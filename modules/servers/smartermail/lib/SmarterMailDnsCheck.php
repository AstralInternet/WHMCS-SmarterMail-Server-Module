<?php
/**
 * SmarterMail — Vérification DNS unifiée (SPF / DKIM / Autodiscover / DMARC)
 *
 * RÔLE :
 *   Centralise toutes les requêtes DNS effectuées par le tableau de bord client
 *   et expose des helpers à l'usage de :
 *     - smartermail.php (calcul initial à l'affichage de la page)
 *     - L'endpoint AJAX checkdns (rafraîchissement asynchrone côté client)
 *     - Le générateur DMARC (construction du record à copier)
 *
 * STRATÉGIE DE PERFORMANCE :
 *   - Cache DB (table mod_sm_dns_cache) avec TTL 4 h par défaut. Les requêtes
 *     DNS coûteuses (200-500 ms chacune) ne sont pas refaites pour chaque
 *     visite du tableau de bord. Un TTL long est acceptable car les
 *     enregistrements DNS changent rarement, et le client peut toujours
 *     forcer un rafraîchissement via le bouton "Actualiser".
 *   - Mode `cacheOnly` : si le cache est vide pour une vérification, on
 *     retourne `null` au lieu de faire la requête DNS live. Permet au
 *     dashboard de se rendre instantanément en marquant les statuts comme
 *     "loading", puis de déclencher un appel AJAX checkdns au chargement
 *     pour remplir le cache et mettre à jour l'UI sans bloquer la page.
 *   - Le client peut forcer un rafraîchissement via le bouton Refresh, qui
 *     POSTe customAction=refreshdns avec un jeton CSRF — bypass du cache.
 *
 * SÉCURITÉ :
 *   - Tous les hôtes interrogés sont dérivés de $params['domain'] (validé par
 *     WHMCS) ou de configoptions admin → pas d'input client direct dans une
 *     requête DNS, donc pas de SSRF possible via ce code.
 *   - dns_get_record() est appelé avec @ pour éviter les warnings PHP qui
 *     fuitraient dans les logs en cas de domaine inexistant ou timeout.
 *
 * DÉPENDANCES :
 *   - WHMCS\Database\Capsule (Eloquent) pour la table de cache
 *   - logActivity() pour la traçabilité non bloquante
 */

if (!defined('WHMCS')) { die('Accès direct interdit.'); }

use WHMCS\Database\Capsule;

// =============================================================================
//  TABLE DE CACHE
// =============================================================================

/**
 * Crée la table mod_sm_dns_cache si elle n'existe pas.
 *
 * SCHÉMA :
 *   - host        : nom complet interrogé (ex: '_dmarc.client.com')
 *   - record_type : type DNS textuel (TXT, A, CNAME, SRV, NS)
 *   - payload     : tableau de réponse json_encode (résultat brut de
 *                   dns_get_record). On stocke le tableau complet pour
 *                   pouvoir extraire ultérieurement plusieurs champs sans
 *                   refaire la requête.
 *   - cached_at   : timestamp Unix de la mise en cache. Sert au calcul TTL.
 *
 * Cache statique : la vérification est faite UNE SEULE FOIS par exécution
 * PHP — évite un round-trip SHOW TABLES par appel de _sm_dnsLookup().
 */
function _sm_ensureDnsCacheTable(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        if (Capsule::schema()->hasTable('mod_sm_dns_cache')) return;

        Capsule::schema()->create('mod_sm_dns_cache', function ($table) {
            $table->increments('id');
            $table->string('host', 253);          // RFC 1035 max
            $table->string('record_type', 10);    // 'A','CNAME','TXT','SRV','NS','MX'
            $table->longText('payload');          // json_encode(dns_get_record)
            $table->integer('cached_at')->unsigned();
            // Une seule entrée par couple (host, record_type) — updateOrInsert
            // remplace la valeur précédente.
            $table->unique(['host', 'record_type'], 'uq_sm_dns_host_type');
            $table->index('cached_at', 'idx_sm_dns_cached_at'); // purge possible
        });

        logActivity('SmarterMail [dns-cache] Table mod_sm_dns_cache créée.');
    } catch (\Throwable $e) {
        logActivity('SmarterMail [dns-cache] Erreur création table : ' . $e->getMessage());
    }
}

/**
 * Convertit la constante DNS_* en chaîne pour la colonne record_type.
 * Permet aux requêtes DB d'être lisibles (TXT/A/SRV/...) au lieu d'entiers.
 */
function _sm_dnsTypeName(int $type): string
{
    static $map = null;
    if ($map === null) {
        $map = [
            DNS_A     => 'A',
            DNS_CNAME => 'CNAME',
            DNS_TXT   => 'TXT',
            DNS_NS    => 'NS',
            DNS_SRV   => 'SRV',
            DNS_MX    => 'MX',
        ];
    }
    return $map[$type] ?? ('T' . $type);
}

// =============================================================================
//  REQUÊTE DNS AVEC CACHE
// =============================================================================

/**
 * Effectue (ou lit en cache) une requête DNS.
 *
 * @param string $host         Nom complet à interroger
 * @param int    $type         Constante DNS_* (DNS_TXT, DNS_A, DNS_SRV, ...)
 * @param int    $ttl          Durée de vie cache en secondes (défaut 14400 = 4 h)
 * @param bool   $forceRefresh true = ignorer le cache et refaire la requête live
 * @param bool   $cacheOnly    true = ne PAS faire de requête live en cas de
 *                             cache miss → retourne null. Utilisé au render
 *                             initial du dashboard pour ne pas bloquer la
 *                             page si le cache est vide. La requête live
 *                             sera faite ensuite via l'endpoint AJAX checkdns.
 * @return array|null          Tableau brut retourné par dns_get_record(),
 *                             ou null si cacheOnly=true et cache miss.
 */
function _sm_dnsLookup(
    string $host,
    int $type,
    int $ttl = 14400,
    bool $forceRefresh = false,
    bool $cacheOnly = false
): ?array {
    _sm_ensureDnsCacheTable();
    $typeStr = _sm_dnsTypeName($type);

    // Normalisation : DNS est insensible à la casse, on stocke en minuscules
    $host = strtolower(trim($host));
    if ($host === '') return [];

    // Lecture cache si non forcé
    if (!$forceRefresh) {
        try {
            $row = Capsule::table('mod_sm_dns_cache')
                ->where('host', $host)
                ->where('record_type', $typeStr)
                ->where('cached_at', '>', time() - $ttl)
                ->select('payload')
                ->first();
            if ($row && isset($row->payload)) {
                $decoded = json_decode($row->payload, true);
                if (is_array($decoded)) return $decoded;
            }
        } catch (\Throwable $e) {
            // Erreur de lecture cache : on continue avec la requête live
            // pour ne pas bloquer l'affichage. Pas de log — bruit inutile.
        }
    }

    // Cache miss + mode cacheOnly : on signale au caller pour qu'il marque
    // la vérification comme "loading" et déclenche l'AJAX côté client.
    if ($cacheOnly) {
        return null;
    }

    // Requête live — @ supprime les warnings PHP en cas de timeout/NXDOMAIN.
    // On considère qu'un échec = tableau vide (pas d'enregistrement trouvé),
    // ce qui est sémantiquement correct pour les vérifications de présence.
    $result = @dns_get_record($host, $type);
    if (!is_array($result)) {
        $result = [];
    }

    // Écriture cache — en upsert pour remplacer une entrée stale
    try {
        Capsule::table('mod_sm_dns_cache')->updateOrInsert(
            ['host' => $host, 'record_type' => $typeStr],
            ['payload' => json_encode($result), 'cached_at' => time()]
        );
    } catch (\Throwable $e) {
        // Non bloquant — l'absence de cache ralentit mais n'empêche rien
        logActivity('SmarterMail [dns-cache] Échec écriture cache pour '
            . $host . '/' . $typeStr . ' : ' . $e->getMessage());
    }

    return $result;
}

/**
 * Extrait le contenu textuel d'une entrée TXT retournée par dns_get_record.
 *
 * dns_get_record peut renvoyer le texte sous deux formes selon la config PHP :
 *   - $rec['txt']     : chaîne unique concaténée (la plupart des cas)
 *   - $rec['entries'] : tableau de fragments (records segmentés > 255 bytes)
 *
 * Cette fonction normalise les deux cas en une seule chaîne.
 */
function _sm_dnsExtractTxt(array $rec): string
{
    if (isset($rec['txt']) && is_string($rec['txt'])) {
        return $rec['txt'];
    }
    if (isset($rec['entries']) && is_array($rec['entries'])) {
        return implode('', $rec['entries']);
    }
    return '';
}

// =============================================================================
//  VÉRIFICATIONS PAR TYPE D'ENREGISTREMENT
// =============================================================================

/**
 * Vérifie le SPF du domaine.
 *
 * Logique :
 *   - configoption13 = mécanisme principal (affiché dans le guide DNS)
 *   - configoption18 = mécanismes secondaires (validation seulement, virgules)
 *   - SPF est valide si le record TXT v=spf1 contient l'un des mécanismes
 *     acceptés (principal OU secondaires).
 *
 * STATUT 'loading' : retourné si $cacheOnly=true et que le cache DNS est
 * vide pour ce domaine. Permet au dashboard de se rendre instantanément
 * et de déclencher un appel AJAX pour remplir le cache à la demande.
 *
 * @return array ['status'=>'ok|err|na|loading','found'=>'','expected'=>'','recommended'=>'']
 */
function _sm_checkSpf(string $domain, array $params, bool $forceRefresh = false, bool $cacheOnly = false): array
{
    $expected = trim((string) ($params['configoption13'] ?? ''));

    // Cas spécial : si l'admin n'a PAS configuré de mécanisme SPF, on retourne
    // un statut neutre — la mini-carte SPF s'affiche en NA et indique au client
    // qu'il n'y a rien à faire pour SPF dans ce produit.
    if ($expected === '') {
        return [
            'status'      => 'na',
            'found'       => '',
            'expected'    => '',
            'recommended' => '',
        ];
    }

    // Liste des mécanismes acceptés (principal en premier)
    $accepted = [$expected];
    $secondary = trim((string) ($params['configoption18'] ?? ''));
    if ($secondary !== '') {
        foreach (explode(',', $secondary) as $m) {
            $m = trim($m);
            if ($m !== '') $accepted[] = $m;
        }
    }

    $records = _sm_dnsLookup($domain, DNS_TXT, 14400, $forceRefresh, $cacheOnly);
    // Cache miss + cacheOnly → statut 'loading'
    if ($records === null) {
        return [
            'status'      => 'loading',
            'found'       => '',
            'expected'    => $expected,
            'recommended' => 'v=spf1 ' . $expected . ' ~all',
        ];
    }

    $valid = false;
    $found = '';
    foreach ($records as $rec) {
        $txt = _sm_dnsExtractTxt($rec);
        if (str_starts_with($txt, 'v=spf1')) {
            $found = $txt;
            foreach ($accepted as $mech) {
                if ($mech !== '' && str_contains($txt, $mech)) {
                    $valid = true;
                    break 2;
                }
            }
        }
    }

    return [
        'status'      => $valid ? 'ok' : 'err',
        'found'       => $found,
        'expected'    => $expected,                           // Mécanisme principal seul
        'recommended' => 'v=spf1 ' . $expected . ' ~all',
    ];
}

/**
 * Vérifie le DKIM côté DNS public.
 *
 * Le statut côté SmarterMail (active/standby/disabled) est déterminé
 * par le code appelant à partir de l'API SM. Ici on ne fait que valider
 * la présence du record TXT v=DKIM1 dans le DNS public.
 *
 * Mode cacheOnly : si le cache est vide, on retourne dnsValid=false et
 * loading=true pour signaler au front-end qu'une vérification AJAX est
 * nécessaire.
 *
 * @param array $smDkim Données DKIM brutes de SmarterMail (selector, publicKey, ...)
 * @return array ['dnsValid'=>bool,'host'=>string,'recommended'=>string,'loading'=>bool]
 */
function _sm_checkDkimDns(string $domain, array $smDkim, bool $forceRefresh = false, bool $cacheOnly = false): array
{
    $selector  = (string) ($smDkim['selector']  ?? '');
    $publicKey = (string) ($smDkim['publicKey'] ?? '');

    $host = '';
    $recommended = '';
    if ($selector !== '') {
        $host = $selector . '._domainkey.' . $domain;
        if ($publicKey !== '') {
            $recommended = $smDkim['txtRecord']
                ?? ('v=DKIM1; k=rsa; p=' . $publicKey);
        }
    }

    $dnsValid = false;
    $loading  = false;
    if ($host !== '' && $publicKey !== '') {
        $records = _sm_dnsLookup($host, DNS_TXT, 14400, $forceRefresh, $cacheOnly);
        if ($records === null) {
            $loading = true;
        } else {
            foreach ($records as $rec) {
                $txt = _sm_dnsExtractTxt($rec);
                if (str_contains($txt, 'v=DKIM1') && str_contains($txt, 'p=')) {
                    $dnsValid = true;
                    break;
                }
            }
        }
    }

    return [
        'dnsValid'    => $dnsValid,
        'host'        => $host,
        'recommended' => $recommended,
        'loading'     => $loading,
    ];
}

/**
 * Vérifie Autodiscover : sous-domaine A/CNAME + record SRV.
 *
 * Le statut résultant suit la spec utilisateur :
 *   - rouge  : aucun des deux                 (status='err')
 *   - jaune  : un seul des deux               (status='warn')
 *   - vert   : les deux présents et corrects  (status='ok')
 *
 * Source des valeurs attendues :
 *   - configoption19 (Hôte Autodiscover attendu) — fallback sur serverhostname
 *   - configoption20 (Cible SRV Autodiscover)    — fallback sur serverhostname
 *
 * Le record SRV est `_autodiscover._tcp.{domain}` qui pointe vers la cible
 * sur le port 443.
 *
 * @return array ['status', 'has_a_or_cname', 'has_srv', 'expected_host',
 *                'expected_srv', 'found_host', 'found_srv']
 */
function _sm_checkAutodiscover(string $domain, array $params, bool $forceRefresh = false, bool $cacheOnly = false): array
{
    $serverHost = strtolower(trim((string) ($params['serverhostname'] ?? '')));
    $expectedHost = strtolower(trim((string) ($params['configoption19'] ?? ''))) ?: $serverHost;
    $expectedSrv  = strtolower(trim((string) ($params['configoption20'] ?? ''))) ?: $serverHost;

    // Valeurs recommandées pour le guide DNS — toujours calculables sans DNS.
    // Permet aux modales de fonctionner même en mode loading.
    $recommended = [
        'recommended_cname' => $expectedHost,
        'recommended_srv_target' => $expectedSrv,
        'recommended_srv_port'   => 443,
        'recommended_srv_priority' => 0,
        'recommended_srv_weight'   => 0,
        'expected_host'  => $expectedHost,
        'expected_srv'   => $expectedSrv,
    ];

    // Sous-domaine autodiscover.{domain} — peut être A ou CNAME
    $autoHost = 'autodiscover.' . $domain;

    // CNAME prioritaire
    $cname = _sm_dnsLookup($autoHost, DNS_CNAME, 14400, $forceRefresh, $cacheOnly);
    if ($cname === null) {
        // Cache miss → loading. On retourne tout de suite avec les valeurs
        // recommandées pour que les modales soient utilisables.
        return array_merge([
            'status'         => 'loading',
            'has_a_or_cname' => false,
            'has_srv'        => false,
            'found_host'     => '',
            'found_srv'      => '',
        ], $recommended);
    }

    $foundHost = '';
    foreach ($cname as $rec) {
        if (!empty($rec['target'])) {
            $foundHost = strtolower(rtrim($rec['target'], '.'));
            break;
        }
    }
    // ── Détermination du match CNAME ──────────────────────────────────────
    // Si la cible CNAME correspond exactement à l'hôte attendu, c'est OK.
    $hasHost = ($foundHost !== '' && $foundHost === strtolower($expectedHost));

    // ── Cas A : pas de CNAME, on tente le record A ────────────────────────
    //
    // Beaucoup de clients préfèrent un record A pointant directement vers
    // l'IP du serveur de courriel plutôt qu'un CNAME (par habitude, ou
    // parce que leur registrar ne supporte pas les CNAME à la racine d'un
    // sous-domaine, ou parce qu'ils ont copié-collé une config existante).
    //
    // Pour considérer un A comme valide, on doit vérifier qu'il pointe vers
    // une IP qui correspond effectivement à $expectedHost. La méthode :
    //   1. Lire le(s) record(s) A de autodiscover.{domain}    (1 requête)
    //   2. Résoudre $expectedHost en IP(s)                    (1 requête, cachée)
    //   3. Match si une IP du A client est dans le set de l'expected
    //
    // Avantage par rapport à une configoption "IP attendue" : pas de
    // configuration à maintenir si l'IP de mail2.astralinternet.com change.
    if (!$hasHost) {
        $a = _sm_dnsLookup($autoHost, DNS_A, 14400, $forceRefresh, $cacheOnly);
        if ($a === null) {
            return array_merge([
                'status'         => 'loading',
                'has_a_or_cname' => false,
                'has_srv'        => false,
                'found_host'     => $foundHost, // peut être un CNAME non-matchant
                'found_srv'      => '',
            ], $recommended);
        }

        if (!empty($a)) {
            // IPs trouvées dans le record A du client autodiscover.{domain}
            $foundIps = array_values(array_filter(array_column($a, 'ip')));

            // Si pas de CNAME du tout, on remplit found_host avec la 1re IP
            // pour affichage dans la modale (sinon le client voit "vide").
            if ($foundHost === '' && !empty($foundIps)) {
                $foundHost = $foundIps[0];
            }

            // On résout $expectedHost pour comparer les IPs. Cette requête
            // passe par le même cache (4 h) — appel quasi-gratuit en régime
            // établi. Ne se déclenche que si le CNAME n'a pas déjà matché.
            if (!empty($foundIps) && $expectedHost !== '') {
                $expectedA = _sm_dnsLookup($expectedHost, DNS_A, 14400, $forceRefresh, $cacheOnly);
                if ($expectedA === null) {
                    return array_merge([
                        'status'         => 'loading',
                        'has_a_or_cname' => false,
                        'has_srv'        => false,
                        'found_host'     => $foundHost,
                        'found_srv'      => '',
                    ], $recommended);
                }

                $expectedIps = array_values(array_filter(array_column($expectedA, 'ip')));
                // Match si AU MOINS UNE IP du client est dans le set de
                // l'expected (gère les multi-A / round-robin DNS).
                if (!empty($expectedIps) && !empty(array_intersect($foundIps, $expectedIps))) {
                    $hasHost = true;
                }
            }
        }
    }

    // SRV _autodiscover._tcp.{domain}
    $srvHost = '_autodiscover._tcp.' . $domain;
    $srvRecords = _sm_dnsLookup($srvHost, DNS_SRV, 14400, $forceRefresh, $cacheOnly);
    if ($srvRecords === null) {
        return array_merge([
            'status'         => 'loading',
            'has_a_or_cname' => $hasHost,
            'has_srv'        => false,
            'found_host'     => $foundHost,
            'found_srv'      => '',
        ], $recommended);
    }

    $foundSrv = '';
    $hasSrv = false;
    foreach ($srvRecords as $rec) {
        if (!empty($rec['target'])) {
            $target = strtolower(rtrim($rec['target'], '.'));
            $foundSrv = $target;
            // Match si cible = expected (ou expected vide → on accepte tout SRV)
            if ($expectedSrv === '' || $target === $expectedSrv) {
                $hasSrv = true;
                break;
            }
        }
    }

    // Détermination du statut
    if ($hasHost && $hasSrv)        $status = 'ok';
    elseif ($hasHost || $hasSrv)    $status = 'warn';
    else                            $status = 'err';

    return array_merge([
        'status'         => $status,
        'has_a_or_cname' => $hasHost,
        'has_srv'        => $hasSrv,
        'found_host'     => $foundHost,
        'found_srv'      => $foundSrv,
    ], $recommended);
}

/**
 * Vérifie le DMARC : record TXT à _dmarc.{domain}.
 *
 * Statut binaire :
 *   - rouge : aucun record DMARC trouvé    (status='err')
 *   - vert  : record DMARC présent valide  (status='ok')
 *
 * Note : on ne valide PAS la politique du record (p=none/quarantine/reject)
 * — ce n'est pas le rôle du module de juger. On valide juste la présence
 * d'un record qui démarre par v=DMARC1.
 *
 * Si configoption21 = off, on retourne status='na' (vérification désactivée).
 */
function _sm_checkDmarc(string $domain, array $params, bool $forceRefresh = false, bool $cacheOnly = false): array
{
    $enabled = ($params['configoption21'] ?? 'on') === 'on';
    if (!$enabled) {
        return ['status' => 'na', 'found' => '', 'enabled' => false];
    }

    $records = _sm_dnsLookup('_dmarc.' . $domain, DNS_TXT, 14400, $forceRefresh, $cacheOnly);
    if ($records === null) {
        return [
            'status'  => 'loading',
            'found'   => '',
            'enabled' => true,
        ];
    }

    $found = '';
    foreach ($records as $rec) {
        $txt = _sm_dnsExtractTxt($rec);
        if (str_starts_with($txt, 'v=DMARC1')) {
            $found = $txt;
            break;
        }
    }

    return [
        'status'  => $found !== '' ? 'ok' : 'err',
        'found'   => $found,
        'enabled' => true,
    ];
}

// =============================================================================
//  COLLECTE GLOBALE
// =============================================================================

/**
 * Agrège les 4 vérifications DNS (SPF, DKIM, Autodiscover, DMARC) pour le
 * tableau de bord du client.
 *
 * Le DKIM côté SmarterMail (statut active/standby/disabled) doit être passé
 * en paramètre — cette fonction ne fait pas l'appel API SM elle-même.
 *
 * MODES :
 *   - $forceRefresh = true  → ignore le cache, fait toutes les requêtes DNS
 *                             en live (utilisé par le bouton Refresh).
 *   - $cacheOnly    = true  → lit UNIQUEMENT le cache, ne fait pas de requête
 *                             live. Si le cache est vide, retourne des statuts
 *                             'loading' (utilisé au render initial pour ne pas
 *                             bloquer la page). Le front-end déclenche ensuite
 *                             un appel AJAX checkdns pour remplir le cache.
 *   - les deux à false      → cache normal : lit le cache, fait du live au
 *                             cache miss (utilisé par checkdns AJAX).
 *
 * Le résultat inclut un drapeau `loading` global = true si AU MOINS UNE des
 * 4 vérifications est en mode loading (utile pour décider de déclencher
 * l'AJAX initial côté template).
 *
 * @param string $domain        Domaine du service (ex: 'client.com')
 * @param array  $params        Paramètres WHMCS (configoptions, serverhostname)
 * @param array  $smDkim        Données DKIM brutes de l'API SmarterMail
 * @param bool   $forceRefresh  true = ignore le cache pour toutes les requêtes
 * @param bool   $cacheOnly     true = retourne 'loading' au lieu de live lookup
 * @return array                Structure imbriquée prête à être passée au template
 */
function _sm_collectDnsStatus(
    string $domain,
    array $params,
    array $smDkim,
    bool $forceRefresh = false,
    bool $cacheOnly = false
): array {
    $domain = strtolower(trim($domain));

    $spf  = _sm_checkSpf($domain, $params, $forceRefresh, $cacheOnly);
    $dkim = _sm_checkDkimDns($domain, $smDkim, $forceRefresh, $cacheOnly);
    $auto = _sm_checkAutodiscover($domain, $params, $forceRefresh, $cacheOnly);
    $dmarc= _sm_checkDmarc($domain, $params, $forceRefresh, $cacheOnly);

    // `loading` global = vrai si au moins une vérif n'a pas pu lire le cache
    $loading =
           ($spf['status']   === 'loading')
        || (!empty($dkim['loading']))
        || ($auto['status']  === 'loading')
        || ($dmarc['status'] === 'loading');

    return [
        'spf'          => $spf,
        'dkim_dns'     => $dkim,
        'autodiscover' => $auto,
        'dmarc'        => $dmarc,
        'checked_at'   => time(),
        'loading'      => $loading,
    ];
}

// =============================================================================
//  PURGE DU CACHE DNS
// =============================================================================
//
//  Deux mécanismes complémentaires sont fournis :
//
//  1. _sm_purgeDnsCacheForDomain($domain) — purge IMMÉDIATE quand un domaine
//     n'a plus de service actif (appelé depuis smartermail_TerminateAccount).
//     Évite que les entrées d'un client résilié polluent le cache.
//
//  2. _sm_cleanDnsCacheStale($maxAgeSeconds) — purge HEBDOMADAIRE des entrées
//     trop vieilles (par défaut > 7 jours). Sert de filet de sécurité au
//     cas où la purge immédiate aurait été manquée (cron qui n'a pas tourné,
//     domaine modifié à la main dans la DB, etc.).
//
//  Les deux fonctions sont appelées depuis hooks.php :
//   - DailyCronJob → _sm_cleanDnsCacheStale (chaque dimanche)
//   - TerminateAccount → _sm_purgeDnsCacheForDomain (immédiat)
// =============================================================================

/**
 * Purge toutes les entrées de cache DNS associées à un domaine donné.
 *
 * Cible explicitement les hôtes interrogés par le module pour ce domaine :
 *   - {domain}                          → SPF (TXT racine)
 *   - _dmarc.{domain}                   → DMARC (TXT)
 *   - autodiscover.{domain}             → Autodiscover CNAME/A
 *   - _autodiscover._tcp.{domain}       → Autodiscover SRV
 *   - *._domainkey.{domain}             → DKIM (sélecteur variable, LIKE)
 *
 * SÉCURITÉ : le pattern LIKE est ancré sur `._domainkey.{domain}` exactement
 * — le `%` ne peut donc PAS matcher d'autres domaines (ex: une entrée pour
 * `mail._domainkey.autre-client.com` ne sera pas effacée si on purge
 * `autre.com`). Le suffixe pleinement qualifié garantit l'isolation.
 *
 * @param string $domain Le domaine du service (ex: 'client.com')
 * @return int Nombre d'entrées supprimées
 */
function _sm_purgeDnsCacheForDomain(string $domain): int
{
    _sm_ensureDnsCacheTable();
    $domain = strtolower(trim($domain));
    if ($domain === '') return 0;

    try {
        return Capsule::table('mod_sm_dns_cache')
            ->where(function ($q) use ($domain) {
                $q->where('host', '=', $domain)
                  ->orWhere('host', '=', '_dmarc.' . $domain)
                  ->orWhere('host', '=', 'autodiscover.' . $domain)
                  ->orWhere('host', '=', '_autodiscover._tcp.' . $domain)
                  // DKIM : sélecteur variable. On échappe les caractères
                  // spéciaux LIKE ('_', '%', '\') pour ne matcher QUE
                  // littéralement, puis on ajoute le wildcard initial.
                  ->orWhere('host', 'like', '%._domainkey.' . str_replace(
                      ['\\', '%', '_'],
                      ['\\\\', '\\%', '\\_'],
                      $domain
                  ));
            })
            ->delete();
    } catch (\Throwable $e) {
        logActivity('SmarterMail [dns-cache] Erreur purge domaine ' . $domain . ' : ' . $e->getMessage());
        return 0;
    }
}

/**
 * Purge les entrées de cache DNS plus anciennes qu'un seuil donné.
 *
 * Roulé par le DailyCronJob chaque dimanche (voir hooks.php).
 * Sert de filet de sécurité : même si la purge ciblée par domaine a été
 * manquée, les entrées vieillissantes sont retirées après 7 jours par défaut
 * (largement plus long que le TTL de 4 h utilisé par le module — donc on
 * n'efface JAMAIS une entrée encore valide).
 *
 * @param int $maxAgeSeconds Seuil d'âge en secondes (défaut 604800 = 7 jours)
 * @return int Nombre d'entrées supprimées
 */
function _sm_cleanDnsCacheStale(int $maxAgeSeconds = 604800): int
{
    _sm_ensureDnsCacheTable();
    $cutoff = time() - max(3600, $maxAgeSeconds); // garde-fou : au moins 1 h

    try {
        return Capsule::table('mod_sm_dns_cache')
            ->where('cached_at', '<', $cutoff)
            ->delete();
    } catch (\Throwable $e) {
        logActivity('SmarterMail [dns-cache] Erreur nettoyage entrées périmées : ' . $e->getMessage());
        return 0;
    }
}


// =============================================================================
//  GÉNÉRATEUR DE RECORD DMARC
// =============================================================================

/**
 * Construit un record TXT DMARC à partir des choix de l'utilisateur dans
 * la modale "Générateur DMARC".
 *
 * Référence RFC 7489 §6.3 — Tags supportés :
 *   v=DMARC1   (obligatoire, fixe)
 *   p=         (politique : none/quarantine/reject)
 *   sp=        (politique sous-domaines, optionnel)
 *   adkim=     (alignement DKIM : r=relaxed, s=strict)
 *   aspf=      (alignement SPF  : r=relaxed, s=strict)
 *   pct=       (pourcentage 0-100, défaut 100)
 *   rf=        (format de rapport : afrf, iodef)
 *   ri=        (intervalle rapports en secondes, défaut 86400)
 *   rua=       (URI rapports agrégés, ex: mailto:dmarc@example.com)
 *   ruf=       (URI rapports d'échec)
 *
 * VALIDATION :
 *   - Toutes les valeurs sont normalisées (lowercase, whitelist) côté
 *     serveur pour ne jamais émettre un record syntaxiquement invalide.
 *   - Les emails RUA/RUF sont validés par filter_var(FILTER_VALIDATE_EMAIL)
 *     et préfixés de "mailto:" si l'utilisateur omet le schéma.
 *   - Si une valeur est invalide, le tag correspondant est OMIS du record
 *     (jamais d'erreur — le client a le record le plus permissif possible).
 *
 * @param array $input Tableau associatif venant du formulaire (POST ou JSON)
 * @return string      Record TXT complet (ex: "v=DMARC1; p=none; rua=mailto:...")
 */
function _sm_buildDmarcRecord(array $input): string
{
    $tags = ['v=DMARC1'];

    // Politique principale (obligatoire, défaut 'none')
    $allowedPolicy = ['none', 'quarantine', 'reject'];
    $policy = strtolower(trim((string) ($input['policy'] ?? 'none')));
    if (!in_array($policy, $allowedPolicy, true)) $policy = 'none';
    $tags[] = 'p=' . $policy;

    // Politique sous-domaines (optionnel — n'inclure que si différent de p)
    $subPolicy = strtolower(trim((string) ($input['subpolicy'] ?? '')));
    if ($subPolicy !== '' && in_array($subPolicy, $allowedPolicy, true) && $subPolicy !== $policy) {
        $tags[] = 'sp=' . $subPolicy;
    }

    // Alignement DKIM
    $allowedAlign = ['r', 's'];
    $adkim = strtolower(trim((string) ($input['adkim'] ?? 'r')));
    if (!in_array($adkim, $allowedAlign, true)) $adkim = 'r';
    if ($adkim !== 'r') $tags[] = 'adkim=' . $adkim; // r est défaut RFC, omis

    // Alignement SPF
    $aspf = strtolower(trim((string) ($input['aspf'] ?? 'r')));
    if (!in_array($aspf, $allowedAlign, true)) $aspf = 'r';
    if ($aspf !== 'r') $tags[] = 'aspf=' . $aspf;

    // Pourcentage
    $pct = (int) ($input['pct'] ?? 100);
    $pct = max(0, min(100, $pct));
    if ($pct !== 100) $tags[] = 'pct=' . $pct; // 100 est défaut RFC, omis

    // Format de rapport
    $allowedFormat = ['afrf', 'iodef'];
    $rf = strtolower(trim((string) ($input['rf'] ?? 'afrf')));
    if (!in_array($rf, $allowedFormat, true)) $rf = 'afrf';
    if ($rf !== 'afrf') $tags[] = 'rf=' . $rf;

    // Intervalle de rapports (secondes)
    $ri = (int) ($input['ri'] ?? 86400);
    $ri = max(60, min(604800, $ri)); // bornes raisonnables : 1 min à 1 sem
    if ($ri !== 86400) $tags[] = 'ri=' . $ri;

    // URIs de rapport — RUA et RUF
    foreach (['rua', 'ruf'] as $tag) {
        $raw = trim((string) ($input[$tag] ?? ''));
        if ($raw === '') continue;
        // Plusieurs adresses possibles, séparées par virgule
        $uris = [];
        foreach (explode(',', $raw) as $email) {
            $email = trim($email);
            if ($email === '') continue;
            // Si le client a déjà mis "mailto:", on le retire pour valider l'email
            if (stripos($email, 'mailto:') === 0) {
                $email = substr($email, 7);
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $uris[] = 'mailto:' . $email;
            }
        }
        if (!empty($uris)) {
            $tags[] = $tag . '=' . implode(',', $uris);
        }
    }

    return implode('; ', $tags);
}
