<?php
/**
 * ============================================================================
 *  tools/test_billing_forfaits.php — Banc d'essai REVENU des NOUVEAUX produits
 *  (chaîne complète forfaits, exécutable hors WHMCS via une BD simulée)
 * ============================================================================
 *
 * Complète tools/test_billing_logic.php (qui prouve le CALCUL) : ici on prouve
 * la CHAÎNE COMPLÈTE d'un nouveau produit lié à un forfait, avec le VRAI code
 * de production à chaque maillon :
 *
 *   POST de la GUI addon ──► _sm_savePackage()          (mapping + clamps)
 *        mod_sm_packages ──► _sm_getPackage()           (normalisation + masque global)
 *   configoption24 ("3" ou "#3 — Argent") ──► _sm_resolvePackage()
 *                                             via _sm_packageFromServiceRow()
 *   valeurs consommées par le hook (clamps hooks.php:497-502 répliqués)
 *        ──► _sm_computeBaseCharge() / _sm_quotaMaxSizeBytes()  ──► MONTANTS
 *
 * Seule la couche Capsule (BD) est simulée en mémoire — tout le reste est le
 * code de production réel, inclus depuis modules/servers/smartermail/lib/.
 *
 *   F1  Sauvegarde GUI → lecture : mapping champ-à-champ + clamps (« usage » →
 *       tiers+block ; « fixe » → flat+block ; valeurs hostiles → planchers)
 *   F2  Pont configoption24 : id nu, libellé « #id — nom », forfait inexistant
 *       → repli hérité (+ journal), vide → hérité, supprimé (soft) → hérité
 *   F3  Bout-en-bout « À l'usage » : montants disque exacts + plafond serveur
 *       + lignes EAS/MAPI (réplique exacte du regroupement du hook)
 *   F4  Bout-en-bout « Fixe » : ligne Hosting intacte, aucune ligne, plafond poussé
 *   F5  (mode « masked ») EAS/MAPI désactivé GLOBALEMENT : les offres sont
 *       masquées mais les PRIX restent → la facturation n'est JAMAIS supprimée
 *
 * Usage :  php tools/test_billing_forfaits.php
 *          php tools/test_billing_forfaits.php masked   (variante masquage global)
 */

// ─────────────────────────────────────────────────────────────────────────────
//  Stub Capsule — BD en mémoire (l'UNIQUE pièce simulée)
// ─────────────────────────────────────────────────────────────────────────────
namespace WHMCS\Database {

    /** Ligne : accès propriété tolérant (colonne absente → null, comme une vraie table). */
    class FakeRow
    {
        public function __construct(private array $d) {}
        public function __get($k)      { return $this->d[$k] ?? null; }
        public function __isset($k)    { return array_key_exists($k, $this->d) && $this->d[$k] !== null; }
        public function set($k, $v)    { $this->d[$k] = $v; }
        public function merge(array $a){ $this->d = array_merge($this->d, $a); }
    }

    class FakeSchema
    {
        public function hasTable($t): bool { return true; }   // tables « déjà créées »
        public function create($t, $cb): void {}
    }

    class FakeQuery
    {
        private array $wheres = [];
        public function __construct(private string $table) {}

        public function where($col, $a, $b = null): self
        {
            $this->wheres[] = ($b === null) ? [$col, '=', $a] : [$col, $a, $b];
            return $this;
        }
        public function orderBy($c, $d = 'asc'): self { return $this; }

        private function match(FakeRow $r): bool
        {
            foreach ($this->wheres as [$col, $op, $val]) {
                $v = $r->$col;
                if ($op === '=' && !($v == $val)) return false;
                if ($op === '>' && !($v >  $val)) return false;
            }
            return true;
        }
        private function &rows(): array
        {
            if (!isset(Capsule::$tables[$this->table])) Capsule::$tables[$this->table] = [];
            return Capsule::$tables[$this->table];
        }
        public function first(): ?FakeRow
        {
            foreach ($this->rows() as $r) if ($this->match($r)) return $r;
            return null;
        }
        public function value($col) { $r = $this->first(); return $r?->$col; }
        public function get(): array
        {
            return array_values(array_filter($this->rows(), fn($r) => $this->match($r)));
        }
        public function count(): int { return count($this->get()); }
        public function insertGetId(array $data): int
        {
            $id = Capsule::$autoInc[$this->table] = (Capsule::$autoInc[$this->table] ?? 0) + 1;
            $data['id'] = $id;
            Capsule::$tables[$this->table][] = new FakeRow($data);
            return $id;
        }
        public function update(array $data): int
        {
            $n = 0;
            foreach ($this->rows() as $r) if ($this->match($r)) { $r->merge($data); $n++; }
            return $n;
        }
        public function updateOrInsert(array $matchCond, array $data): bool
        {
            foreach ($matchCond as $k => $v) $this->where($k, $v);
            if ($this->first()) { $this->update($data); return true; }
            $this->insertGetId(array_merge($matchCond, $data));
            return true;
        }
    }

    class Capsule
    {
        public static array $tables  = [];
        public static array $autoInc = [];
        public static function table(string $name): FakeQuery { return new FakeQuery($name); }
        public static function schema(): FakeSchema { return new FakeSchema(); }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Banc d'essai (espace global) — charge le VRAI code de production
// ─────────────────────────────────────────────────────────────────────────────
namespace {

    define('WHMCS', true);

    /** Journal capturé (assertions sur le repli hérité). */
    $GLOBALS['SM_TEST_LOG'] = [];
    function logActivity($msg) { $GLOBALS['SM_TEST_LOG'][] = (string) $msg; }

    require __DIR__ . '/../modules/servers/smartermail/lib/SmarterMailProductSettings.php';
    require __DIR__ . '/../modules/servers/smartermail/lib/SmarterMailPackages.php';

    $MASKED = (($argv[1] ?? '') === 'masked');

    $fail = 0; $tests = 0;
    function ok(bool $cond, string $label, string $detail = ''): void
    {
        global $fail, $tests;
        $tests++;
        if (!$cond) { $fail++; printf("  FAIL  %s%s\n", $label, $detail !== '' ? '  [' . $detail . ']' : ''); }
    }
    function moneyEq(float $a, float $b): bool { return abs(round($a, 2) - round($b, 2)) < 0.000001; }

    /** Réplique EXACTE des clamps du hook (hooks.php:497-502). */
    function hookView(array $pkg): array
    {
        return [
            'gbPerTier' => max(1, ((int) $pkg['gb_per_tier']) ?: 10),
            'easPrice'  => $pkg['price_eas'],
            'mapiPrice' => $pkg['price_mapi'],
            'bundle'    => $pkg['price_bundle'],
            'lockDays'  => max(0, (int) $pkg['billing_threshold_days']),
            'doEasMapi' => ($pkg['price_eas'] > 0 || $pkg['price_mapi'] > 0), // hooks.php:743
        ];
    }

    /**
     * Réplique EXACTE du regroupement EAS/MAPI du hook (hooks.php:783-859) :
     * une ligne par type ; combiné éclaté vers EAS+MAPI si le prix combiné est 0.
     * @param array $boxes ['adresse' => ['eas'=>bool,'mapi'=>bool]]
     * @return array ['combined'=>float,'eas'=>float,'mapi'=>float,'total'=>float]
     */
    function easMapiLines(array $boxes, float $easPrice, float $mapiPrice, float $bundle): array
    {
        $combined = $eas = $mapi = [];
        foreach ($boxes as $addr => $p) {
            $hasE = !empty($p['eas']); $hasM = !empty($p['mapi']);
            if ($hasE && $hasM)      $combined[] = $addr;
            elseif ($hasE)           $eas[] = $addr;
            elseif ($hasM)           $mapi[] = $addr;
        }
        $out = ['combined' => 0.0, 'eas' => 0.0, 'mapi' => 0.0];
        if (!empty($combined) && $bundle > 0) {
            $out['combined'] = count($combined) * $bundle;
        } elseif (!empty($combined)) {
            $eas  = array_merge($eas,  $combined);
            $mapi = array_merge($mapi, $combined);
        }
        if (!empty($eas)  && $easPrice  > 0) $out['eas']  = count($eas)  * $easPrice;
        if (!empty($mapi) && $mapiPrice > 0) $out['mapi'] = count($mapi) * $mapiPrice;
        $out['total'] = $out['combined'] + $out['eas'] + $out['mapi'];
        return $out;
    }

    /** POST simulé de l'éditeur de forfait (GUI 1.27.0). */
    function guiPost(array $over = []): array
    {
        return array_merge([
            'name'                   => 'Argent',
            'plan_type'              => 'usage',
            'gb_per_tier'            => '10',
            'quota_gb'               => '50',
            'max_mailbox_size_gb'    => '5',
            'notify_threshold_pct'   => '90',
            'offer_eas'              => '1',
            'offer_mapi'             => '1',
            'price_eas'              => '2.50',
            'price_mapi'             => '3.00',
            'price_bundle'           => '4.50',
            'billing_threshold_days' => '30',
            'domain_path'            => 'D:\\Mail\\',
            'outbound_ip'            => 'default',
            'max_users'              => '25',
            'max_domain_aliases'     => '2',
            'delete_on_terminate'    => '1',
            'spf_primary'            => 'include:spf.example.com',
            'spf_secondary'          => '',
            'autodiscover_host'      => '',
            'srv_target'             => '',
            'dmarc_check'            => '1',
            'dmarc_rua'              => '',
            'dmarc_policy'           => 'none',
        ], $over);
    }

    /** Ligne de service simulée (jointure du hook) — legacy VOLONTAIREMENT différent. */
    function serviceRow(string $co24): object
    {
        return (object) [
            'serviceid'      => 101,
            'domain'         => 'client.ca',
            'packageid'      => 55,
            'serverhostname' => 'mail.example.com',
            // Legacy DIFFÉRENT du forfait → prouve quelle source gagne.
            'configoption1'  => '25',    // Go/tranche hérité
            'configoption2'  => '9.99',  // prix EAS hérité
            'configoption3'  => '8.88',  // prix MAPI hérité
            'configoption4'  => '0',     // pas de combiné hérité
            'configoption16' => '7',     // seuil hérité
            'configoption24' => $co24,   // pont forfait
        ];
    }

    echo 'Banc d\'essai REVENU — chaîne forfaits complète (code de production réel)'
        . ($MASKED ? '  [MODE MASQUÉ : EAS/MAPI désactivé globalement]' : '') . "\n";
    echo str_repeat('=', 74) . "\n";

    // ════════════════════════════════════════════════════════════════════════
    //  F5 (mode masqué) — le réglage global DOIT être posé AVANT toute lecture
    //  (cache statique par clé). Les offres sont masquées, les PRIX survivent.
    // ════════════════════════════════════════════════════════════════════════
    if ($MASKED) {
        _sm_setGlobalSetting('eas_mapi_available', false);
    }

    // ════════════════════════════════════════════════════════════════════════
    //  F1 — Sauvegarde GUI → lecture (mapping + clamps), via le VRAI code
    // ════════════════════════════════════════════════════════════════════════
    echo "F1 Sauvegarde GUI → lecture (mod_sm_packages simulée)\n";

    $idU = _sm_savePackage(null, guiPost());
    ok($idU > 0, 'F1 forfait « À l\'usage » créé');
    $pkgU = _sm_getPackage($idU);
    ok($pkgU !== null, 'F1 relecture forfait usage');
    ok($pkgU['billing_model'] === 'tiers' && $pkgU['overage_mode'] === 'block', 'F1 usage → tiers+block', "got={$pkgU['billing_model']}+{$pkgU['overage_mode']}");
    ok($pkgU['quota_gb'] === 50, 'F1 quota 50');
    ok($pkgU['gb_per_tier'] === 10, 'F1 tranche 10');
    ok(moneyEq($pkgU['price_eas'], 2.50) && moneyEq($pkgU['price_mapi'], 3.00) && moneyEq($pkgU['price_bundle'], 4.50), 'F1 prix EAS/MAPI/combiné');
    ok($pkgU['billing_threshold_days'] === 30, 'F1 seuil 30 j');
    ok($pkgU['max_mailbox_size_gb'] === 5, 'F1 taille max boîte 5');
    ok($pkgU['notify_threshold_pct'] === 90, 'F1 seuil alerte 90 (champ caché préservé)');
    ok($pkgU['overage_price'] === 0.0, 'F1 prix excédentaire absent → 0 (retiré de la GUI)');
    ok($pkgU['_source'] === 'package' && $pkgU['_package_id'] === $idU, 'F1 méta source');

    $idF = _sm_savePackage(null, guiPost(['name' => 'Bloc20', 'plan_type' => 'fixe', 'quota_gb' => '20']));
    $pkgF = _sm_getPackage($idF);
    ok($pkgF['billing_model'] === 'flat' && $pkgF['overage_mode'] === 'block', 'F1 fixe → flat+block');

    $idG = _sm_savePackage(null, guiPost(['name' => 'Garbage', 'plan_type' => 'n_importe_quoi']));
    $pkgG = _sm_getPackage($idG);
    ok($pkgG['billing_model'] === 'tiers' && $pkgG['overage_mode'] === 'block', 'F1 plan_type inconnu → défaut usage (tiers+block)');

    $idH = _sm_savePackage(null, guiPost([
        'name' => 'Hostile', 'price_eas' => '-5', 'quota_gb' => '-10',
        'gb_per_tier' => '0', 'billing_threshold_days' => '-3', 'notify_threshold_pct' => '250',
    ]));
    $pkgH = _sm_getPackage($idH);
    ok($pkgH['price_eas'] === 0.0, 'F1 prix négatif → 0');
    ok($pkgH['quota_gb'] === 0, 'F1 quota négatif → 0 (illimité)');
    ok($pkgH['gb_per_tier'] === 1, 'F1 tranche 0 → plancher 1');
    ok($pkgH['billing_threshold_days'] === 0, 'F1 seuil négatif → 0');
    ok($pkgH['notify_threshold_pct'] === 100, 'F1 seuil alerte plafonné 100');

    // ════════════════════════════════════════════════════════════════════════
    //  F2 — Pont configoption24 → resolver (id nu, libellé, absent, vide, supprimé)
    // ════════════════════════════════════════════════════════════════════════
    echo "F2 Pont configoption24 (resolver + replis)\n";

    $r = _sm_packageFromServiceRow(serviceRow((string) $idU));
    ok($r['_source'] === 'package' && $r['_package_id'] === $idU, 'F2 id nu → forfait');
    ok($r['gb_per_tier'] === 10 && moneyEq($r['price_eas'], 2.50), 'F2 valeurs du FORFAIT (pas du legacy 25/9.99)');

    $r = _sm_packageFromServiceRow(serviceRow('#' . $idU . ' — Argent'));
    ok($r['_source'] === 'package' && $r['_package_id'] === $idU, 'F2 libellé « #id — nom » → forfait');

    $GLOBALS['SM_TEST_LOG'] = [];
    $r = _sm_packageFromServiceRow(serviceRow('424242'));
    ok($r['_source'] === 'legacy', 'F2 forfait inexistant → repli hérité');
    ok(moneyEq((float) $r['price_eas'], 9.99) && max(1, ((int) $r['gb_per_tier']) ?: 10) === 25, 'F2 repli : valeurs HÉRITÉES (9.99 / 25)');
    ok((bool) preg_grep('/introuvable/', $GLOBALS['SM_TEST_LOG']), 'F2 repli journalisé (« introuvable »)');

    $r = _sm_packageFromServiceRow(serviceRow(''));
    ok($r['_source'] === 'legacy', 'F2 co24 vide → hérité');

    // Forfait supprimé (soft-delete) AVANT toute lecture (cache par id).
    $idDel = _sm_savePackage(null, guiPost(['name' => 'Efface']));
    foreach (\WHMCS\Database\Capsule::$tables['mod_sm_packages'] as $rowDel) {
        if ($rowDel->id == $idDel) $rowDel->set('is_deleted', 1);
    }
    $r = _sm_packageFromServiceRow(serviceRow((string) $idDel));
    ok($r['_source'] === 'legacy', 'F2 forfait supprimé (soft) → hérité');

    // ════════════════════════════════════════════════════════════════════════
    //  F3 — Bout-en-bout « À l'usage » : la facture d'un NOUVEAU produit
    // ════════════════════════════════════════════════════════════════════════
    echo "F3 Bout-en-bout « À l'usage » (forfait #$idU, produit 6,00 \$/tranche)\n";

    $pkg  = _sm_packageFromServiceRow(serviceRow((string) $idU));
    $hv   = hookView($pkg);
    ok($hv['gbPerTier'] === 10 && $hv['lockDays'] === 30 && $hv['doEasMapi'] === true, 'F3 vue hook (tranche/seuil/protocoles)');

    $P = 6.00;
    foreach ([
        ['u' => 0.0,  'tiers' => 1, 'amt' => 6.00],
        ['u' => 34.2, 'tiers' => 4, 'amt' => 24.00],
        ['u' => 50.0, 'tiers' => 5, 'amt' => 30.00],
        ['u' => 61.0, 'tiers' => 5, 'amt' => 30.00], // blocage contourné → plafonné, jamais plus
    ] as $c) {
        $ch = _sm_computeBaseCharge($pkg, ['usageGB' => $c['u'], 'gbPerTier' => $hv['gbPerTier'], 'baseUnitPrice' => $P]);
        ok($ch['billedTiers'] === $c['tiers'] && moneyEq($ch['baseAmount'], $c['amt']),
            "F3 disque u={$c['u']}", "got={$ch['billedTiers']}×→{$ch['baseAmount']} want={$c['tiers']}×→{$c['amt']}");
        ok($ch['overageLine'] === null, "F3 aucune ligne d'excédent u={$c['u']}");
        ok($ch['rewriteHosting'] === true, "F3 Hosting réécrit (usage) u={$c['u']}");
    }
    ok(_sm_quotaMaxSizeBytes($pkg) === 50 * 1024 * 1024 * 1024, 'F3 plafond 50 Go poussé au serveur (block)');

    // Lignes EAS/MAPI (réplique exacte du regroupement) — 3 combinés, 2 EAS, 1 MAPI.
    $boxes = [
        'a@client.ca' => ['eas' => true,  'mapi' => true],
        'b@client.ca' => ['eas' => true,  'mapi' => true],
        'c@client.ca' => ['eas' => true,  'mapi' => true],
        'd@client.ca' => ['eas' => true,  'mapi' => false],
        'e@client.ca' => ['eas' => true,  'mapi' => false],
        'f@client.ca' => ['eas' => false, 'mapi' => true],
    ];
    $L = easMapiLines($boxes, $hv['easPrice'], $hv['mapiPrice'], $hv['bundle']);
    ok(moneyEq($L['combined'], 3 * 4.50), 'F3 ligne combinée 3 × 4,50 = 13,50');
    ok(moneyEq($L['eas'], 2 * 2.50), 'F3 ligne EAS 2 × 2,50 = 5,00');
    ok(moneyEq($L['mapi'], 1 * 3.00), 'F3 ligne MAPI 1 × 3,00 = 3,00');
    ok(moneyEq($L['total'], 21.50), 'F3 total protocoles 21,50');

    // Sans prix combiné → les boîtes combinées sont facturées sur LES DEUX lignes.
    $L0 = easMapiLines($boxes, 2.50, 3.00, 0.0);
    ok(moneyEq($L0['eas'], 5 * 2.50) && moneyEq($L0['mapi'], 4 * 3.00) && moneyEq($L0['total'], 24.50),
        'F3 combiné à 0 → éclaté EAS(5×2,50)+MAPI(4×3,00) = 24,50');

    // ════════════════════════════════════════════════════════════════════════
    //  F4 — Bout-en-bout « Fixe » : Hosting jamais réécrit, plafond poussé
    // ════════════════════════════════════════════════════════════════════════
    echo "F4 Bout-en-bout « Fixe » (forfait #$idF, produit 12,00 \$)\n";
    $pkgFx = _sm_packageFromServiceRow(serviceRow((string) $idF));
    ok($pkgFx['billing_model'] === 'flat' && $pkgFx['overage_mode'] === 'block', 'F4 résolu flat+block');
    foreach ([0.0, 19.99, 20.0, 80.0] as $u) {
        $ch = _sm_computeBaseCharge($pkgFx, ['usageGB' => $u, 'gbPerTier' => 10, 'baseUnitPrice' => 12.00]);
        ok($ch['rewriteHosting'] === false, "F4 Hosting intact u=$u");
        ok(moneyEq($ch['baseAmount'], 12.00), "F4 montant = prix produit u=$u");
        ok($ch['overageLine'] === null, "F4 aucune ligne u=$u");
    }
    ok(_sm_quotaMaxSizeBytes($pkgFx) === 20 * 1024 * 1024 * 1024, 'F4 plafond 20 Go poussé au serveur');

    // ════════════════════════════════════════════════════════════════════════
    //  F5 — mode masqué : offres masquées, PRIX (donc facturation) intacts
    // ════════════════════════════════════════════════════════════════════════
    if ($MASKED) {
        echo "F5 Masquage global EAS/MAPI : la facturation survit\n";
        ok($pkgU['offer_eas'] === false && $pkgU['offer_mapi'] === false, 'F5 offres masquées (forfait)');
        ok(moneyEq($pkgU['price_eas'], 2.50) && moneyEq($pkgU['price_mapi'], 3.00), 'F5 PRIX intacts (forfait)');
        ok(hookView($pkgU)['doEasMapi'] === true, 'F5 hook facture toujours ($doEasMapi=true)');
        $rl = _sm_packageFromServiceRow(serviceRow(''));
        ok($rl['offer_eas'] === false && moneyEq((float) $rl['price_eas'], 9.99), 'F5 hérité : offre masquée, prix intact');
        $Lm = easMapiLines($boxes, $pkgU['price_eas'], $pkgU['price_mapi'], $pkgU['price_bundle']);
        ok(moneyEq($Lm['total'], 21.50), 'F5 lignes EAS/MAPI identiques (21,50)');
    }

    echo str_repeat('=', 74) . "\n";
    printf("%d assertions — %s\n", $tests, $fail === 0
        ? '✓ CHAÎNE FORFAITS CONFORME (nouveaux produits facturés selon le forfait)'
        : "✗ $fail ÉCHEC(S) — NE PAS DÉPLOYER EN PROD");
    exit($fail === 0 ? 0 : 1);
}
