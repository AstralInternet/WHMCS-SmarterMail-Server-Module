<?php
/**
 * ============================================================================
 *  hooks.php — Hooks WHMCS pour la facturation dynamique SmarterMail
 * ============================================================================
 *
 * Ce fichier est chargé AUTOMATIQUEMENT par WHMCS parce qu'il se trouve dans
 * le répertoire du module serveur (modules/servers/smartermail/hooks.php).
 * Aucune configuration supplémentaire n'est nécessaire pour l'activer.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  POURQUOI UN HOOK PLUTÔT QU'UNE INTÉGRATION NATIVE WHMCS ?
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Le système de facturation standard de WHMCS utilise un prix fixe mensuel
 *  par produit. Pour notre hébergement courriel, le prix VARIE selon :
 *    1. L'espace disque réellement utilisé (facturation à la tranche)
 *    2. Le nombre de boîtes avec ActiveSync activé (EAS, prix/boîte)
 *    3. Le nombre de boîtes avec MAPI activé (prix/boîte)
 *    4. Les boîtes ayant les deux (tarif combiné réduit)
 *
 *  Le hook InvoiceCreated intercepte la facture AU MOMENT DE SA GÉNÉRATION
 *  et la modifie avant qu'elle ne soit envoyée au client. C'est la seule
 *  façon de faire de la facturation variable dans WHMCS sans addon externe.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  FLUX DE FACTURATION COMPLET
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  1. CRON WHMCS (quotidien) :
 *     → Appelle smartermail_UsageUpdate() pour chaque service actif
 *     → UsageUpdate interroge l'API SmarterMail → diskUsage en bytes → converti en MB
 *     → WHMCS stocke la valeur dans tblhosting.diskusage (en MB)
 *
 *  2. GÉNÉRATION DE FACTURE (mensuelle, selon configuration WHMCS) :
 *     → WHMCS génère une facture avec le prix mensuel du produit comme montant
 *     → Le hook InvoiceCreated est déclenché IMMÉDIATEMENT après la création
 *     → Le hook lit tbltenant_stats (metric='disk_gb') pour le calcul des tranches
 *     → Le hook se connecte à SmarterMail pour lire les boîtes EAS/MAPI actives
 *     → Le hook MODIFIE le montant et la description de la ligne de la facture
 *     → Le hook AJOUTE des lignes supplémentaires pour EAS/MAPI
 *     → Le hook RECALCULE le total de la facture
 *
 *  3. FACTURE FINALE envoyée au client avec les montants calculés.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  EXEMPLE DE FACTURE GÉNÉRÉE
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Produit       : Hébergement courriel client.com
 *  Prix produit  : 6.00$/mois (= prix pour 1 tranche de 10 Go)
 *  configoption1 : 10 (GB par tranche)
 *  configoption2 : 2.00$ (EAS)
 *  configoption3 : 3.00$ (MAPI)
 *  configoption4 : 4.50$ (EAS+MAPI combiné)
 *  Utilisation   : 21 Go (= 3 tranches × 10 Go = 30 Go facturés)
 *  Boîtes EAS    : jean@client.com, marie@client.com
 *  Boîtes MAPI   : jean@client.com (jean a EAS+MAPI → tarif combiné)
 *
 *  AVANT hook :
 *    Hébergement courriel (client.com) ... 6.00$
 *
 *  APRÈS hook :
 *    Hébergement courriel (client.com) (2026/05/12 - 2026/06/11)
 *    » 21.00 Go utilisé · 3 Tranche(s) de 10 Go × $6 .......................... 18.00$
 *    EAS + MAPI/Exchange : jean@client.com ................................................ 4.50$
 *    ActiveSync (EAS) : marie@client.com .................................................. 2.00$
 *    ─────────────────────────────────────────────────────────────────────────
 *    TOTAL ............................................................................24.50$
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  TABLES WHMCS IMPLIQUÉES
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  tblinvoices        → En-tête de la facture (id, total, duedate, etc.)
 *  tblinvoiceitems    → Lignes de la facture (description, amount, type, relid)
 *  tblhosting         → Services des clients (domain, diskusage, packageid, server)
 *  tblproducts        → Produits WHMCS (servertype, configoptionN)
 *  tblservers         → Serveurs configurés (hostname, username, password, etc.)
 *  tblpricing         → Prix des produits (monthly, annually, etc.)
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

// Définir le chemin absolu vers le répertoire lib du module une seule fois.
// L'utilisation de define() avec un guard if(!defined()) garantit deux choses :
//   1. Le chemin est résolu avec realpath() — immunisé contre les variations de
//      contexte d'exécution (__DIR__ peut se comporter différemment selon que
//      WHMCS charge le hook depuis un cron, une page web ou un appel API).
//   2. Les fichiers ne sont inclus qu'une seule fois même si le hook est chargé
//      plusieurs fois (ex: si smartermail.php est déjà chargé et a déjà inclus
//      ces fichiers via son propre require_once).
if (!defined('SM_MODULE_LIB')) {
    define('SM_MODULE_LIB', realpath(dirname(__FILE__) . '/lib'));
}

if (defined('SM_MODULE_LIB')) {
    require_once SM_MODULE_LIB . '/SmarterMailApi.php';
    require_once SM_MODULE_LIB . '/SmarterMailProtoUsage.php';
    // Helpers de cache DNS (utilisés par le DailyCronJob pour la purge hebdo)
    require_once SM_MODULE_LIB . '/SmarterMailDnsCheck.php';
}

use WHMCS\Database\Capsule;


// =============================================================================
//  FONCTION UTILITAIRE : Chargement de la langue (contexte hook)
// =============================================================================

/**
 * Charge et retourne le tableau de traduction pour le contexte des hooks.
 *
 * Contrairement à _sm_lang() (dans smartermail.php), cette version N'ACCEPTE
 * PAS de paramètre $params car les hooks comme InvoiceCreation sont déclenchés
 * sans contexte de client spécifique — ils traitent potentiellement plusieurs
 * clients dans la même exécution du cron WHMCS.
 *
 * STRATÉGIE DE LANGUE :
 *   On utilise la langue par défaut du SYSTÈME WHMCS (CONFIG['Language']).
 *   Les textes apparaissant sur les factures s'affichent dans la langue du
 *   système, indépendamment de la langue préférée du client individuel.
 *
 * ORDRE DE RÉSOLUTION :
 *   1. $GLOBALS['CONFIG']['Language'] — langue système WHMCS
 *   2. Fallback : 'french' (notre langue principale d'exploitation)
 *   3. Fallback final : 'english' si le fichier de langue n'existe pas
 *
 * CACHE STATIQUE :
 *   Le tableau est chargé une seule fois par exécution PHP (static $cache),
 *   même si le hook traite plusieurs factures dans le même cron.
 *
 * SÉCURITÉ — PATH TRAVERSAL :
 *   Le nom de langue est nettoyé (strtolower + preg_replace) avant d'être
 *   utilisé pour construire le chemin du fichier. Cela neutralise toute
 *   tentative d'injection via CONFIG['Language'] (ex : "../../config").
 *   De plus, realpath() valide que le fichier résolu est bien dans /lang/.
 *
 * @return array<string, string> Tableau de traduction indexé par clé de langue
 */
function _sm_hookLang(): array
{
    // Cache statique : chargé une seule fois pour toute la durée du cron.
    // Évite les inclusions de fichier répétées lorsque le hook traite
    // plusieurs factures dans le même appel du cron WHMCS.
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // ── Résolution de la langue système ──────────────────────────────────────
    //
    // WHMCS expose sa configuration via $GLOBALS['CONFIG']['Language'].
    // Valeurs possibles : 'english', 'french', 'spanish', etc.
    // On retire tout caractère non-alphabétique (sauf tiret) pour prévenir
    // les attaques de type path traversal sur le nom du fichier de langue.
    $rawLanguage = $GLOBALS['CONFIG']['Language'] ?? 'french';
    $language    = preg_replace('/[^a-z\-]/', '', strtolower(trim((string) $rawLanguage)));

    // ── Résolution sécurisée du répertoire lang ───────────────────────────────
    // realpath() résout les symlinks et les séquences '..' — garantit que
    // $langDir pointe bien sur le répertoire lang du module, pas ailleurs.
    $langDir  = realpath(__DIR__ . '/lang');

    // Vérification de sécurité : si realpath() échoue (répertoire manquant),
    // on retourne un tableau vide plutôt que de risquer une inclusion hors-path.
    if ($langDir === false) {
        return $cache = [];
    }

    // Construction du chemin et validation qu'il reste dans $langDir
    $langFile = $langDir . '/' . $language . '.php';
    if (!file_exists($langFile) || strpos(realpath($langFile), $langDir) !== 0) {
        // Fallback vers le français (langue principale d'exploitation)
        $langFile = $langDir . '/french.php';
    }

    // Fallback final vers l'anglais si même le français est absent
    if (!file_exists($langFile)) {
        $langFile = $langDir . '/english.php';
    }

    // ── Chargement du tableau $_lang ─────────────────────────────────────────
    //
    // Le fichier de langue déclare : $_lang = [ 'clé' => 'valeur', ... ]
    // On l'inclut dans une portée locale — $_lang sera disponible juste après.
    // Initialisation explicite à [] pour éviter tout résidu d'include précédent.
    $_lang = [];
    if (file_exists($langFile)) {
        include $langFile; // $_lang est peuplé par le fichier inclus
    }

    return $cache = $_lang;
}


// =============================================================================
//  FONCTION UTILITAIRE : Compte administrateur pour localAPI
// =============================================================================

/**
 * Retourne le username d'un administrateur WHMCS actif, requis comme contexte
 * d'exécution par localAPI() (notamment pour UpdateInvoice).
 *
 * On sélectionne le plus ancien administrateur non désactivé (id le plus bas =
 * généralement le super-administrateur principal), qui dispose des permissions
 * complètes. Le résultat est mis en cache statique pour toute la durée du cron.
 *
 * @return string Username admin, ou '' si aucun admin actif n'est trouvé
 *                (dans ce cas l'appelant journalise et n'effectue pas l'appel API).
 */
function _sm_getAdminUsername(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    try {
        $username = Capsule::table('tbladmins')
            ->where('disabled', 0)
            ->orderBy('id')
            ->value('username');
        $cached = (string) ($username ?? '');
    } catch (\Throwable $e) {
        logActivity('SmarterMail [getAdminUsername] EXCEPTION : ' . $e->getMessage());
        $cached = '';
    }

    return $cached;
}


// =============================================================================
//  HOOK : InvoiceCreated — Facturation dynamique
// =============================================================================

/**
 * Hook déclenché IMMÉDIATEMENT après la création d'une facture dans WHMCS.
 *
 * Ce hook est le cœur du système de facturation variable de ce module.
 * Il transforme une facture avec un montant fixe en une facture détaillée
 * reflétant l'utilisation réelle du client.
 *
 * PARAMÈTRE $params :
 *   $params['invoiceid'] → ID de la facture nouvellement créée (tblinvoices.id)
 *
 * LOGIQUE GLOBALE :
 *   Pour chaque ligne "Hosting" de la facture :
 *     1. Vérifier que le service utilise le module "smartermail"
 *     2. Lire l'utilisation disque depuis tbltenant_stats (metric='disk_gb')
 *     3. Calculer les tranches et modifier la ligne principale de la facture
 *     4. Se connecter à SmarterMail pour lire les boîtes EAS/MAPI actives
 *     5. Ajouter une ligne par boîte avec EAS et/ou MAPI activé
 *     6. Recalculer le total de la facture
 *
 * GESTION DES ERREURS :
 *   - Les erreurs de connexion API sont capturées et loguées (logActivity)
 *   - La facturation disque de base fonctionne SANS connexion à SmarterMail
 *     (basée uniquement sur tbltenant_stats)
 *   - La facturation EAS/MAPI nécessite une connexion API réussie
 *   - En cas d'erreur API, les lignes EAS/MAPI sont simplement omises
 *     (le client n'est pas facturé pour EAS/MAPI ce mois-ci — à surveiller)
 *
 * IDEMPOTENCE :
 *   ⚠️  Ce hook N'EST PAS idempotent. Si appelé deux fois sur la même facture
 *   (ce qui ne devrait pas arriver normalement), il ajouterait des lignes en double.
 *   WHMCS ne déclenche InvoiceCreated qu'une seule fois par facture.
 */
add_hook('InvoiceCreation', 1, function (array $params) {

    // Récupérer l'ID de la facture depuis les paramètres du hook
    $invoiceId = (int) $params['invoiceid'];
    if (!$invoiceId) {
        return; // Sécurité : ne rien faire si l'ID est invalide
    }

    // ── Étape 1 : Trouver les lignes "Hosting" de cette facture ──────────────
    //
    // WHMCS crée différents types de lignes dans tblinvoiceitems :
    //   type = 'Hosting'    → Service d'hébergement (ce qui nous intéresse)
    //   type = 'Domain'     → Nom de domaine
    //   type = 'DomainRenew'→ Renouvellement de domaine
    //   type = 'AddOnProduct'→ Produit addon
    //   type = 'LateFee'    → Frais de retard
    //   type = 'PromoDiscount'→ Remise promotionnelle
    //
    // 'relid' pour une ligne Hosting = tblhosting.id (ID du service)
    $items = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where('type', 'Hosting')
        ->get();

    // Aucune ligne d'hébergement dans cette facture → rien à faire
    if ($items->isEmpty()) {
        return;
    }

    // ── Étape 2 : Traiter chaque ligne d'hébergement ─────────────────────────
    //
    // Une facture peut contenir plusieurs services d'hébergement si le client
    // a plusieurs produits actifs avec dates de renouvellement identiques.
    foreach ($items as $item) {
      try {  // ── Catch global : protège le cron en cas d'erreur inattendue ──
        $serviceId = (int) $item->relid;

        // ── Vérification : Ce service utilise-t-il notre module ? ─────────────
        //
        // On joint tblhosting avec tblproducts pour vérifier servertype,
        // et avec tblservers pour récupérer les infos de connexion au serveur.
        //
        // IMPORTANT : On vérifie servertype = 'smartermail' pour ne modifier
        // que les factures des services utilisant CE module. Les autres services
        // d'hébergement (cPanel, Plesk, etc.) ne doivent PAS être touchés.
        $service = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->join('tblservers',  'tblhosting.server',    '=', 'tblservers.id')
            ->where('tblhosting.id', $serviceId)
            ->where('tblproducts.servertype', 'smartermail')  // ← Filtre essentiel
            ->select(
                // Infos du service
                'tblhosting.id        as serviceid',
                'tblhosting.domain',
                'tblhosting.packageid',
                // Paramètres du module (configoptionN)
                'tblproducts.configoption1',    // GB par tranche
                'tblproducts.configoption2',    // Prix EAS
                'tblproducts.configoption3',    // Prix MAPI
                'tblproducts.configoption4',    // Prix combiné EAS+MAPI
                // Infos du serveur SmarterMail pour la connexion API
                'tblservers.hostname  as serverhostname',
                'tblservers.secure    as serversecure',
                'tblservers.port      as serverport',
                'tblservers.username  as serverusername',
                'tblservers.password  as serverpassword',
                'tblproducts.configoption16',    // Seuil facturation EAS/MAPI (jours)
                'tblhosting.server  as serverid_raw'  // Clé pour la recherche dans tblserver_tenants
            )
            ->first();

        // Ce service n'utilise pas notre module → passer au suivant
        if (!$service) {
            continue;
        }

        // ── Lecture des paramètres de facturation ─────────────────────────────
        //
        // Ces valeurs sont configurées dans l'onglet "Module Settings" du produit.
        // On utilise des valeurs par défaut raisonnables en cas de valeur manquante.
        $gbPerTier     = max(1, (int) ($service->configoption1 ?: 10));
        $easPrice      = (float) ($service->configoption2 ?: 0);
        $mapiPrice     = (float) ($service->configoption3 ?: 0);
        $combinedPrice = (float) ($service->configoption4 ?: 0);
        // configoption16 : seuil de facturation EAS/MAPI en jours (0 = désactivé → mode live)
        $lockDays      = max(0, (int) ($service->configoption16 ?: 0));

        // ── API SmarterMail : connexion pour la facturation EAS/MAPI ──────────
        //
        // Le disque est lu depuis tbltenant_stats (métriques WHMCS) — pas besoin
        // de l'API pour ça. La connexion ici sert uniquement à récupérer les
        // boîtes EAS/MAPI actives (Phase 2 live fallback).
        // Si la connexion échoue, les lignes EAS/MAPI live sont simplement omises
        // (la Phase 1 proto_usage fonctionne sans connexion API).
        $api = new SmarterMailApi(
            $service->serverhostname,
            (bool) $service->serversecure,
            (int) ($service->serverport ?: ($service->serversecure ? 443 : 80))
        );

        $saToken = null;
        $daToken = null;
        try {
            // ⚠️  DÉCODAGE DU MOT DE PASSE SERVEUR (cohérence avec le reste du module)
            // WHMCS applique parfois une couche de sanitization HTML aux identifiants
            // serveur : un mot de passe SA contenant & < > " ' est alors stocké encodé
            // (ex: « p&ss » → « p&amp;ss »). Le decrypt() brut renvoie cette forme
            // encodée → l'authentification SA échoue silencieusement → pas de token DA
            // (symptôme observé en production : « Token DA absent », alors que le
            // MetricsProvider fonctionne car il applique déjà html_entity_decode via
            // loginSysAdminFromParams). On décode ici de la même façon pour aligner le
            // hook sur le reste du module. html_entity_decode est IDEMPOTENT : c'est un
            // no-op si le mot de passe est déjà brut (aucun risque de double-décodage).
            $saToken = $api->loginSysAdmin(
                $service->serverusername,
                html_entity_decode(
                    (string) decrypt($service->serverpassword),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );
            if ($saToken) {
                $daToken = $api->loginDomainAdmin($saToken, $service->domain);
            }
        } catch (\Exception $e) {
            logActivity('SmarterMail InvoiceCreation [API connexion] EXCEPTION '
                . '(service #' . $serviceId . '): ' . $e->getMessage());
        }

        // ── Utilisation disque — métriques WHMCS (tbltenant_stats) ─────────────
        //
        //   tblserver_tenants.server_id == tblhosting.server  (ID serveur WHMCS)
        //   tblserver_tenants.tenant    == domaine
        //   tblserver_tenants.id        == tbltenant_stats.tenant_id
        //   tbltenant_stats.metric      == 'disk_gb'
        //   tbltenant_stats.value       == espace disque en Go (float)
        $usageGB = 0.0;
        try {
            $tenantRow = Capsule::table('tblserver_tenants')
                ->where('server_id', (int) $service->serverid_raw)
                ->where('tenant',    $service->domain)
                ->first();

            if ($tenantRow) {
                // (P0.3) Lire la dernière métrique disk_gb NON NULLE. On ignore
                // volontairement les zéros : ils sont typiquement écrits par une
                // panne de l'API SmarterMail pendant le cron de métriques
                // (impersonification DA échouée → disk_gb=0). Facturer un gros
                // compte au tarif minimum (1 tranche) sur un 0 transitoire serait
                // une sous-facturation massive et silencieuse. Un domaine réellement
                // vide donne de toute façon 1 tranche (max(1, ceil(0/gb))=1), donc
                // ignorer le 0 est sans effet négatif dans ce cas.
                $metricRow = Capsule::table('tbltenant_stats')
                    ->where('tenant_id', $tenantRow->id)
                    ->where('metric',    'disk_gb')
                    ->where('value',     '>', 0)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($metricRow) {
                    $usageGB = (float) $metricRow->value;
                }
            }
        } catch (\Exception $e) {
            logActivity('SmarterMail InvoiceCreation [disque] EXCEPTION '
                . '(service #' . $serviceId . '): ' . $e->getMessage());
        }

        // (P0.3) Si aucune valeur disque fiable (> 0) n'est disponible, on facture
        // au minimum (1 tranche) mais on le SIGNALE : soit le domaine est réellement
        // vide, soit la collecte de métriques est en panne — dans ce dernier cas
        // l'admin doit intervenir (facture immuable en WHMCS 9).
        if ($usageGB <= 0) {
            logActivity(sprintf(
                'SmarterMail InvoiceCreation [disque] AVERTISSEMENT : aucune métrique '
                . 'disk_gb fiable (> 0) pour service #%d (domaine %s) — ligne disque '
                . 'facturée au minimum (1 tranche). Vérifiez la collecte de métriques '
                . '/ la connexion SmarterMail si le domaine n\'est pas réellement vide.',
                $serviceId, $service->domain ?? ''
            ));
        }

        // ── Calcul des tranches et mise à jour de la ligne principale ─────────
        //
        // FORMULE : tiers = ceil(usageGB / gbPerTier), minimum 1
        // Exemple : 31.50 Go, gbPerTier=10 → ceil(3.15) = 4 tranches → 40 Go facturés
        $tiers         = max(1, (int) ceil($usageGB / $gbPerTier));
        $baseUnitPrice = (float) $item->amount;  // Prix unitaire par tranche (dans WHMCS)
        $newAmount     = round($tiers * $baseUnitPrice, 2);

        // Description : format "{X.XX Go utilisé · N Tranche(s) × Y Go × $Z.ZZ}"
        // Cohérent avec l'affichage dans l'espace client.
        // Le gabarit est lu depuis le fichier de langue via _sm_hookLang() afin
        // de supporter l'anglais et le français canadien selon la langue système.
        // Clé : 'inv_usage_label' — paramètres sprintf : %1$s %2$d %3$d %4$s
        //
        // MISE EN FORME DE LA FACTURE (retour de ligne) :
        //   Le détail d'utilisation apparaît sur une SECONDE ligne, préfixé par
        //   le caractère 'inv_usage_prefix' (par défaut "» ") afin d'améliorer
        //   la lisibilité sur la facture PDF/courriel. Résultat visuel :
        //
        //     Hébergement courriel (SM+) - domaine.ca (2026/05/12 - 2026/06/11)
        //     » 155.58 Go utilisé · 16 Tranche(s) de 10 Go × $6
        //
        //   Le retour de ligne est un "\n" codé en dur (fonctionne dans les
        //   factures WHMCS PDF et HTML). Le préfixe "» " est externalisé dans
        //   les fichiers de langue (clé 'inv_usage_prefix') pour permettre
        //   une personnalisation par langue (ex: "» " en FR, "» " en EN).
        $usageFormatted = number_format($usageGB, 2);
        $hookLang       = _sm_hookLang(); // Chargé une seule fois (cache statique)
        $usageLabel     = sprintf(
            $hookLang['inv_usage_label'] ?? '%1$s Go utilisé · %2$d Tranche(s) × %3$d Go × $%4$s',
            $usageFormatted,   // %1$s — utilisation en Go formatée
            $tiers,            // %2$d — nombre de tranches calculées
            $gbPerTier,        // %3$d — Go par tranche (configoption1)
            $baseUnitPrice     // %4$s — prix unitaire par tranche
        );

        // ── Ligne principale (disque) via l'API UpdateInvoice ─────────────────
        //
        // ⚠️  WHMCS 9.0 — IMMUTABILITÉ DES FACTURES
        // Depuis WHMCS 9.0, les factures hors statut « Draft » sont immuables.
        // Toute écriture DIRECTE dans tblinvoiceitems (INSERT/DELETE via Capsule)
        // pendant la génération n'est plus reprise : WHMCS reconstruit l'ensemble
        // des lignes après le hook et ÉLIMINE les lignes « orphelines »
        // (type='' / relid=0) ajoutées en SQL brut — d'où la disparition
        // silencieuse des suppléments EAS/MAPI constatée après la migration 9.0.
        //
        // SOLUTION : on passe par l'API officielle UpdateInvoice (couche modèle
        // WHMCS, compatible 9.0). Le payload est construit progressivement puis
        // appliqué en UN SEUL appel localAPI() à la fin du traitement du service :
        //   - itemdescription/itemamount/itemtaxed → modifient EN PLACE la ligne
        //     Hosting existante (indexées par son lineItemId = $item->id).
        //     L'édition préserve type='Hosting' et relid=<serviceid>, donc le
        //     renouvellement du service reste correct.
        //   - newitemdescription/newitemamount/newitemtaxed → AJOUTENT les lignes
        //     EAS/MAPI comme items légitimes (WHMCS ne les retire plus).
        //   - WHMCS recalcule le total automatiquement (plus de recalcul manuel).
        //
        // SÉCURITÉ : $item->description provient de WHMCS ; $usageLabel est
        // construit via number_format() et des paramètres numériques castés —
        // aucun risque d'injection.
        $usagePrefix        = $hookLang['inv_usage_prefix'] ?? '» ';
        $updatedDescription = $item->description . "\n" . $usagePrefix . $usageLabel;

        // Statut de taxation hérité de la ligne Hosting d'origine — appliqué à la
        // ligne principale ET aux lignes EAS/MAPI (préserve 'taxed' => $item->taxed).
        $itemTaxed = (bool) $item->taxed;

        // Payload UpdateInvoice. Clés item* indexées par lineItemId ; clés
        // newitem* = tableaux numériques (une entrée par ligne EAS/MAPI ajoutée).
        $update = [
            'invoiceid'          => $invoiceId,
            'itemdescription'    => [(int) $item->id => $updatedDescription],
            'itemamount'         => [(int) $item->id => $newAmount],
            'itemtaxed'          => [(int) $item->id => $itemTaxed],
            'newitemdescription' => [],
            'newitemamount'      => [],
            'newitemtaxed'       => [],
        ];

        // Helper : empile une ligne EAS/MAPI dans le payload newitem*.
        // (Remplace les anciens Capsule::table('tblinvoiceitems')->insert().)
        $addLine = function (string $desc, float $amount) use (&$update, $itemTaxed): void {
            $update['newitemdescription'][] = $desc;
            $update['newitemamount'][]      = round($amount, 2);
            $update['newitemtaxed'][]       = $itemTaxed;
        };

        // ── Facturation EAS/MAPI ─────────────────────────────────────────────
        // $doEasMapi : un prix EAS ou MAPI est-il configuré ? Si non, aucune ligne
        // de supplément n'est ajoutée (la ligne disque est tout de même appliquée
        // plus bas via UpdateInvoice). On NE fait PLUS de « continue » prématuré :
        // toutes les écritures passent désormais par un appel UpdateInvoice unique
        // en fin de boucle — point de sortie unique du traitement du service.
        $doEasMapi          = ($easPrice > 0 || $mapiPrice > 0);
        $billedByProtoUsage = [];  // emails déjà facturés via Phase 1 (anti double-billing)
        // Lignes proto_usage sélectionnées en Phase 1 — hissées ici pour être
        // marquées « facturées » APRÈS le succès de UpdateInvoice (correctif P0.1),
        // et non avant : un échec de l'appel ne doit plus effacer/verrouiller ces
        // lignes sans qu'aucune ligne de facture n'existe (perte de revenu définitive).
        $billableByEmail    = [];
        // Période de facturation courante — hissée pour le marquage + le rollover
        // (P1.1) dans la branche de succès de UpdateInvoice. Réassignée en Phase 1.
        $period             = ['start' => null, 'end' => null];
        // (P2-seed) Protocoles EAS/MAPI facturés en Phase 2 (live) car ABSENTS de
        // proto_usage (boîtes créées directement dans SmarterMail). Matérialisés
        // dans la table après le succès d'UpdateInvoice → le rollover les couvre
        // dès le cycle suivant (fin de la dépendance à l'API live).
        $seededByEmail      = [];

        // ════════════════════════════════════════════════════════════════════
        //  PHASE 1 — SUIVI D'UTILISATION (mod_sm_proto_usage)
        //  Adresses trackées depuis l'installation du suivi d'utilisation.
        //
        //  Phase 1 est 100 % basée sur la base de données (mod_sm_proto_usage) et
        //  NE dépend PAS de la connexion API. Contrairement à l'ancienne version
        //  (qui sautait toute la facturation EAS/MAPI quand $daToken était absent),
        //  les suppléments TRACÉS sont désormais facturés même si le serveur
        //  SmarterMail est momentanément injoignable au moment de la facture.
        // ════════════════════════════════════════════════════════════════════
        if ($doEasMapi && $lockDays >= 1) {
            try {
                $period = _sm_getBillingPeriod($serviceId);

                if ($period['start']) {
                    $billableByEmail = _sm_finalizeAndGetBillable($serviceId, $period['start']);

                    // ── Regrouper par type de protocole ──────────────────────
                    // Une seule ligne de facture par type (combined / eas / mapi)
                    // avec toutes les adresses listées dans la description.
                    // Cela correspond au format demandé :
                    //   EAS + MAPI/Exchange :          $X.XX
                    //      bob@domain.com
                    //      alice@domain.com (Désactivé le 15-jan-26)
                    $combinedEmails = [];
                    $easEmails      = [];
                    $mapiEmails     = [];

                    foreach ($billableByEmail as $protoEmail => $protocols) {
                        $hasEAS  = isset($protocols['eas']);
                        $hasMAPI = isset($protocols['mapi']);

                        // Helper : formatage de l'adresse avec date si deleted.
                        // Si la boîte a été désactivée (status='deleted'), on ajoute
                        // la date de désactivation pour la transparence de la facture.
                        // La chaîne "(Désactivé le ...)" est externalisée dans les
                        // fichiers de langue — clé 'inv_disabled_on' (sprintf, %s = date).
                        $fmtEmail = function (string $addr, string $proto) use ($protocols, $hookLang): string {
                            $row = $protocols[$proto] ?? null;
                            if ($row && $row->status === 'deleted' && $row->deleted_at) {
                                // Clé de langue : 'inv_disabled_on' — %s = date d-M-y
                                $disabledStr = sprintf(
                                    $hookLang['inv_disabled_on'] ?? '(Désactivé le %s)',
                                    date('d-M-y', strtotime($row->deleted_at))
                                );
                                return ($hookLang['inv_entry_prefix'] ?? '- ') . $addr . ' ' . $disabledStr;
                            }
                            // Adresse active : simple préfixe de puce
                            return ($hookLang['inv_entry_prefix'] ?? '- ') . $addr;
                        };

                        if ($hasEAS && $hasMAPI) {
                            $combinedEmails[] = $fmtEmail($protoEmail, 'eas');
                        } elseif ($hasEAS) {
                            $easEmails[] = $fmtEmail($protoEmail, 'eas');
                        } elseif ($hasMAPI) {
                            $mapiEmails[] = $fmtEmail($protoEmail, 'mapi');
                        }

                        // (P1.1) Dédup PAR PROTOCOLE (pas par adresse) : sinon une
                        // adresse facturée pour un seul protocole en Phase 1 masquerait
                        // l'autre protocole en Phase 2 (perte du supplément).
                        if ($hasEAS)  { $billedByProtoUsage[$protoEmail]['eas']  = true; }
                        if ($hasMAPI) { $billedByProtoUsage[$protoEmail]['mapi'] = true; }
                    }

                    // ── Insérer UNE ligne par type, toutes adresses dans la description ──
                    //
                    // Format de la description : en-tête sur la première ligne,
                    // puis une adresse par ligne avec préfixe '- '.
                    // Les en-têtes sont externalisés dans les fichiers de langue :
                    //   'inv_combined_hdr' → EAS + MAPI/Exchange :
                    //   'inv_eas_hdr'      → ActiveSync (EAS) :
                    //   'inv_mapi_hdr'     → MAPI/Exchange :
                    if (!empty($combinedEmails) && $combinedPrice > 0) {
                        $addLine(
                            ($hookLang['inv_combined_hdr'] ?? 'EAS + MAPI/Exchange :')
                                . "\n" . implode("\n", $combinedEmails),
                            count($combinedEmails) * $combinedPrice
                        );
                    } elseif (!empty($combinedEmails)) {
                        // Pas de prix combiné configuré → deux listes séparées
                        $easEmails   = array_merge($easEmails,  $combinedEmails);
                        $mapiEmails  = array_merge($mapiEmails, $combinedEmails);
                    }

                    if (!empty($easEmails) && $easPrice > 0) {
                        $addLine(
                            ($hookLang['inv_eas_hdr'] ?? 'ActiveSync (EAS) :')
                                . "\n" . implode("\n", $easEmails),
                            count($easEmails) * $easPrice
                        );
                    }

                    if (!empty($mapiEmails) && $mapiPrice > 0) {
                        $addLine(
                            ($hookLang['inv_mapi_hdr'] ?? 'MAPI/Exchange :')
                                . "\n" . implode("\n", $mapiEmails),
                            count($mapiEmails) * $mapiPrice
                        );
                    }

                    // NOTE (P0.1) : le marquage « facturé » n'est PLUS fait ici.
                    // Il est déplacé APRÈS le succès de l'appel UpdateInvoice
                    // (plus bas), pour ne jamais marquer/effacer des lignes si la
                    // facture n'a finalement pas été modifiée.
                }

            } catch (\Throwable $e) {
                // Une exception à mi-construction ne doit ni marquer ni facturer
                // des lignes qui ne figureront pas sur la facture (P0.1) : on remet
                // $billableByEmail à vide pour que le marquage post-succès soit sauté.
                $billableByEmail = [];
                logActivity('SmarterMail InvoiceCreation [Phase 1 proto-usage] EXCEPTION '
                    . '(service #' . $serviceId . '): ' . $e->getMessage());
            }
        }

        // ════════════════════════════════════════════════════════════════════
        //  PHASE 2 — FALLBACK LIVE API
        //  Adresses EAS/MAPI actives NON tracées dans mod_sm_proto_usage.
        //  Rétrocompatibilité pour les services antérieurs au suivi.
        //  Nécessite la connexion API (token DA) — sautée si absente (la Phase 1
        //  basée DB a déjà facturé les suppléments tracés dans ce cas).
        // ════════════════════════════════════════════════════════════════════
        if ($doEasMapi && $daToken) {
            try {
                $easMailboxes  = $api->getActiveSyncMailboxes($daToken);
                $mapiMailboxes = $api->getMapiMailboxes($daToken);

                // Filtrer : ignorer les adresses déjà facturées en Phase 1
                $liveEasEmails  = [];
                $liveMapiEmails = [];
                $liveCombined   = [];
                // (P2-seed) Emails bruts (minuscules) en parallèle des listes
                // d'affichage : servent à matérialiser après coup, dans proto_usage,
                // EXACTEMENT les protocoles réellement facturés (prix > 0).
                $rawEas      = [];
                $rawMapi     = [];
                $rawCombined = [];

                $allLiveEmails = array_unique(array_merge(
                    array_keys($easMailboxes),
                    array_keys($mapiMailboxes)
                ));
                $allLiveEmails = array_values(array_filter($allLiveEmails,
                    fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
                ));

                foreach ($allLiveEmails as $liveEmail) {
                    // (P1.1) Normaliser en minuscules : la table proto_usage stocke en
                    // minuscules alors que l'API peut renvoyer une casse mixte — la
                    // comparaison sensible à la casse ratait la dédup (double facturation).
                    $lcEmail = strtolower($liveEmail);
                    // Ne facturer en live QUE les protocoles NON déjà facturés en Phase 1.
                    $hasEAS  = isset($easMailboxes[$liveEmail])  && empty($billedByProtoUsage[$lcEmail]['eas']);
                    $hasMAPI = isset($mapiMailboxes[$liveEmail]) && empty($billedByProtoUsage[$lcEmail]['mapi']);
                    if (!$hasEAS && !$hasMAPI) continue;
                    // Préfixe de puce depuis le fichier de langue ('- ' par défaut)
                    $prefix  = $hookLang['inv_entry_prefix'] ?? '- ';
                    if ($hasEAS && $hasMAPI)  { $liveCombined[]  = $prefix . $liveEmail; $rawCombined[] = $lcEmail; }
                    elseif ($hasEAS)          { $liveEasEmails[] = $prefix . $liveEmail; $rawEas[]      = $lcEmail; }
                    elseif ($hasMAPI)         { $liveMapiEmails[] = $prefix . $liveEmail; $rawMapi[]     = $lcEmail; }
                }

                // Une ligne par type (même format que Phase 1)
                // En-têtes externalisés dans les fichiers de langue (mêmes clés que Phase 1)
                // (P2-seed) $seededByEmail ne retient QUE les protocoles réellement
                // facturés (prix > 0) → on matérialise exactement ce qui a été facturé.
                if (!empty($liveCombined) && $combinedPrice > 0) {
                    $addLine(
                        ($hookLang['inv_combined_hdr'] ?? 'EAS + MAPI/Exchange :')
                            . "\n" . implode("\n", $liveCombined),
                        count($liveCombined) * $combinedPrice
                    );
                    foreach ($rawCombined as $e) { $seededByEmail[$e]['eas'] = true; $seededByEmail[$e]['mapi'] = true; }
                } elseif (!empty($liveCombined)) {
                    $liveEasEmails  = array_merge($liveEasEmails,  $liveCombined);
                    $liveMapiEmails = array_merge($liveMapiEmails, $liveCombined);
                    $rawEas  = array_merge($rawEas,  $rawCombined);
                    $rawMapi = array_merge($rawMapi, $rawCombined);
                }
                if (!empty($liveEasEmails) && $easPrice > 0) {
                    $addLine(
                        ($hookLang['inv_eas_hdr'] ?? 'ActiveSync (EAS) :')
                            . "\n" . implode("\n", $liveEasEmails),
                        count($liveEasEmails) * $easPrice
                    );
                    foreach ($rawEas as $e) { $seededByEmail[$e]['eas'] = true; }
                }
                if (!empty($liveMapiEmails) && $mapiPrice > 0) {
                    $addLine(
                        ($hookLang['inv_mapi_hdr'] ?? 'MAPI/Exchange :')
                            . "\n" . implode("\n", $liveMapiEmails),
                        count($liveMapiEmails) * $mapiPrice
                    );
                    foreach ($rawMapi as $e) { $seededByEmail[$e]['mapi'] = true; }
                }

            } catch (\Exception $e) {
                logActivity('SmarterMail InvoiceCreation [Phase 2 live-api] EXCEPTION '
                    . '(service #' . $serviceId . ', domaine: ' . ($service->domain ?? '') . '): '
                    . $e->getMessage());
            }
        } elseif ($doEasMapi) {
            // Token DA absent : seule la Phase 2 (live) est ignorée. La Phase 1
            // (proto_usage, basée DB) a déjà pu facturer les suppléments tracés.
            logActivity('SmarterMail InvoiceCreation: Token DA absent — '
                . 'Phase 2 live ignorée, Phase 1 proto_usage appliquée '
                . '(service #' . $serviceId . ').');
        }

        // ── Application du payload via l'API officielle UpdateInvoice ─────────
        // UN SEUL appel par service : modifie la ligne disque EN PLACE et ajoute
        // les lignes EAS/MAPI accumulées. WHMCS recalcule le total automatiquement.
        //
        // On exécute localAPI avec un compte admin valide et on journalise le
        // résultat (succès ET échec) : aucune défaillance silencieuse possible —
        // contrairement à l'écriture SQL brute, on saura toujours si la facture a
        // bien été modifiée (vérifiable dans Configuration → Journal d'activité).
        $nbNew     = count($update['newitemdescription']);
        $adminUser = _sm_getAdminUsername();

        if ($adminUser === '') {
            logActivity('SmarterMail InvoiceCreation [UpdateInvoice] ABANDON : '
                . 'aucun administrateur actif trouvé pour exécuter localAPI '
                . '(facture #' . $invoiceId . ', service #' . $serviceId . ').');
        } else {
            $apiRes = localAPI('UpdateInvoice', $update, $adminUser);
            if (($apiRes['result'] ?? '') === 'success') {
                logActivity(sprintf(
                    'SmarterMail InvoiceCreation [UpdateInvoice] OK : facture #%d, '
                        . 'service #%d — ligne disque mise à jour + %d ligne(s) EAS/MAPI ajoutée(s).',
                    $invoiceId, $serviceId, $nbNew
                ));

                // ── P0.1 — Marquage « facturé » APRÈS confirmation du succès ─────
                // Avant ce correctif, _sm_markEntriesAsBilled() était appelé en
                // Phase 1 AVANT UpdateInvoice : il pose billed=1 et EFFACE les lignes
                // status='deleted'. Si l'appel échouait ensuite, les suppléments
                // EAS/MAPI de la période disparaissaient sans qu'aucune ligne de
                // facture n'existe (perte de revenu irréversible et quasi silencieuse).
                // On ne marque donc QUE dans cette branche de succès confirmé. En cas
                // d'échec (else) ou d'admin absent, rien n'est marqué : les lignes
                // restent billed=0 (reprise couverte par la Phase 1.1 — rollover +
                // filtre period_start <=).
                // (P1.1) Marquage + rollover dans UNE transaction : un crash entre
                // les deux ne peut pas laisser des lignes billed=1 sans descendance
                // pour la période suivante. Le rollover reporte les lignes
                // grace/active à period_end (billed=0) → la Phase 1 (DB) couvre les
                // renouvellements sans dépendre de l'API live.
                if (!empty($billableByEmail) && $period['start']) {
                    // Pas de rollover pour les cycles non renouvelables (One Time) :
                    // les lignes reportées ne seraient jamais facturées.
                    $cycle      = strtolower(trim((string) (Capsule::table('tblhosting')
                        ->where('id', $serviceId)->value('billingcycle') ?? '')));
                    $doRollover = !empty($period['end'])
                        && !in_array($cycle, ['one time', 'onetime', 'free account'], true);
                    try {
                        Capsule::connection()->transaction(
                            function () use ($serviceId, $billableByEmail, $invoiceId, $period, $doRollover) {
                                _sm_markEntriesAsBilled($billableByEmail, $invoiceId, $period['start']);
                                if ($doRollover) {
                                    _sm_rolloverProtoUsage($serviceId, $billableByEmail, $period['end']);
                                }
                            }
                        );
                    } catch (\Throwable $e) {
                        logActivity('SmarterMail InvoiceCreation [mark+rollover] EXCEPTION '
                            . '(facture #' . $invoiceId . ', service #' . $serviceId . '): '
                            . $e->getMessage());
                    }
                }

                // ── (P2-seed) Matérialisation des protocoles facturés en Phase 2 ──
                // Les protocoles EAS/MAPI facturés en live (boîtes créées hors module,
                // absentes de proto_usage) sont inscrits dans la table : ligne période
                // courante billed=1 (trace + annulable) + ligne période suivante billed=0
                // (rollover). Dès le prochain cycle, la Phase 1 (DB) les facture sans
                // l'API live. Transaction SÉPARÉE du marquage Phase 1 ci-dessus : un
                // échec de seeding ne doit pas annuler le marquage déjà appliqué.
                // Ne s'exécute que si $period['start'] est connu — donc jamais en
                // « mode live » (lockDays=0, Phase 1 désactivée), où le suivi DB est
                // volontairement inactif.
                if (!empty($seededByEmail) && $period['start']) {
                    $seedCycle     = strtolower(trim((string) (Capsule::table('tblhosting')
                        ->where('id', $serviceId)->value('billingcycle') ?? '')));
                    $seedRollover  = !empty($period['end'])
                        && !in_array($seedCycle, ['one time', 'onetime', 'free account'], true);
                    $seedThreshold = max(1, $lockDays) * 24;
                    try {
                        $nbSeeded = Capsule::connection()->transaction(
                            function () use ($serviceId, $seededByEmail, $invoiceId, $period, $seedRollover, $seedThreshold) {
                                return _sm_seedLiveProtoUsage(
                                    $serviceId,
                                    $seededByEmail,
                                    $invoiceId,
                                    $period['start'],
                                    $seedRollover ? $period['end'] : null,
                                    $seedThreshold
                                );
                            }
                        );
                        if ($nbSeeded > 0) {
                            logActivity(sprintf(
                                'SmarterMail InvoiceCreation [P2-seed] service #%d : %d protocole(s) '
                                    . 'live matérialisé(s) dans proto_usage — désormais couverts par '
                                    . 'la Phase 1 (DB) + rollover, sans dépendre de l\'API live.',
                                $serviceId, $nbSeeded
                            ));
                        }
                    } catch (\Throwable $e) {
                        logActivity('SmarterMail InvoiceCreation [P2-seed] EXCEPTION '
                            . '(facture #' . $invoiceId . ', service #' . $serviceId . '): '
                            . $e->getMessage());
                    }
                }
            } else {
                logActivity('SmarterMail InvoiceCreation [UpdateInvoice] ÉCHEC '
                    . '(facture #' . $invoiceId . ', service #' . $serviceId . ') : '
                    . ($apiRes['message'] ?? json_encode($apiRes))
                    . ' — AUCUN marquage proto_usage effectué (suppléments non perdus, '
                    . 'à reprendre au prochain cycle). ⚠ Vérification admin recommandée.');
            }
        }

      } catch (\Throwable $e) {
            // Catch global : toute exception non couverte est loguée sans crasher le cron
            logActivity('SmarterMail InvoiceCreation [ERREUR INATTENDUE] service #'
                . (isset($serviceId) ? $serviceId : '?') . ' : ' . $e->getMessage()
                . ' — ' . $e->getFile() . ':' . $e->getLine());
      }
    } // fin foreach $items
});


// =============================================================================
//  HOOK : InvoiceCancelled — Rendre les suppléments EAS/MAPI à nouveau facturables
// =============================================================================
//
//  (P1.1) Quand une facture est annulée, les lignes mod_sm_proto_usage qu'elle avait
//  réglées (billed=1 + invoiceid) sont remises facturables (billed=0). Grâce au
//  filtre period_start <= de _sm_finalizeAndGetBillable, elles seront reprises sur la
//  facture régénérée (ou la suivante), dates de désactivation comprises. Les lignes
//  reportées à la période suivante (rollover) ne sont PAS touchées : elles tracent
//  un usage réel en cours.
//
//  Ce hook n'écrit QUE dans mod_sm_proto_usage (aucune écriture tblinvoice*) →
//  compatible avec l'immutabilité des factures WHMCS 9.
// =============================================================================
add_hook('InvoiceCancelled', 1, function (array $vars) {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if (!$invoiceId) return;

    _sm_ensureProtoUsageTable();
    try {
        $n = Capsule::table('mod_sm_proto_usage')
            ->where('invoiceid', $invoiceId)
            ->update([
                'billed'    => 0,
                'invoiceid' => null,
                'billed_at' => null,
            ]);
        if ($n > 0) {
            logActivity(sprintf(
                'SmarterMail [InvoiceCancelled] Facture #%d annulée : %d ligne(s) EAS/MAPI '
                . 'remise(s) en facturable — reprise sur la prochaine facture du service.',
                $invoiceId, $n
            ));
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail [InvoiceCancelled] EXCEPTION (facture #' . $invoiceId
            . ') : ' . $e->getMessage());
    }
});


// =============================================================================
//  NOTE — Recalcul du total de la facture (fonction retirée)
// =============================================================================
//
//  L'ancienne fonction _sm_recalculerTotalFacture() additionnait les lignes et
//  écrivait DIRECTEMENT dans tblinvoices.total après des INSERT SQL bruts.
//  Cette approche est INCOMPATIBLE avec l'immutabilité des factures de
//  WHMCS 9.0+ et a été supprimée : le hook InvoiceCreation passe désormais par
//  localAPI('UpdateInvoice'), qui recalcule le total lui-même via la couche
//  modèle de WHMCS.
//
//  ⚠️  Ne PAS réintroduire d'écriture directe dans tblinvoices / tblinvoiceitems
//      pendant la génération de factures — utiliser l'API UpdateInvoice.
// =============================================================================


// =============================================================================
//  HOOK : DailyCronJob — Tâches quotidiennes
// =============================================================================

/**
 * Hook déclenché une fois par jour par le cron WHMCS.
 *
 * Le cron WHMCS s'exécute via la commande :
 *   php -q /path/to/whmcs/crons/cron.php
 *
 * Ce hook est déclenché dans le cadre de ce cron, après les tâches système.
 * Il est disponible pour des tâches de maintenance ou d'alerte supplémentaires.
 *
 * UTILISATION ACTUELLE :
 *   Squelette — extensible selon les besoins futurs.
 *
 * UTILISATIONS FUTURES PRÉVUES :
 *   - Alertes de dépassement : envoyer un courriel si l'utilisation dépasse X%
 *     de la tranche en cours (ex: alerte à 90% pour avertir le client)
 *   - Rapport mensuel : générer un résumé de l'utilisation par domaine
 *   - Nettoyage : archiver ou supprimer des données de log obsolètes
 *   - Synchronisation : vérifier la cohérence entre WHMCS et SmarterMail
 *     (domaines actifs dans WHMCS mais pas dans SmarterMail, ou vice versa)
 *
 * NOTE : WHMCS appelle DÉJÀ smartermail_UsageUpdate() via le cron standard
 * pour mettre à jour l'utilisation disque. Ce hook est COMPLÉMENTAIRE.
 *
 * @param array $params Paramètres fournis par WHMCS (vide pour DailyCronJob)
 */
add_hook('DailyCronJob', 1, function (array $params) {

    // ── Transition grace → active ─────────────────────────────────────────────
    // Parcourt toutes les lignes mod_sm_proto_usage en status=grace et les
    // fait passer à active quand le seuil de threshold_hours est atteint.
    // Cela garantit que l'état affiché dans l'espace client est toujours à jour
    // même si l'utilisateur ne modifie pas ses paramètres.
    try {
        _sm_transitionGraceToActive(); // 0 = tous les services
    } catch (\Throwable $e) {
        logActivity('SmarterMail DailyCronJob [transitionGrace] EXCEPTION: ' . $e->getMessage());
    }

    // ── Nettoyage hebdomadaire de mod_sm_proto_usage ──────────────────────────
    // Supprime les enregistrements EAS/MAPI liés à des services annulés,
    // résiliés ou marqués comme fraude. Roulé chaque dimanche (day = 0)
    // pour éviter une exécution quotidienne inutile.
    //
    // Pour forcer une exécution manuelle : bouton "Nettoyage Proto Usage"
    // dans Admin → Clients → [client] → Services → [service] → Module Commands.
    if ((int) date('w') === 0) { // 0 = dimanche
        try {
            $result = _sm_cleanProtoUsage(0); // 0 = tous les services
            if ($result['deleted'] > 0) {
                logActivity(sprintf(
                    'SmarterMail DailyCronJob [cleanProtoUsage] %d enregistrement(s) supprimé(s) pour %d service(s) inactif(s).',
                    $result['deleted'],
                    $result['services']
                ));
            }
        } catch (\Throwable $e) {
            logActivity('SmarterMail DailyCronJob [cleanProtoUsage] EXCEPTION: ' . $e->getMessage());
        }

        // ── (P1.1) Purge différée des lignes proto_usage facturées trop vieilles ──
        // Le rollover fait croître la table d'un jeu de lignes par période ; on
        // supprime les lignes billed=1 des périodes passées après ~90 jours (fenêtre
        // d'annulation de facture préservée, affichage de la période courante intact).
        try {
            $purgedProto = _sm_purgeBilledProtoUsage(90);
            if ($purgedProto > 0) {
                logActivity(sprintf(
                    'SmarterMail DailyCronJob [purgeBilledProtoUsage] %d ligne(s) proto_usage '
                    . 'facturée(s) et ancienne(s) purgée(s).',
                    $purgedProto
                ));
            }
        } catch (\Throwable $e) {
            logActivity('SmarterMail DailyCronJob [purgeBilledProtoUsage] EXCEPTION: ' . $e->getMessage());
        }

        // ── Nettoyage du cache DNS — Phase 1 : par service inactif ────────
        //
        // Pour chaque service avec domainstatus IN ('Cancelled','Fraud',
        // 'Terminated'), purge les entrées DNS qui pourraient avoir été
        // créées avant ou après la résiliation. La purge à TerminateAccount
        // gère déjà ce cas en immédiat ; cette boucle est un filet de
        // sécurité pour les services Cancelled (jamais activement résiliés
        // par le module) ou si TerminateAccount a échoué partiellement.
        try {
            // On lit aussi tblhosting.server pour ne purger QUE les domaines
            // de services pilotés par le module SmarterMail (sinon on
            // pourrait effacer du cache DNS d'autres modules — improbable,
            // car la table mod_sm_dns_cache est dédiée, mais bonne pratique).
            $inactiveServices = Capsule::table('tblhosting')
                ->whereIn('domainstatus', ['Cancelled', 'Fraud', 'Terminated'])
                ->whereNotNull('domain')
                ->where('domain', '!=', '')
                ->select('id', 'domain')
                ->get();

            $totalPurged = 0;
            $domainsTouched = 0;
            foreach ($inactiveServices as $svc) {
                $purged = _sm_purgeDnsCacheForDomain((string) $svc->domain);
                if ($purged > 0) {
                    $totalPurged += $purged;
                    $domainsTouched++;
                }
            }
            if ($totalPurged > 0) {
                logActivity(sprintf(
                    'SmarterMail DailyCronJob [cleanDnsCache:inactive] %d entrée(s) purgée(s) pour %d domaine(s) inactif(s).',
                    $totalPurged,
                    $domainsTouched
                ));
            }
        } catch (\Throwable $e) {
            logActivity('SmarterMail DailyCronJob [cleanDnsCache:inactive] EXCEPTION: ' . $e->getMessage());
        }

        // ── Nettoyage du cache DNS — Phase 2 : entrées trop vieilles ──────
        //
        // Filet de sécurité ultime : supprime les entrées dont le
        // cached_at est plus vieux que 7 jours, peu importe le statut du
        // service. Le TTL utilisé par le module est de 4 h donc 7 jours =
        // 42× le TTL → aucune entrée encore valide n'est jamais effacée.
        // Empêche la table de gonfler indéfiniment si un domaine a été
        // supprimé hors module (intervention DB manuelle, par exemple).
        try {
            $purged = _sm_cleanDnsCacheStale(7 * 86400); // 7 jours
            if ($purged > 0) {
                logActivity(sprintf(
                    'SmarterMail DailyCronJob [cleanDnsCache:stale] %d entrée(s) DNS périmée(s) supprimée(s).',
                    $purged
                ));
            }
        } catch (\Throwable $e) {
            logActivity('SmarterMail DailyCronJob [cleanDnsCache:stale] EXCEPTION: ' . $e->getMessage());
        }
    }

});



// =============================================================================
//  MASQUER "MODIFIER LE MOT DE PASSE" DANS LE MENU ACTIONS (SIDEBAR)
// =============================================================================
//
//  CONTEXTE :
//  Le lien "Modifier le mot de passe" dans le menu Actions de l'espace client
//  appelle smartermail_ChangePassword(), qui modifie le mot de passe du compte
//  Domain Admin INTERNE de SmarterMail ($params['username'] dans WHMCS).
//
//  Ce compte est un compte de SERVICE utilisé par le module pour se connecter
//  à l'API SmarterMail. Si un client le modifie, le module perd l'accès au
//  domaine et plus rien ne fonctionne (provisionnement, liste comptes, etc.).
//
//  POURQUOI ClientAreaPage ne suffisait pas :
//  Dans les versions récentes de WHMCS, le menu "Actions" de la sidebar est
//  construit via le système de menus orienté objet (\WHMCS\View\Menu\Item),
//  PAS via des variables Smarty $changepw. Retourner ['changepw' => false]
//  depuis ClientAreaPage n'affecte plus le menu — il est déjà construit.
//
//  SOLUTION — ClientAreaPrimarySidebar :
//  Ce hook reçoit l'objet racine du sidebar déjà construit. On peut y naviguer
//  et supprimer l'enfant "Change Password" depuis le groupe "Actions" du service.
//
//  NAVIGATION DANS L'ARBRE DE MENU :
//  PrimarySidebar
//   └── "Service Actions" (clé variable selon le thème)
//        └── "changepassword" ← l'élément à supprimer
//
//  PORTÉE :
//  Le hook s'exécute sur TOUTES les pages client mais ne modifie le menu que
//  si on est sur la page productdetails d'un service SmarterMail.
//
//  SÉCURITÉ :
//  - serviceid casté (int) — aucune injection SQL possible
//  - Requête DB via Capsule avec paramètres liés (query builder)
//  - La fonction ChangePassword reste définie pour l'admin WHMCS
//  - removeChild() est sans effet si l'élément n'existe pas → aucun risque
add_hook('ClientAreaPrimarySidebar', 1, function ($primarySidebar): void {

    // ── Filtre 1 : page productdetails uniquement ─────────────────────────
    // On lit l'action depuis $_GET — ClientAreaPrimarySidebar ne reçoit pas
    // les variables de page comme $vars dans ClientAreaPage.
    if (($_GET['action'] ?? '') !== 'productdetails') {
        return;
    }

    // ── Filtre 2 : service ID valide ──────────────────────────────────────
    $serviceId = (int) ($_GET['id'] ?? 0);
    if ($serviceId <= 0) {
        return;
    }

    // ── Filtre 3 : confirmer que le service utilise le module SmarterMail ─
    // SELECT 1 LIMIT 1 — minimal, ne charge que la clé primaire.
    try {
        $isSmarterMail = \WHMCS\Database\Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.id', $serviceId)
            ->where('tblproducts.servertype', 'smartermail')
            ->exists();
    } catch (\Throwable $e) {
        // Erreur DB non bloquante — on laisse le menu intact par sécurité
        logActivity('SmarterMail [hook-sidebar-changepw] Erreur DB service #'
            . $serviceId . ' : ' . $e->getMessage());
        return;
    }

    if (!$isSmarterMail) {
        return;
    }

    // ── Supprimer l'élément "Change Password" du sidebar ──────────────────
    if (!is_null($primarySidebar->getChild('Service Details Actions'))) {
    $primarySidebar->getChild('Service Details Actions')
    ->removeChild('Change Password');
    }
});


// =============================================================================
//  INJECTION CSS — Mode sombre Lagom + fix <code>
// =============================================================================
//
//  CONTEXTE :
//  Les overrides CSS (dark mode + fix de la couleur rouge du tag <code>
//  appliquée par certains thèmes) sont stockés dans un fichier unique :
//      templates/_sm_dark_mode.css
//
//  Le hook ClientAreaHeadOutput injecte ce CSS dans le <head> de toutes
//  les pages clientarea concernant un service SmarterMail. Avantages :
//    - Source UNIQUE pour les 5 templates du module (clientarea, adduser,
//      edituser, addredirect, editredirect) → maintenance simplifiée.
//    - Aucun chemin Smarty fragile : on lit le fichier directement en PHP.
//    - Le CSS est injecté AVANT le rendu des <style> inline des templates,
//      donc le mode sombre s'applique correctement (le browser cascade
//      naturellement le dernier sélecteur applicable).
//
//  CONDITIONS D'INJECTION :
//    - On est sur clientarea.php?action=productdetails
//    - Le service ciblé (id) utilise le module 'smartermail' (vérifié via
//      tblhosting JOIN tblservers)
//  → Aucune CSS n'est ajoutée sur les pages d'autres modules.
//
//  CACHE :
//    Le contenu CSS est lu une seule fois par requête PHP via cache statique.
//    Pas de cache disque/mémoire persistant — file_get_contents est rapide
//    et OPcache prend déjà le fichier en charge implicitement sur la
//    plupart des installs.
// =============================================================================

/**
 * Retourne le contenu du fichier CSS dark mode partagé.
 * Cache statique par exécution PHP — évite plusieurs file_get_contents
 * si le hook est appelé plusieurs fois (rare mais théoriquement possible).
 */
function _sm_loadDarkModeCss(): string
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = realpath(__DIR__ . '/templates/_sm_dark_mode.css');
    if ($path && is_readable($path)) {
        $cache = (string) file_get_contents($path);
    } else {
        $cache = '';
        logActivity('SmarterMail [dark-mode] Fichier introuvable : ' . __DIR__ . '/templates/_sm_dark_mode.css');
    }
    return $cache;
}

add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    // Filtre : on n'agit que sur la page productdetails d'un service.
    // $_GET est utilisé directement car $vars ne contient pas systématiquement
    // l'action/id selon la version WHMCS — plus fiable.
    if (($_GET['action'] ?? '') !== 'productdetails') return '';

    $sid = (int) ($_GET['id'] ?? 0);
    if ($sid <= 0) return '';

    // Vérification que le service utilise notre module — évite d'injecter
    // notre CSS sur les services d'autres modules (cPanel, Plesk, etc.).
    try {
        $serverType = Capsule::table('tblhosting')
            ->join('tblservers', 'tblhosting.server', '=', 'tblservers.id')
            ->where('tblhosting.id', $sid)
            ->value('tblservers.type');
        if ($serverType !== 'smartermail') return '';
    } catch (\Throwable $e) {
        // Erreur DB non bloquante → on n'injecte rien (sûr par défaut)
        return '';
    }

    $css = _sm_loadDarkModeCss();
    if ($css === '') return '';

    // L'ID sm-dark-mode-shared permet d'identifier le bloc dans les
    // devtools et empêche d'éventuelles doubles injections (les hooks
    // WHMCS sont normalement appelés une seule fois par page).
    return '<style id="sm-dark-mode-shared">' . $css . '</style>';
});
