<?php
/**
 * ============================================================================
 *  tools/test_billing_logic.php — Banc d'essai REVENU (pur, exécutable hors WHMCS)
 * ============================================================================
 *
 * Objectif : prouver qu'AUCUN des changements (chantier forfaits P0→P5 +
 * refonte 1.27.0 « Fixe / À l'usage ») ne fait perdre de revenu dans WHMCS.
 *
 * Le banc charge le VRAI code de production (_sm_computeBaseCharge et
 * _sm_quotaMaxSizeBytes — fonctions pures, aucune BD requise) et le confronte
 * à la formule HISTORIQUE : montant = max(1, ceil(usage / GoParTranche)) × prix.
 *
 *   §1  Produit HÉRITÉ (tiers, quota 0, notify)  == historique, à l'octet près
 *   §2  Forfait « À l'usage » (tiers + block)    == historique sous quota ;
 *       plafonné au quota au-delà (le serveur bloque physiquement) ; quota 0 == historique
 *   §3  Forfait « Fixe » (flat + block)          : ligne Hosting INTACTE, aucune ligne ajoutée
 *   §4  Anciens forfaits « bill » (pré-1.27.0)   : math d'excédent exacte, préservée
 *   §5  _sm_quotaMaxSizeBytes                    : le plafond serveur n'est poussé QUE en block
 *   §6  Clamp gb_per_tier du hook                : 0/vide → 10 (défaut historique)
 *   §7  Aucune ligne fantôme (overage_price résiduel ignoré hors mode 'bill')
 *
 * Usage :  php tools/test_billing_logic.php   (CLI, n'importe où — lecture seule)
 */

define('WHMCS', true); // franchir le garde du fichier lib (aucune fonction BD n'est appelée)
require __DIR__ . '/../modules/servers/smartermail/lib/SmarterMailProductSettings.php';

$fail = 0;
$tests = 0;

/** Assertion : $cond doit être vrai. */
function ok(bool $cond, string $label, string $detail = ''): void
{
    global $fail, $tests;
    $tests++;
    if (!$cond) {
        $fail++;
        printf("  FAIL  %s%s\n", $label, $detail !== '' ? '  [' . $detail . ']' : '');
    }
}

/** Comparaison monétaire stricte (les deux côtés arrondis à 2 décimales). */
function moneyEq(float $a, float $b): bool
{
    return abs(round($a, 2) - round($b, 2)) < 0.000001;
}

/** Formule HISTORIQUE (avant tout le chantier) : tranches min 1. */
function histTiers(float $usage, int $gbPerTier): int
{
    return max(1, (int) ceil($usage / max(1, $gbPerTier)));
}
function histAmount(float $usage, int $gbPerTier, float $unitPrice): float
{
    return round(histTiers($usage, $gbPerTier) * $unitPrice, 2);
}

/** Réglages type. */
function settingsOf(string $model, int $quotaGb, string $mode, float $overPrice = 0.0): array
{
    return [
        'billing_model' => $model,
        'quota_gb'      => $quotaGb,
        'overage_mode'  => $mode,
        'overage_price' => $overPrice,
    ];
}

/** Appel du VRAI code de prod. */
function charge(array $settings, float $usage, int $gbPerTier, float $unitPrice): array
{
    return _sm_computeBaseCharge($settings, [
        'usageGB'       => $usage,
        'gbPerTier'     => $gbPerTier,
        'baseUnitPrice' => $unitPrice,
    ]);
}

// Grilles de balayage (bornes + valeurs de série).
$tierSizes  = [1, 5, 10, 25];
$unitPrices = [2.00, 6.00, 7.49, 15.375];
$usagePoints = function (int $t): array {
    $pts = [0.0, 0.01, 0.5, 1.0, $t - 0.01, (float) $t, $t + 0.01,
            2.0 * $t, 2.5 * $t, 10 * $t - 0.01, 10.0 * $t, 10 * $t + 0.01,
            33.33, 99.99, 100.0, 250.5, 999.99];
    for ($u = 0.0; $u <= 300.0; $u += 0.37) { $pts[] = $u; } // série fine
    return array_values(array_filter($pts, fn($u) => $u >= 0));
};

echo "Banc d'essai REVENU — code de production réel (_sm_computeBaseCharge)\n";
echo str_repeat('=', 74) . "\n";

// ════════════════════════════════════════════════════════════════════════════
// §1 — HÉRITÉ (tiers, quota 0, notify) : STRICTEMENT identique à l'historique.
//      C'est le parc actuel d'Astral : la moindre dérive = facture changée.
// ════════════════════════════════════════════════════════════════════════════
echo "§1 Hérité (tiers/quota 0/notify) == historique\n";
$legacy = settingsOf('tiers', 0, 'notify');
foreach ($tierSizes as $t) {
    foreach ($unitPrices as $p) {
        foreach ($usagePoints($t) as $u) {
            $c = charge($legacy, $u, $t, $p);
            ok($c['rewriteHosting'] === true, "§1 rewrite (u=$u t=$t)");
            ok($c['billedTiers'] === histTiers($u, $t), "§1 tranches", "u=$u t=$t got={$c['billedTiers']} want=" . histTiers($u, $t));
            ok(moneyEq($c['baseAmount'], histAmount($u, $t, $p)), "§1 montant", "u=$u t=$t p=$p got={$c['baseAmount']} want=" . histAmount($u, $t, $p));
            ok($c['overageLine'] === null, "§1 aucune ligne d'excédent", "u=$u t=$t");
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// §2 — Forfait « À L'USAGE » 1.27.0 (tiers + block, quota Q) :
//      sous/au quota == historique ; au-delà (blocage serveur contourné) ==
//      plafonné au quota ; quota 0 == historique intégral (aucun plafond).
// ════════════════════════════════════════════════════════════════════════════
echo "§2 Forfait À l'usage (tiers+block)\n";
foreach ([['q' => 50, 't' => 10], ['q' => 25, 't' => 10], ['q' => 100, 't' => 25], ['q' => 7, 't' => 5]] as $case) {
    $q = $case['q']; $t = $case['t']; $p = 6.00;
    $usage = settingsOf('tiers', $q, 'block', 5.00); // overage_price résiduel VOLONTAIRE (doit être ignoré)
    $quotaTiers = max(1, (int) ceil($q / $t));

    // Sous et exactement au quota → identique à l'historique (revenu préservé).
    foreach ([0.0, 0.01, $q / 3, $q - 0.01, (float) $q] as $u) {
        $c = charge($usage, $u, $t, $p);
        ok(moneyEq($c['baseAmount'], histAmount($u, $t, $p)), "§2 sous-quota == historique", "q=$q t=$t u=$u got={$c['baseAmount']} want=" . histAmount($u, $t, $p));
        ok($c['overageLine'] === null, "§2 sous-quota sans excédent", "q=$q u=$u");
    }
    // Au-delà du quota (ne devrait pas arriver : maxSize poussé au serveur) → plafonné.
    foreach ([$q + 0.01, $q * 1.5, $q * 3] as $u) {
        $c = charge($usage, $u, $t, $p);
        ok($c['billedTiers'] === $quotaTiers, "§2 plafonné au quota", "q=$q t=$t u=$u got={$c['billedTiers']} want=$quotaTiers");
        ok(moneyEq($c['baseAmount'], round($quotaTiers * $p, 2)), "§2 montant plafonné", "q=$q u=$u");
        ok($c['overageLine'] === null, "§2 jamais de ligne fantôme en block", "q=$q u=$u");
        ok(!empty($c['quota']['over']), "§2 signal quota dépassé (alerte admin)", "q=$q u=$u");
    }
}
// Quota 0 (illimité) + block → aucun plafond → historique intégral.
$unl = settingsOf('tiers', 0, 'block');
foreach ([0.0, 9.99, 100.0, 555.5] as $u) {
    $c = charge($unl, $u, 10, 6.00);
    ok(moneyEq($c['baseAmount'], histAmount($u, 10, 6.00)), "§2 quota 0 == historique", "u=$u");
    ok($c['overageLine'] === null, "§2 quota 0 sans excédent", "u=$u");
}

// ════════════════════════════════════════════════════════════════════════════
// §3 — Forfait « FIXE » 1.27.0 (flat + block) : la ligne Hosting n'est PAS
//      réécrite (WHMCS facture le prix produit tel quel) et AUCUNE ligne ajoutée.
// ════════════════════════════════════════════════════════════════════════════
echo "§3 Forfait Fixe (flat+block)\n";
$fixe = settingsOf('flat', 50, 'block', 9.99); // prix d'excédent résiduel volontaire
foreach ([0.0, 25.0, 49.99, 50.0, 80.0, 500.0] as $u) {
    $c = charge($fixe, $u, 10, 12.00);
    ok($c['rewriteHosting'] === false, "§3 Hosting intact (pas de réécriture)", "u=$u");
    ok(moneyEq($c['baseAmount'], 12.00), "§3 montant = prix produit", "u=$u got={$c['baseAmount']}");
    ok($c['overageLine'] === null, "§3 aucune ligne ajoutée en block", "u=$u");
}

// ════════════════════════════════════════════════════════════════════════════
// §4 — ANCIENS forfaits « bill » (créés avant 1.27.0, non ré-enregistrés) :
//      comportement préservé — base plafonnée au quota + ligne d'excédent
//      exacte (extra × overage_price). Et « bill » sans prix ⇒ historique.
// ════════════════════════════════════════════════════════════════════════════
echo "§4 Anciens forfaits 'bill' (pré-1.27.0) préservés\n";
$bill = settingsOf('tiers', 50, 'bill', 4.00);
foreach ([0.0, 30.0, 50.0] as $u) { // sous/au quota → historique
    $c = charge($bill, $u, 10, 6.00);
    ok(moneyEq($c['baseAmount'], histAmount($u, 10, 6.00)), "§4 bill sous-quota == historique", "u=$u");
    ok($c['overageLine'] === null, "§4 bill sous-quota sans excédent", "u=$u");
}
foreach ([['u' => 60.0, 'extra' => 1], ['u' => 75.0, 'extra' => 3], ['u' => 120.0, 'extra' => 7]] as $case) {
    $c = charge($bill, $case['u'], 10, 6.00);
    ok($c['billedTiers'] === 5, "§4 base plafonnée au quota", "u={$case['u']}");
    ok(moneyEq($c['baseAmount'], 30.00), "§4 base = 5 tranches × 6", "u={$case['u']}");
    ok(is_array($c['overageLine']), "§4 ligne d'excédent présente", "u={$case['u']}");
    ok(($c['overageLine']['tiers'] ?? -1) === $case['extra'], "§4 tranches excédentaires", "u={$case['u']} got=" . ($c['overageLine']['tiers'] ?? 'null') . " want={$case['extra']}");
    ok(moneyEq($c['overageLine']['amount'] ?? -1, $case['extra'] * 4.00), "§4 montant excédent", "u={$case['u']}");
    // Revenu total nouveau modèle vs historique (informative, pas une assertion) :
    // total = 30 + extra×4 ; historique = ceil(u/10)×6 — choix pré-1.27 assumé.
}
// « bill » avec prix d'excédent à 0 → repli plein usage (= historique, AUCUNE perte).
$bill0 = settingsOf('tiers', 50, 'bill', 0.0);
foreach ([60.0, 120.0] as $u) {
    $c = charge($bill0, $u, 10, 6.00);
    ok(moneyEq($c['baseAmount'], histAmount($u, 10, 6.00)), "§4 bill sans prix == historique", "u=$u");
    ok($c['overageLine'] === null, "§4 bill sans prix, sans ligne", "u=$u");
}

// ════════════════════════════════════════════════════════════════════════════
// §5 — _sm_quotaMaxSizeBytes : le plafond n'est poussé au serveur QUE en
//      'block' (Fixe ET À l'usage 1.27.0). bill/notify ⇒ 0 (on ne bloque pas
//      ce qu'on veut facturer/alerter). C'est LA garantie que « À l'usage »
//      ne dépasse jamais le quota (donc jamais de plafonnement de facture).
// ════════════════════════════════════════════════════════════════════════════
echo "§5 Plafond serveur (maxSize) poussé au bon moment\n";
ok(_sm_quotaMaxSizeBytes(settingsOf('tiers', 50, 'block')) === 50 * 1024 * 1024 * 1024, '§5 block+50Go → 50 Go en octets');
ok(_sm_quotaMaxSizeBytes(settingsOf('flat', 20, 'block')) === 20 * 1024 * 1024 * 1024, '§5 flat(Fixe)+20Go → 20 Go en octets');
ok(_sm_quotaMaxSizeBytes(settingsOf('tiers', 0, 'block')) === 0, '§5 block+quota 0 → illimité (0)');
ok(_sm_quotaMaxSizeBytes(settingsOf('tiers', 50, 'bill')) === 0, '§5 bill → pas de blocage serveur');
ok(_sm_quotaMaxSizeBytes(settingsOf('tiers', 50, 'notify')) === 0, '§5 notify → pas de blocage serveur');

// ════════════════════════════════════════════════════════════════════════════
// §6 — Clamp gb_per_tier du hook (hooks.php:497) : réplique exacte de la ligne
//      « max(1, ((int) $pkg['gb_per_tier']) ?: 10) » — 0/vide ⇒ 10 (historique).
// ════════════════════════════════════════════════════════════════════════════
echo "§6 Clamp gb_per_tier du hook\n";
$hookClamp = fn($v) => max(1, ((int) $v) ?: 10);
ok($hookClamp(0) === 10,   '§6 0 → 10 (défaut historique)');
ok($hookClamp('') === 10,  '§6 vide → 10');
ok($hookClamp(5) === 5,    '§6 5 → 5');
ok($hookClamp(-3) === 1,   '§6 négatif → plancher 1');
ok($hookClamp(25) === 25,  '§6 25 → 25');

// ════════════════════════════════════════════════════════════════════════════
// §7 — Mapping GUI 1.27.0 (réplique de _sm_savePackage, BD requise pour l'original)
//      « fixe » → flat+block ; tout le reste → tiers+block. Vérifié au travers
//      du calcul réel : Fixe n'écrase jamais Hosting ; À l'usage == historique
//      sous quota.
// ════════════════════════════════════════════════════════════════════════════
echo "§7 Mapping plan_type 1.27.0 → comportement\n";
$mapPlan = fn(string $pt): array => [($pt === 'fixe') ? 'flat' : 'tiers', 'block'];
[$bm, $om] = $mapPlan('fixe');
$c = charge(settingsOf($bm, 30, $om), 12.0, 10, 8.00);
ok($bm === 'flat' && $om === 'block' && $c['rewriteHosting'] === false, '§7 fixe → flat+block, Hosting intact');
[$bm, $om] = $mapPlan('usage');
$c = charge(settingsOf($bm, 30, $om), 12.0, 10, 8.00);
ok($bm === 'tiers' && $om === 'block' && moneyEq($c['baseAmount'], histAmount(12.0, 10, 8.00)), '§7 usage → tiers+block == historique sous quota');

// ════════════════════════════════════════════════════════════════════════════
echo str_repeat('=', 74) . "\n";
printf("%d assertions — %s\n", $tests, $fail === 0
    ? '✓ AUCUNE PERTE DE REVENU DÉTECTÉE (hérité byte-identique, forfaits conformes)'
    : "✗ $fail ÉCHEC(S) — NE PAS DÉPLOYER EN PROD");
exit($fail === 0 ? 0 : 1);
