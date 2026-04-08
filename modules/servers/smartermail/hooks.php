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
 *    Hébergement courriel (client.com) (21.00 Go utilisés sur 30 Go facturés) ... 18.00$
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
            $saToken = $api->loginSysAdmin(
                $service->serverusername,
                decrypt($service->serverpassword)
            );
            if ($saToken) {
                $daToken = $api->loginDomainAdmin($saToken, $service->domain);
            }
        } catch (\Exception $e) {
            logActivity('SmarterMail InvoiceCreated [API connexion] EXCEPTION '
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
                $metricRow = Capsule::table('tbltenant_stats')
                    ->where('tenant_id', $tenantRow->id)
                    ->where('metric',    'disk_gb')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($metricRow) {
                    $usageGB = (float) $metricRow->value;
                }
            }
        } catch (\Exception $e) {
            logActivity('SmarterMail InvoiceCreated [disk] EXCEPTION '
                . '(service #' . $serviceId . '): ' . $e->getMessage());
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
        $usageFormatted = number_format($usageGB, 2);
        $hookLang       = _sm_hookLang(); // Chargé une seule fois (cache statique)
        $usageLabel     = sprintf(
            $hookLang['inv_usage_label'] ?? '%1$s Go utilisé · %2$d Tranche(s) × %3$d Go × $%4$s',
            $usageFormatted,   // %1$s — utilisation en Go formatée
            $tiers,            // %2$d — nombre de tranches calculées
            $gbPerTier,        // %3$d — Go par tranche (configoption1)
            $baseUnitPrice     // %4$s — prix unitaire par tranche
        );

        // ── Mise à jour et repositionnement de la ligne principale ─────────
        //
        // Au lieu d'un simple UPDATE (qui garde le même id et donc la même position),
        // on supprime la ligne et on la réinsère avec le nouvel AUTO_INCREMENT.
        // Cela garantit que la ligne principale obtient un id > toutes les autres
        // lignes déjà présentes (autres produits, domaines, etc.) et que les lignes
        // EAS/MAPI insérées JUSTE APRÈS auront des ids encore supérieurs.
        //
        // Résultat sans aucune détection d'ordre ni renumérisation :
        //   [autres produits existants]   ← ids inchangés
        //   [courriel] ← DELETE + INSERT  ← nouvel id élevé
        //   [EAS/MAPI] ← INSERT           ← ids encore plus élevés
        $updatedDescription = $item->description . ' (' . $usageLabel . ')';

        // Supprimer l'ancienne ligne puis réinsérer avec les nouvelles valeurs
        Capsule::table('tblinvoiceitems')->where('id', $item->id)->delete();
        Capsule::table('tblinvoiceitems')->insert([
            'invoiceid'   => $item->invoiceid,
            'type'        => $item->type,
            'relid'       => $item->relid,
            'description' => $updatedDescription,
            'amount'      => $newAmount,
            'taxed'       => $item->taxed,
            'duedate'     => $item->duedate,
        ]);
        // $item->id n'est plus valide après le DELETE — on n'en a plus besoin.

        // ── Facturation EAS/MAPI ─────────────────────────────────────────────
        if ($easPrice <= 0 && $mapiPrice <= 0) {
            _sm_recalculerTotalFacture($invoiceId);
            continue;
        }

        if (!$daToken) {
            logActivity('SmarterMail InvoiceCreated: Token DA absent — '
                . 'lignes EAS/MAPI omises (service #' . $serviceId . ').');
            _sm_recalculerTotalFacture($invoiceId);
            continue;
        }

        $dueDate            = $item->duedate;  // Déjà disponible depuis $item
        $billedByProtoUsage = [];              // emails déjà facturés via Phase 1 (anti double-billing)

        // ════════════════════════════════════════════════════════════════════
        //  PHASE 1 — SUIVI D'UTILISATION (mod_sm_proto_usage)
        //  Adresses trackées depuis l'installation du suivi d'utilisation.
        // ════════════════════════════════════════════════════════════════════
        if ($lockDays >= 1) {
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

                        $billedByProtoUsage[$protoEmail] = true;
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
                        $count = count($combinedEmails);
                        $desc  = ($hookLang['inv_combined_hdr'] ?? 'EAS + MAPI/Exchange :')
                                 . "\n" . implode("\n", $combinedEmails);
                        Capsule::table('tblinvoiceitems')->insert([
                            'invoiceid'   => $invoiceId,
                            'type'        => '',     // Pas de type Hosting : évite le renouvellement multiple du service
                            'relid'       => 0,       // relid=0 : non lié au service, pas de renouvellement
                            'description' => $desc,
                            'amount'      => round($count * $combinedPrice, 2),
                            'taxed'       => $item->taxed,
                            'duedate'     => $dueDate,
                        ]);
                    } elseif (!empty($combinedEmails)) {
                        // Pas de prix combiné configuré → deux listes séparées
                        $easEmails   = array_merge($easEmails,  $combinedEmails);
                        $mapiEmails  = array_merge($mapiEmails, $combinedEmails);
                    }

                    if (!empty($easEmails) && $easPrice > 0) {
                        $count = count($easEmails);
                        $desc  = ($hookLang['inv_eas_hdr'] ?? 'ActiveSync (EAS) :')
                                 . "\n" . implode("\n", $easEmails);
                        Capsule::table('tblinvoiceitems')->insert([
                            'invoiceid'   => $invoiceId,
                            'type'        => '',     // Pas de type Hosting : évite le renouvellement multiple du service
                            'relid'       => 0,       // relid=0 : non lié au service, pas de renouvellement
                            'description' => $desc,
                            'amount'      => round($count * $easPrice, 2),
                            'taxed'       => $item->taxed,
                            'duedate'     => $dueDate,
                        ]);
                    }

                    if (!empty($mapiEmails) && $mapiPrice > 0) {
                        $count = count($mapiEmails);
                        $desc  = ($hookLang['inv_mapi_hdr'] ?? 'MAPI/Exchange :')
                                 . "\n" . implode("\n", $mapiEmails);
                        Capsule::table('tblinvoiceitems')->insert([
                            'invoiceid'   => $invoiceId,
                            'type'        => '',     // Pas de type Hosting : évite le renouvellement multiple du service
                            'relid'       => 0,       // relid=0 : non lié au service, pas de renouvellement
                            'description' => $desc,
                            'amount'      => round($count * $mapiPrice, 2),
                            'taxed'       => $item->taxed,
                            'duedate'     => $dueDate,
                        ]);
                    }

                    _sm_markEntriesAsBilled($billableByEmail);
                }

            } catch (\Throwable $e) {
                logActivity('SmarterMail InvoiceCreated [Phase 1 proto-usage] EXCEPTION '
                    . '(service #' . $serviceId . '): ' . $e->getMessage());
            }
        }

        // ════════════════════════════════════════════════════════════════════
        //  PHASE 2 — FALLBACK LIVE API
        //  Adresses EAS/MAPI actives NON tracées dans mod_sm_proto_usage.
        //  Rétrocompatibilité pour les services antérieurs au suivi.
        // ════════════════════════════════════════════════════════════════════
        try {
            $easMailboxes  = $api->getActiveSyncMailboxes($daToken);
            $mapiMailboxes = $api->getMapiMailboxes($daToken);

            // Filtrer : ignorer les adresses déjà facturées en Phase 1
            $liveEasEmails  = [];
            $liveMapiEmails = [];
            $liveCombined   = [];

            $allLiveEmails = array_unique(array_merge(
                array_keys($easMailboxes),
                array_keys($mapiMailboxes)
            ));
            $allLiveEmails = array_values(array_filter($allLiveEmails,
                fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
            ));

            foreach ($allLiveEmails as $liveEmail) {
                if (isset($billedByProtoUsage[$liveEmail])) continue;
                $hasEAS  = isset($easMailboxes[$liveEmail]);
                $hasMAPI = isset($mapiMailboxes[$liveEmail]);
                // Préfixe de puce depuis le fichier de langue ('- ' par défaut)
                $prefix  = $hookLang['inv_entry_prefix'] ?? '- ';
                if ($hasEAS && $hasMAPI)  $liveCombined[]  = $prefix . $liveEmail;
                elseif ($hasEAS)          $liveEasEmails[] = $prefix . $liveEmail;
                elseif ($hasMAPI)         $liveMapiEmails[] = $prefix . $liveEmail;
            }

            // Une ligne par type (même format que Phase 1)
            // En-têtes externalisés dans les fichiers de langue (même clés que Phase 1)
            if (!empty($liveCombined) && $combinedPrice > 0) {
                Capsule::table('tblinvoiceitems')->insert([
                    'invoiceid'   => $invoiceId, 'type' => '', 'relid' => 0,
                    'description' => ($hookLang['inv_combined_hdr'] ?? 'EAS + MAPI/Exchange :')
                                     . "\n" . implode("\n", $liveCombined),
                    'amount'      => round(count($liveCombined) * $combinedPrice, 2),
                    'taxed'       => $item->taxed, 'duedate' => $dueDate,
                ]);
            } elseif (!empty($liveCombined)) {
                $liveEasEmails  = array_merge($liveEasEmails,  $liveCombined);
                $liveMapiEmails = array_merge($liveMapiEmails, $liveCombined);
            }
            if (!empty($liveEasEmails) && $easPrice > 0) {
                Capsule::table('tblinvoiceitems')->insert([
                    'invoiceid'   => $invoiceId, 'type' => '', 'relid' => 0,
                    'description' => ($hookLang['inv_eas_hdr'] ?? 'ActiveSync (EAS) :')
                                     . "\n" . implode("\n", $liveEasEmails),
                    'amount'      => round(count($liveEasEmails) * $easPrice, 2),
                    'taxed'       => $item->taxed, 'duedate' => $dueDate,
                ]);
            }
            if (!empty($liveMapiEmails) && $mapiPrice > 0) {
                Capsule::table('tblinvoiceitems')->insert([
                    'invoiceid'   => $invoiceId, 'type' => '', 'relid' => 0,
                    'description' => ($hookLang['inv_mapi_hdr'] ?? 'MAPI/Exchange :')
                                     . "\n" . implode("\n", $liveMapiEmails),
                    'amount'      => round(count($liveMapiEmails) * $mapiPrice, 2),
                    'taxed'       => $item->taxed, 'duedate' => $dueDate,
                ]);
            }

        } catch (\Exception $e) {
            logActivity('SmarterMail InvoiceCreated [Phase 2 live-api] EXCEPTION '
                . '(service #' . $serviceId . ', domaine: ' . ($service->domain ?? '') . '): '
                . $e->getMessage());
        }

        _sm_recalculerTotalFacture($invoiceId);

      } catch (\Throwable $e) {
            // Catch global : toute exception non couverte est loguée sans crasher le cron
            logActivity('SmarterMail InvoiceCreation [ERREUR INATTENDUE] service #'
                . (isset($serviceId) ? $serviceId : '?') . ' : ' . $e->getMessage()
                . ' — ' . $e->getFile() . ':' . $e->getLine());
      }
    } // fin foreach $items
});


// =============================================================================
//  FONCTION UTILITAIRE : Recalcul du total de la facture
// =============================================================================

/**
 * Recalcule et met à jour le total d'une facture dans WHMCS.
 *
 * Cette fonction est nécessaire car WHMCS ne recalcule pas automatiquement
 * tblinvoices.total lorsqu'on insère des lignes directement dans tblinvoiceitems
 * via Capsule (contrairement à l'API LocalAPI AddInvoicePayment qui le ferait).
 *
 * MÉTHODE :
 *   Additionne le champ 'amount' de TOUTES les lignes de la facture et
 *   met à jour tblinvoices.total avec cette somme arrondie à 2 décimales.
 *
 * NOTES :
 *   - Les lignes avec amount négatif (remises, crédits) sont incluses → correct
 *   - Les taxes ne sont PAS recalculées ici (tblinvoices.taxrate, tax2rate, etc.)
 *     Si votre configuration utilise des taxes, vérifiez la logique de TVA
 *     et envisagez d'utiliser localAPI('UpdateInvoice', ...) à la place.
 *
 * @param int $invoiceId ID de la facture (tblinvoices.id) à recalculer
 */
function _sm_recalculerTotalFacture(int $invoiceId): void
{
    // Sommer tous les montants des lignes de cette facture
    $newTotal = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->sum('amount');

    // Mettre à jour le total dans l'en-tête de la facture
    Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->update(['total' => round((float) $newTotal, 2)]);
}


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
