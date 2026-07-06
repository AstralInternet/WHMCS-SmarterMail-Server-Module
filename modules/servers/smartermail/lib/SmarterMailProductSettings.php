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
