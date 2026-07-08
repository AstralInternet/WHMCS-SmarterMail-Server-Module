<?php
/**
 * ============================================================================
 *  diag_packages.php — Harnais BYTE-IDENTIQUE des forfaits (LECTURE SEULE)
 * ============================================================================
 *
 *  Vérifie que le convertisseur _sm_legacyConfigToPackage() reproduit EXACTEMENT
 *  chaque expression consommateur des 23 configoptions historiques, pour TOUS les
 *  produits SmarterMail. C'est le garde-fou de la Phase 0 : tant que ce script
 *  rapporte « 0 écart », brancher les consommateurs sur le resolver (Phases 3-4)
 *  ne change RIEN au comportement.
 *
 *  Le côté « attendu » reproduit indépendamment le code réel (défaut + cast +
 *  plancher). Pour les champs à plancher DIVERGENT (gb_per_tier, seuil), on
 *  vérifie l'équivalence APRÈS le clamp du site MODULE **et** du site HOOK.
 *
 *  USAGE (CLI, sur le serveur ou une copie de la base) :
 *      php diag_packages.php
 *
 *  ⚠️  N'ÉCRIT AUCUNE donnée métier. (La lecture des réglages produit peut
 *      auto-créer la table mod_sm_product_settings vide — comportement déjà
 *      présent en production.) SUPPRIMEZ le fichier après usage.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ce script s'exécute uniquement en CLI : php diag_packages.php\n");
}

// ── Localiser init.php (remonte jusqu'à 8 niveaux) ──────────────────────────
$dir = __DIR__;
$init = null;
for ($i = 0; $i < 8; $i++) {
    if (is_file($dir . '/init.php')) { $init = $dir . '/init.php'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if (!$init) {
    fwrite(STDERR, "ERREUR : init.php introuvable. Placez ce script sous la racine WHMCS.\n");
    exit(1);
}
$ROOT = dirname($init);
require $init;

use WHMCS\Database\Capsule;

require_once $ROOT . '/modules/servers/smartermail/lib/SmarterMailPackages.php';

echo "====================================================\n";
echo "  Harnais byte-identique — Forfaits SmarterMail\n";
echo "====================================================\n";

$totalChecks   = 0;
$totalMismatch = 0;

/**
 * Compare une valeur attendue (expression consommateur) à la valeur du
 * convertisseur. Tolérance flottante. Incrémente les compteurs globaux.
 */
$cmp = function (string $field, $expected, $actual) use (&$totalChecks, &$totalMismatch): void {
    $totalChecks++;
    $eq = ($expected === $actual);
    if (!$eq && (is_float($expected) || is_float($actual))) {
        $eq = (abs((float) $expected - (float) $actual) < 0.0000001);
    }
    if (!$eq) {
        $totalMismatch++;
        printf("  \xE2\x9C\x97 %-24s attendu=%s  obtenu=%s\n",
            $field, var_export($expected, true), var_export($actual, true));
    }
};

// ── Auto-test unitaire de _sm_parsePackageId ────────────────────────────────
echo "\n── _sm_parsePackageId ──\n";
$idCases = ['' => 0, '3' => 3, '#3 — Argent' => 3, '  12 ' => 12, 'abc' => 0, '#7' => 7, '0' => 0];
foreach ($idCases as $in => $want) {
    $got = _sm_parsePackageId((string) $in);
    $totalChecks++;
    if ($got !== $want) {
        $totalMismatch++;
        printf("  \xE2\x9C\x97 parse(%s) attendu=%d obtenu=%d\n", var_export($in, true), $want, $got);
    }
}
echo "  (7 cas vérifiés)\n";

// ── Produits SmarterMail ────────────────────────────────────────────────────
$products = Capsule::table('tblproducts')
    ->where('servertype', 'smartermail')
    ->orderBy('id')
    ->get();

echo "\nProduits smartermail : " . count($products) . "\n";

foreach ($products as $p) {
    $co = [];
    for ($i = 1; $i <= 24; $i++) {
        $k = 'configoption' . $i;
        $co[$k] = $p->$k ?? null;
    }
    $pid = (int) $p->id;
    $pkg = _sm_legacyConfigToPackage($co, ['pid' => $pid]);
    $ps  = _sm_getProductSettings($pid);

    echo "\n── #$pid " . $p->name . " ──\n";
    $before = $totalMismatch;

    // Général / protocoles — reproduction indépendante du code réel.
    $cmp('offer_eas',           (($co['configoption14'] ?? 'on') === 'on'),          $pkg['offer_eas']);
    $cmp('offer_mapi',          (($co['configoption15'] ?? 'on') === 'on'),          $pkg['offer_mapi']);
    $cmp('price_eas',           (float) ($co['configoption2'] ?? 0),                 $pkg['price_eas']);
    $cmp('price_mapi',          (float) ($co['configoption3'] ?? 0),                 $pkg['price_mapi']);
    $cmp('price_bundle',        (float) ($co['configoption4'] ?? 0),                 $pkg['price_bundle']);
    $cmp('gb_per_tier(raw)',    $co['configoption1'],                                $pkg['gb_per_tier']);
    $cmp('threshold(raw)',      $co['configoption16'],                               $pkg['billing_threshold_days']);
    $cmp('domain_path',         ($co['configoption5'] ?? 'C:\\SmarterMail\\Domains\\'), $pkg['domain_path']);
    $cmp('outbound_ip',         ($co['configoption6'] ?? 'default'),                 $pkg['outbound_ip']);
    $cmp('max_users',           max(0, (int) ($co['configoption7'] ?? 0)),           $pkg['max_users']);
    $cmp('max_domain_aliases',  max(0, (int) ($co['configoption17'] ?? 0)),          $pkg['max_domain_aliases']);
    $cmp('delete_on_terminate', (($co['configoption8'] ?? null) === 'on'),           $pkg['delete_on_terminate']);

    // Mot de passe
    $cmp('pwd_min_len',         (int) ($co['configoption9'] ?? 8),                   $pkg['pwd_min_len']);
    $cmp('pwd_require_upper',   (($co['configoption10'] ?? 'on') === 'on'),          $pkg['pwd_require_upper']);
    $cmp('pwd_require_lower',   false,                                               $pkg['pwd_require_lower']);
    $cmp('pwd_require_digit',   (($co['configoption11'] ?? 'on') === 'on'),          $pkg['pwd_require_digit']);
    $cmp('pwd_require_special', (($co['configoption12'] ?? 'on') === 'on'),          $pkg['pwd_require_special']);

    // DNS
    $cmp('spf_primary',         trim((string) ($co['configoption13'] ?? '')),        $pkg['spf_primary']);
    $cmp('spf_secondary',       trim((string) ($co['configoption18'] ?? '')),        $pkg['spf_secondary']);
    $cmp('autodiscover_host',   strtolower(trim((string) ($co['configoption19'] ?? ''))), $pkg['autodiscover_host']);
    $cmp('srv_target',          strtolower(trim((string) ($co['configoption20'] ?? ''))), $pkg['srv_target']);
    $cmp('dmarc_check',         (($co['configoption21'] ?? 'on') === 'on'),          $pkg['dmarc_check']);
    $cmp('dmarc_rua',           trim((string) ($co['configoption22'] ?? '')),        $pkg['dmarc_rua']);
    $cmp('dmarc_policy',        trim((string) ($co['configoption23'] ?? 'none')),    $pkg['dmarc_policy']);

    // Disque & facturation — doit refléter _sm_getProductSettings() à l'identique.
    $cmp('billing_model',        $ps['billing_model'],        $pkg['billing_model']);
    $cmp('quota_gb',             $ps['quota_gb'],             $pkg['quota_gb']);
    $cmp('overage_mode',         $ps['overage_mode'],         $pkg['overage_mode']);
    $cmp('overage_price',        $ps['overage_price'],        $pkg['overage_price']);
    $cmp('notify_threshold_pct', $ps['notify_threshold_pct'], $pkg['notify_threshold_pct']);
    $cmp('per_mailbox_price',    $ps['per_mailbox_price'],    $pkg['per_mailbox_price']);
    $cmp('included_gb',          $ps['included_gb'],          $pkg['included_gb']);
    $cmp('included_mailboxes',   $ps['included_mailboxes'],   $pkg['included_mailboxes']);
    $cmp('max_mailbox_size_gb',  0,                           $pkg['max_mailbox_size_gb']);

    // ── Équivalence APRÈS plancher pour les champs DIVERGENTS ────────────────
    // gb_per_tier — site MODULE (?? 10) puis site HOOK (?: 10).
    $cmp('gb_per_tier@module',
        max(1, (int) ($co['configoption1'] ?? 10)),
        max(1, (int) ($pkg['gb_per_tier'] ?? 10)));
    $cmp('gb_per_tier@hook',
        max(1, ((int) $co['configoption1']) ?: 10),
        max(1, ((int) $pkg['gb_per_tier']) ?: 10));
    // billing_threshold_days — site MODULE (max(1) … ?? 1) puis HOOK (max(0) … ?: 0).
    $cmp('threshold@module',
        max(1, (int) ($co['configoption16'] ?? 1)),
        max(1, (int) ($pkg['billing_threshold_days'] ?? 1)));
    $cmp('threshold@hook',
        max(0, ((int) $co['configoption16']) ?: 0),
        max(0, ((int) $pkg['billing_threshold_days']) ?: 0));
    // pwd_min_len — plancher ADMIN (max 8) puis BOÎTE (max 1).
    $cmp('pwd_min_len@admin',
        max(8, (int) ($co['configoption9'] ?? 8)),
        max(8, (int) $pkg['pwd_min_len']));
    $cmp('pwd_min_len@mailbox',
        max(1, (int) ($co['configoption9'] ?? 8)),
        max(1, (int) $pkg['pwd_min_len']));

    if ($totalMismatch === $before) {
        echo "  \xE2\x9C\x93 identique\n";
    }
}

// ── Verdict ─────────────────────────────────────────────────────────────────
echo "\n====================================================\n";
printf("  %d vérifications, %d écart(s).\n", $totalChecks, $totalMismatch);
if ($totalMismatch === 0) {
    echo "  \xE2\x9C\x93 BYTE-IDENTIQUE — le convertisseur reproduit le legacy.\n";
    echo "====================================================\n";
    exit(0);
}
echo "  \xE2\x9C\x97 DES ÉCARTS EXISTENT — corriger _sm_legacyConfigToPackage avant de brancher.\n";
echo "====================================================\n";
exit(1);
