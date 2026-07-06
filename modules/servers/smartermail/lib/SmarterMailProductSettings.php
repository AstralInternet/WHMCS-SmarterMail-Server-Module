<?php
/**
 * ============================================================================
 *  SmarterMailProductSettings.php — Réglages de facturation par produit
 * ============================================================================
 *
 * Porte les réglages multi-options par produit WHMCS que les 24 slots
 * configoption (23 déjà consommés) ne peuvent plus accueillir. Base du chantier
 * « généricité / revente » : modèles de facturation sélectionnables + quota
 * disque bloquant (design validé, AUDIT_COMPLET_smartermail.md §3.3).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  TABLE : mod_sm_product_settings   (une ligne par produit ; PK = product_id)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  product_id           INT  PK   — tblproducts.id
 *  billing_model        VARCHAR   — 'flat' | 'tiers' | 'per_mailbox' | 'hybrid'
 *  quota_gb             INT       — plafond disque en Go (0 = illimité)
 *  overage_mode         VARCHAR   — 'block' | 'bill' | 'notify'
 *  overage_price        DECIMAL   — prix d'une tranche excédentaire (≠ prix produit)
 *  per_mailbox_price    DECIMAL   — prix par boîte (modèles per_mailbox / hybrid)
 *  included_gb          INT       — Go inclus avant excédent (hybrid)
 *  included_mailboxes   INT       — boîtes incluses avant excédent (hybrid)
 *  notify_threshold_pct INT       — seuil d'alerte quota en % (défaut 90)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  « BASE + MODIFICATEUR » (et non 12 branches)
 * ─────────────────────────────────────────────────────────────────────────────
 *  billing_model définit le calcul de la ligne de BASE ; quota_gb + overage_mode
 *  ne modifient que le volet DISQUE (block plafonne, bill ajoute une ligne
 *  d'excédent, notify n'affecte pas la facture). Seul le modèle 'tiers' réécrit
 *  le montant de la ligne Hosting ; les autres passent par des lignes ajoutées.
 *
 *  RÉTRO-COMPATIBILITÉ : l'ABSENCE de ligne (ou toute erreur de lecture) ≡
 *  { billing_model=tiers, quota_gb=0, overage_mode=notify } ⇒ calcul et libellés
 *  strictement identiques au comportement historique. Le repli sur défauts est
 *  donc systématique : la facturation ne doit JAMAIS casser à cause de ce module.
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

use WHMCS\Database\Capsule;

// Valeurs autorisées — partagées entre la lecture (validation), la page addon
// d'édition et le calcul de facturation.
if (!defined('SM_BILLING_MODELS')) {
    define('SM_BILLING_MODELS', ['flat', 'tiers', 'per_mailbox', 'hybrid']);
}
if (!defined('SM_OVERAGE_MODES')) {
    define('SM_OVERAGE_MODES', ['block', 'bill', 'notify']);
}


/**
 * Réglages par défaut d'un produit — équivalents au comportement historique
 * (tiers, aucun quota, notify). Retournés quand aucune ligne n'existe ou en cas
 * d'erreur de lecture.
 *
 * @param  int   $productId
 * @return array
 */
function _sm_productSettingsDefaults(int $productId): array
{
    return [
        'product_id'           => $productId,
        'billing_model'        => 'tiers',   // comportement actuel (tranches disque)
        'quota_gb'             => 0,          // 0 = illimité (aucun plafond)
        'overage_mode'         => 'notify',   // le plus sûr : n'affecte pas la facture
        'overage_price'        => 0.0,
        'per_mailbox_price'    => 0.0,
        'included_gb'          => 0,
        'included_mailboxes'   => 0,
        'notify_threshold_pct' => 90,
    ];
}


/**
 * Crée la table mod_sm_product_settings si elle n'existe pas.
 * Cache statique : vérification unique par exécution PHP.
 */
function _sm_ensureProductSettingsTable(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        if (!Capsule::schema()->hasTable('mod_sm_product_settings')) {
            Capsule::schema()->create('mod_sm_product_settings', function ($table) {
                // Clé primaire = tblproducts.id (une ligne par produit ; pas d'auto-increment).
                $table->integer('product_id')->unsigned()->primary();

                // Modèle de facturation de la ligne de base.
                $table->string('billing_model', 20)->default('tiers');

                // Volet disque : plafond + mode d'excédent.
                $table->integer('quota_gb')->unsigned()->default(0);        // 0 = illimité
                $table->string('overage_mode', 10)->default('notify');
                $table->decimal('overage_price', 10, 2)->default(0);

                // Volet boîtes (modèles per_mailbox / hybrid).
                $table->decimal('per_mailbox_price', 10, 2)->default(0);

                // Inclus avant excédent (modèle hybrid).
                $table->integer('included_gb')->unsigned()->default(0);
                $table->integer('included_mailboxes')->unsigned()->default(0);

                // Seuil d'alerte quota (%).
                $table->smallInteger('notify_threshold_pct')->unsigned()->default(90);
            });
        }
    } catch (\Throwable $e) {
        // Non bloquant : en l'absence de table, _sm_getProductSettings() renvoie
        // les défauts → la facturation reste identique au comportement historique.
        logActivity('SmarterMail [product-settings] création table échouée : ' . $e->getMessage());
    }
}


/**
 * Lit les réglages d'un produit — requête SÉPARÉE (jamais un leftJoin dans la
 * requête de facturation : une table absente y ferait sauter tout le service via
 * le catch global du hook). Cache statique par exécution. Repli sur les défauts
 * sur TOUTE erreur ou absence de ligne — la facturation ne casse jamais ici.
 *
 * Les valeurs sont bornées/validées (enum + min/max) pour résister à des données
 * corrompues sans jamais lever d'exception vers l'appelant.
 *
 * @param  int   $productId  tblproducts.id
 * @return array             Réglages complets (voir _sm_productSettingsDefaults)
 */
function _sm_getProductSettings(int $productId): array
{
    static $cache = [];

    if ($productId <= 0) {
        return _sm_productSettingsDefaults($productId);
    }
    if (isset($cache[$productId])) {
        return $cache[$productId];
    }

    $defaults = _sm_productSettingsDefaults($productId);

    try {
        _sm_ensureProductSettingsTable();
        $row = Capsule::table('mod_sm_product_settings')
            ->where('product_id', $productId)
            ->first();

        if (!$row) {
            return $cache[$productId] = $defaults;
        }

        $model = (string) ($row->billing_model ?? 'tiers');
        $mode  = (string) ($row->overage_mode ?? 'notify');

        return $cache[$productId] = [
            'product_id'           => $productId,
            'billing_model'        => in_array($model, SM_BILLING_MODELS, true) ? $model : 'tiers',
            'quota_gb'             => max(0, (int) ($row->quota_gb ?? 0)),
            'overage_mode'         => in_array($mode, SM_OVERAGE_MODES, true) ? $mode : 'notify',
            'overage_price'        => max(0.0, (float) ($row->overage_price ?? 0)),
            'per_mailbox_price'    => max(0.0, (float) ($row->per_mailbox_price ?? 0)),
            'included_gb'          => max(0, (int) ($row->included_gb ?? 0)),
            'included_mailboxes'   => max(0, (int) ($row->included_mailboxes ?? 0)),
            'notify_threshold_pct' => min(100, max(1, (int) ($row->notify_threshold_pct ?? 90))),
        ];

    } catch (\Throwable $e) {
        // Repli défauts sur TOUTE erreur — jamais casser la facturation.
        logActivity('SmarterMail [product-settings] lecture échouée (produit #'
            . $productId . ') : ' . $e->getMessage());
        return $cache[$productId] = $defaults;
    }
}


/**
 * Calcule la CHARGE DE BASE (volet disque) d'un service selon le modèle de
 * facturation et le quota du produit. Fonction PURE (aucun accès DB/API, aucune
 * écriture) — partagée entre le hook InvoiceCreation (facture réelle) et le
 * tableau de bord client (estimé), pour garantir des calculs IDENTIQUES.
 *
 * « BASE + MODIFICATEUR » :
 *   - billing_model calcule la ligne de base :
 *       • 'tiers' (défaut historique) : tranches = ceil(usage/gbPerTier) ; la ligne
 *         Hosting est RÉÉCRITE à tranches × prix_produit.
 *       • 'flat' : forfait — la ligne Hosting reste au prix produit (non réécrite).
 *       • 'per_mailbox' / 'hybrid' : comptage de boîtes → étape 2b ; traités ici
 *         comme 'tiers' en attendant (ne peuvent être définis sans la page addon).
 *   - quota_gb + overage_mode ne modifient QUE le volet disque :
 *       • 'block'  : plafonne les tranches facturées au quota (le serveur applique
 *                    maxSize) — jamais facturé au-delà.
 *       • 'bill'   : base plafonnée au quota ; l'excédent part sur une ligne séparée
 *                    au prix d'excédent (overage_price).
 *       • 'notify' : aucun effet sur la facture (historique) ; sert à l'alerte / la
 *                    jauge quand l'usage dépasse le seuil.
 *
 * RÉTRO-COMPATIBILITÉ : 'tiers' + quota 0 + 'notify' (les défauts) ⇒ baseAmount =
 * ceil(usage/gbPerTier) × prix, rewriteHosting=true, aucune ligne d'excédent —
 * STRICTEMENT identique au calcul historique.
 *
 * @param  array $settings  Réglages produit (_sm_getProductSettings()).
 * @param  array $ctx       ['usageGB'=>float, 'gbPerTier'=>int, 'baseUnitPrice'=>float]
 * @return array {
 *   rewriteHosting: bool,   baseAmount: float,  billedTiers: int,  billedGB: int,
 *   overageLine:    ?array (['tiers'=>int,'amount'=>float] ou null),
 *   quota:          array  (['gb'=>int,'mode'=>string,'usagePct'=>float,'over'=>bool])
 * }
 */
function _sm_computeBaseCharge(array $settings, array $ctx): array
{
    $usageGB   = max(0.0, (float) ($ctx['usageGB'] ?? 0));
    $gbPerTier = max(1,   (int)   ($ctx['gbPerTier'] ?? 10));
    $unitPrice = max(0.0, (float) ($ctx['baseUnitPrice'] ?? 0));

    $model     = in_array(($settings['billing_model'] ?? 'tiers'), SM_BILLING_MODELS, true)
        ? $settings['billing_model'] : 'tiers';
    $quotaGB   = max(0,   (int)   ($settings['quota_gb'] ?? 0));
    $mode      = in_array(($settings['overage_mode'] ?? 'notify'), SM_OVERAGE_MODES, true)
        ? $settings['overage_mode'] : 'notify';
    $overPrice = max(0.0, (float) ($settings['overage_price'] ?? 0));

    // Tranches d'usage réel (min 1, comme l'historique) et tranches couvertes par
    // le quota (0 = pas de quota / illimité).
    $rawTiers   = max(1, (int) ceil($usageGB / $gbPerTier));
    $quotaTiers = $quotaGB > 0 ? max(1, (int) ceil($quotaGB / $gbPerTier)) : 0;

    // Volet quota commun (jauge + alerte).
    $quota = [
        'gb'       => $quotaGB,
        'mode'     => $mode,
        'usagePct' => $quotaGB > 0 ? round($usageGB / $quotaGB * 100, 1) : 0.0,
        'over'     => $quotaGB > 0 && $usageGB > $quotaGB,
    ];

    // ── Modèle 'flat' : la ligne Hosting reste au prix produit ────────────────
    if ($model === 'flat') {
        $overageLine = null;
        if ($mode === 'bill' && $quotaTiers > 0 && $rawTiers > $quotaTiers && $overPrice > 0) {
            $extra       = $rawTiers - $quotaTiers;
            $overageLine = ['tiers' => $extra, 'amount' => round($extra * $overPrice, 2)];
        }
        return [
            'rewriteHosting' => false,
            'baseAmount'     => round($unitPrice, 2),
            'billedTiers'    => 0,
            'billedGB'       => $quotaGB,     // « inclus » = quota (affichage)
            'overageLine'    => $overageLine,
            'quota'          => $quota,
        ];
    }

    // ── Modèle 'tiers' (défaut) + per_mailbox/hybrid (interim → tiers) ─────────
    $billedTiers = $rawTiers;
    $overageLine = null;

    if ($mode === 'block' && $quotaTiers > 0) {
        // Plafond : jamais facturé au-delà du quota (le serveur applique maxSize).
        $billedTiers = min($rawTiers, $quotaTiers);
    } elseif ($mode === 'bill' && $quotaTiers > 0 && $rawTiers > $quotaTiers && $overPrice > 0) {
        // Base plafonnée au quota ; excédent sur une ligne au prix d'excédent.
        $billedTiers = $quotaTiers;
        $extra       = $rawTiers - $quotaTiers;
        $overageLine = ['tiers' => $extra, 'amount' => round($extra * $overPrice, 2)];
    }
    // 'notify' : aucun plafond, aucune ligne — historique + alerte éventuelle.

    return [
        'rewriteHosting' => true,
        'baseAmount'     => round($billedTiers * $unitPrice, 2),
        'billedTiers'    => $billedTiers,
        'billedGB'       => $billedTiers * $gbPerTier,
        'overageLine'    => $overageLine,
        'quota'          => $quota,
    ];
}


/**
 * Taille max du domaine (en OCTETS) à pousser au serveur SmarterMail via
 * createDomain / setDomainSettings. Poussée UNIQUEMENT en mode 'block'
 * (quota_gb × 1024³) : en 'bill'/'notify', le serveur ne doit PAS bloquer ce
 * qu'on veut facturer/notifier. 0 = illimité côté serveur.
 *
 * (64 bits requis pour de très gros quotas ; WHMCS moderne tourne en 64 bits.)
 *
 * @param  array $settings  _sm_getProductSettings()
 * @return int              Octets (0 = illimité)
 */
function _sm_quotaMaxSizeBytes(array $settings): int
{
    $quotaGB = max(0, (int) ($settings['quota_gb'] ?? 0));
    $mode    = $settings['overage_mode'] ?? 'notify';
    return ($quotaGB > 0 && $mode === 'block')
        ? $quotaGB * 1024 * 1024 * 1024
        : 0;
}

/**
 * Limite disque (en Mo) pour la jauge native WHMCS (tblhosting.disklimit).
 * Renvoyée dans TOUS les modes dès qu'un quota est défini (block/bill/notify),
 * pour afficher une jauge cohérente côté client. 0 = pas de limite affichée.
 *
 * @param  array $settings  _sm_getProductSettings()
 * @return int              Mégaoctets (0 = illimité)
 */
function _sm_quotaDiskLimitMB(array $settings): int
{
    $quotaGB = max(0, (int) ($settings['quota_gb'] ?? 0));
    return $quotaGB > 0 ? $quotaGB * 1024 : 0;
}


/**
 * Écrit (upsert) les réglages d'un produit. Utilisé par la page addon d'admin.
 * Valide/borne toutes les valeurs (enum + min/max) avant écriture. (Dans le flux
 * addon, la sauvegarde précède la relecture — le cache statique de
 * _sm_getProductSettings est encore froid pour ce produit, donc la relecture
 * reflète bien la valeur enregistrée.)
 *
 * @param  int   $productId  tblproducts.id
 * @param  array $data       Données brutes du formulaire (clés = colonnes)
 * @return bool              true si l'écriture a réussi
 */
function _sm_saveProductSettings(int $productId, array $data): bool
{
    if ($productId <= 0) {
        return false;
    }

    $model = (string) ($data['billing_model'] ?? 'tiers');
    $mode  = (string) ($data['overage_mode'] ?? 'notify');

    $row = [
        'billing_model'        => in_array($model, SM_BILLING_MODELS, true) ? $model : 'tiers',
        'quota_gb'             => max(0, (int) ($data['quota_gb'] ?? 0)),
        'overage_mode'         => in_array($mode, SM_OVERAGE_MODES, true) ? $mode : 'notify',
        'overage_price'        => max(0.0, (float) ($data['overage_price'] ?? 0)),
        'per_mailbox_price'    => max(0.0, (float) ($data['per_mailbox_price'] ?? 0)),
        'included_gb'          => max(0, (int) ($data['included_gb'] ?? 0)),
        'included_mailboxes'   => max(0, (int) ($data['included_mailboxes'] ?? 0)),
        'notify_threshold_pct' => min(100, max(1, (int) ($data['notify_threshold_pct'] ?? 90))),
    ];

    try {
        _sm_ensureProductSettingsTable();
        Capsule::table('mod_sm_product_settings')->updateOrInsert(
            ['product_id' => $productId],
            $row
        );
        return true;
    } catch (\Throwable $e) {
        logActivity('SmarterMail [product-settings] écriture échouée (produit #'
            . $productId . ') : ' . $e->getMessage());
        return false;
    }
}
