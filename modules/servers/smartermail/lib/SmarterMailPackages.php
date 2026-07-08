<?php
/**
 * ============================================================================
 *  SmarterMailPackages.php — Forfaits : forme normalisée + resolver + convertisseur
 * ============================================================================
 *
 *  Base du gestionnaire de FORFAITS (packages) — objectif revente. Fournit une
 *  UNIQUE forme normalisée décrivant toute la config d'un produit SmarterMail,
 *  résolue depuis deux origines possibles :
 *
 *    1. un FORFAIT nommé (table mod_sm_packages), référencé par le produit via
 *       configoption24 ; OU
 *    2. à défaut (configoption24 vide / forfait absent), les 23 configoptions
 *       historiques + mod_sm_product_settings, converties dans la MÊME forme.
 *
 *  ── PRINCIPES DE SÛRETÉ ─────────────────────────────────────────────────────
 *
 *  • JAMAIS d'exception vers l'appelant : _sm_resolvePackage() retombe sur le
 *    legacy, puis sur les défauts. La facturation/le provisioning ne cassent
 *    jamais à cause de ce module (mêmes garde-fous que _sm_getProductSettings).
 *
 *  • BYTE-IDENTIQUE : forfait vide ⇒ le convertisseur reproduit EXACTEMENT le
 *    comportement historique. Le convertisseur réplique le repli CONSOMMATEUR
 *    (`?? X` / `?:`), PAS le `Default` de smartermail_ConfigOptions() — les deux
 *    divergent parfois (spf_primary, billing_threshold_days, delete_on_terminate).
 *
 *  • VALEURS BRUTES pour les champs à PLANCHER DIVERGENT. Deux options sont lues
 *    différemment selon le site d'appel :
 *       - gb_per_tier (co1)            : module `?? 10` vs hook `?: 10`
 *       - billing_threshold_days (co16): module `max(1) … ?? 1` vs hook `max(0) … ?: 0`
 *    Impossible de capturer ces deux sémantiques dans UNE valeur normalisée. On
 *    renvoie donc la valeur BRUTE et CHAQUE site d'appel conserve son expression
 *    complète (défaut + max) en ne changeant QUE la source
 *    (`$params['configoptionN']`/`$service->configoptionN` → `$pkg['champ']`).
 *    Corollaire : ne JAMAIS unifier ces planchers dans le resolver.
 *
 *  ── PHP ─────────────────────────────────────────────────────────────────────
 *  Cible PHP 8.0+ (le module utilise str_contains/match). Pas d'enum (8.1) :
 *  constantes de classe + tableaux.
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

use WHMCS\Database\Capsule;

// Réutilise la sous-forme disque/facturation existante (_sm_getProductSettings,
// _sm_productSettingsDefaults) et les constantes SM_BILLING_MODELS / SM_OVERAGE_MODES.
require_once __DIR__ . '/SmarterMailProductSettings.php';

// Valeurs autorisées pour la politique DMARC suggérée (whitelist de lecture).
if (!defined('SM_DMARC_POLICIES')) {
    define('SM_DMARC_POLICIES', ['none', 'quarantine', 'reject']);
}


// ============================================================================
//  FORME NORMALISÉE — défauts ultimes (dernier repli si tout échoue)
// ============================================================================

/**
 * Forme normalisée « par défaut » — valeurs sûres équivalentes à un produit sans
 * aucune config. Sert de dernier repli si même la conversion legacy échoue.
 *
 * @return array
 */
function _sm_packageDefaults(): array
{
    return [
        // ── Général / protocoles ──────────────────────────────────────────────
        'offer_eas'              => true,
        'offer_mapi'             => true,
        'price_eas'              => 0.0,
        'price_mapi'             => 0.0,
        'price_bundle'           => 0.0,
        'billing_threshold_days' => 0,      // BRUT — le site applique max(0)/max(1)
        'gb_per_tier'            => 10,      // BRUT
        'domain_path'            => 'C:\\SmarterMail\\Domains\\',
        'outbound_ip'            => 'default',
        'max_users'              => 0,
        'max_domain_aliases'     => 0,
        'delete_on_terminate'    => false,  // legacy : configoption8 sans défaut ⇒ false
        // ── Mot de passe ──────────────────────────────────────────────────────
        'pwd_min_len'            => 8,
        'pwd_require_upper'      => true,
        'pwd_require_lower'      => false,  // aucune règle historique
        'pwd_require_digit'      => true,
        'pwd_require_special'    => true,
        // ── DNS ───────────────────────────────────────────────────────────────
        'spf_primary'            => '',
        'spf_secondary'          => '',
        'autodiscover_host'      => '',     // vide ⇒ serverhostname (au site)
        'srv_target'             => '',     // vide ⇒ serverhostname (au site)
        'dmarc_check'            => true,
        'dmarc_rua'              => '',
        'dmarc_policy'           => 'none',
        // ── Disque & facturation (sous-forme _sm_computeBaseCharge) ────────────
        'billing_model'          => 'tiers',
        'quota_gb'               => 0,
        'overage_mode'           => 'notify',
        'overage_price'          => 0.0,
        'notify_threshold_pct'   => 90,
        'per_mailbox_price'      => 0.0,
        'included_gb'            => 0,
        'included_mailboxes'     => 0,
        'max_mailbox_size_gb'    => 0,      // NOUVEAU — 0 = illimité (no-op)
        // ── Meta ──────────────────────────────────────────────────────────────
        '_source'                => 'legacy',
        '_package_id'            => null,
    ];
}


// ============================================================================
//  TABLES (auto-création — modèle _sm_ensureProductSettingsTable)
// ============================================================================

/**
 * Crée mod_sm_packages si absente. Cache statique : vérifié une fois par exécution.
 * Non bloquant : en cas d'échec, _sm_getPackage() renverra null → repli legacy.
 */
function _sm_ensurePackagesTable(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        if (!Capsule::schema()->hasTable('mod_sm_packages')) {
            Capsule::schema()->create('mod_sm_packages', function ($t) {
                $t->increments('id');
                $t->string('name', 191);
                $t->boolean('is_deleted')->default(0);   // soft-delete (forfait référencé)

                // Général / protocoles
                $t->boolean('offer_eas')->default(1);
                $t->boolean('offer_mapi')->default(1);
                $t->decimal('price_eas', 10, 2)->default(0);
                $t->decimal('price_mapi', 10, 2)->default(0);
                $t->decimal('price_bundle', 10, 2)->default(0);
                $t->integer('billing_threshold_days')->unsigned()->default(0);
                $t->integer('gb_per_tier')->unsigned()->default(10);
                $t->string('domain_path', 191)->default('C:\\SmarterMail\\Domains\\');
                $t->string('outbound_ip', 64)->default('default');
                $t->integer('max_users')->unsigned()->default(0);
                $t->integer('max_domain_aliases')->unsigned()->default(0);
                // NOUVEAU forfait : suppression à la résiliation activée par intention
                // (le repli legacy, lui, reproduit le quirk configoption8 = false).
                $t->boolean('delete_on_terminate')->default(1);

                // Mot de passe
                $t->smallInteger('pwd_min_len')->unsigned()->default(8);
                $t->boolean('pwd_require_upper')->default(1);
                $t->boolean('pwd_require_lower')->default(0);
                $t->boolean('pwd_require_digit')->default(1);
                $t->boolean('pwd_require_special')->default(1);

                // DNS
                $t->string('spf_primary', 191)->default('');
                $t->string('spf_secondary', 191)->default('');
                $t->string('autodiscover_host', 191)->default('');
                $t->string('srv_target', 191)->default('');
                $t->boolean('dmarc_check')->default(1);
                $t->string('dmarc_rua', 191)->default('');
                $t->string('dmarc_policy', 20)->default('none');

                // Disque & facturation
                $t->string('billing_model', 20)->default('tiers');
                $t->integer('quota_gb')->unsigned()->default(0);
                $t->integer('max_mailbox_size_gb')->unsigned()->default(0);
                $t->string('overage_mode', 10)->default('notify');
                $t->decimal('overage_price', 10, 2)->default(0);
                $t->smallInteger('notify_threshold_pct')->unsigned()->default(90);
                $t->decimal('per_mailbox_price', 10, 2)->default(0);
                $t->integer('included_gb')->unsigned()->default(0);
                $t->integer('included_mailboxes')->unsigned()->default(0);

                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] création table mod_sm_packages échouée : ' . $e->getMessage());
    }
}

/**
 * Crée mod_sm_settings (clé/valeur JSON) si absente — réglages GLOBAUX du
 * revendeur (nameservers, disponibilité EAS/MAPI, exemples de dé-branding).
 */
function _sm_ensureSettingsTable(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        if (!Capsule::schema()->hasTable('mod_sm_settings')) {
            Capsule::schema()->create('mod_sm_settings', function ($t) {
                $t->string('setting_key', 100)->primary();
                $t->longText('setting_value')->nullable();
            });
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] création table mod_sm_settings échouée : ' . $e->getMessage());
    }
}


// ============================================================================
//  RÉGLAGES GLOBAUX (clé/valeur JSON) — lecture/écriture tolérantes
// ============================================================================

/**
 * Lit un réglage global (JSON décodé). Repli sur $default en cas d'absence,
 * de JSON invalide ou d'erreur. Ne lève jamais.
 *
 * @param  string $key
 * @param  mixed  $default
 * @return mixed
 */
function _sm_getGlobalSetting(string $key, $default = null)
{
    try {
        _sm_ensureSettingsTable();
        $val = Capsule::table('mod_sm_settings')->where('setting_key', $key)->value('setting_value');
        if ($val === null) {
            return $default;
        }
        $decoded = json_decode((string) $val, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * Écrit (upsert) un réglage global en JSON.
 *
 * @param  string $key
 * @param  mixed  $value  Encodé en JSON.
 * @return bool
 */
function _sm_setGlobalSetting(string $key, $value): bool
{
    try {
        _sm_ensureSettingsTable();
        Capsule::table('mod_sm_settings')->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => json_encode($value)]
        );
        return true;
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] écriture réglage global "' . $key . '" échouée : ' . $e->getMessage());
        return false;
    }
}


// ============================================================================
//  CONVERTISSEUR legacy → forme normalisée (BYTE-IDENTIQUE)
// ============================================================================

/**
 * Convertit les configoptions historiques (co1-23) + mod_sm_product_settings dans
 * la forme normalisée, en reproduisant EXACTEMENT les expressions consommateur.
 *
 * @param  array $co   ['configoption1'=>…, … 'configoption24'=>…] (valeurs brutes, ?null)
 * @param  array $ctx  ['pid'=>int, 'serverhostname'=>string]
 * @return array       Forme normalisée (voir _sm_packageDefaults()).
 */
function _sm_legacyConfigToPackage(array $co, array $ctx = []): array
{
    // Getter brut : retourne null si la clé est absente.
    $g = static function (string $k) use ($co) {
        return $co[$k] ?? null;
    };

    // Sous-forme disque/facturation — copiée verbatim de _sm_getProductSettings()
    // pour que _sm_computeBaseCharge()/_sm_quotaMaxSizeBytes() voient des clés et
    // des valeurs IDENTIQUES au comportement historique.
    $pid = (int) ($ctx['pid'] ?? 0);
    $ps  = _sm_getProductSettings($pid);

    return [
        // ── Général / protocoles ──────────────────────────────────────────────
        // Offres EAS/MAPI : ($x ?? 'on') === 'on'  (smartermail.php:1687-1688,2833-2834,4051-4052,4667-4668)
        'offer_eas'              => (($g('configoption14') ?? 'on') === 'on'),
        'offer_mapi'             => (($g('configoption15') ?? 'on') === 'on'),
        // Prix : (float)($x ?? 0). Le hook lit `?: 0` (hooks.php:355-357) — même
        // résultat que `?? 0` une fois casté en float (les cas limites → 0.0).
        'price_eas'              => (float) ($g('configoption2') ?? 0),
        'price_mapi'             => (float) ($g('configoption3') ?? 0),
        'price_bundle'           => (float) ($g('configoption4') ?? 0),
        // BRUT — planchers/défauts divergents conservés aux sites d'appel.
        'billing_threshold_days' => $g('configoption16'),
        'gb_per_tier'            => $g('configoption1'),
        // Chemin : ($x ?? défaut) ; le rtrim + append reste au site (smartermail.php:1582).
        'domain_path'            => ($g('configoption5') ?? 'C:\\SmarterMail\\Domains\\'),
        'outbound_ip'            => ($g('configoption6') ?? 'default'),
        'max_users'              => max(0, (int) ($g('configoption7') ?? 0)),
        'max_domain_aliases'     => max(0, (int) ($g('configoption17') ?? 0)),
        // configoption8 est lu SANS `??` (smartermail.php:1828) : produit antérieur ⇒ false.
        'delete_on_terminate'    => (($g('configoption8') ?? null) === 'on'),
        // ── Mot de passe ──────────────────────────────────────────────────────
        // Tous les sites lisent `(int)($co9 ?? 8)` ; seul le PLANCHER diffère
        // (admin max(8) / boîte max(1)) → conservé au site.
        'pwd_min_len'            => (int) ($g('configoption9') ?? 8),
        'pwd_require_upper'      => (($g('configoption10') ?? 'on') === 'on'),
        'pwd_require_lower'      => false,  // aucune règle historique (byte-identique)
        'pwd_require_digit'      => (($g('configoption11') ?? 'on') === 'on'),
        'pwd_require_special'    => (($g('configoption12') ?? 'on') === 'on'),
        // ── DNS ───────────────────────────────────────────────────────────────
        'spf_primary'            => trim((string) ($g('configoption13') ?? '')),
        'spf_secondary'          => trim((string) ($g('configoption18') ?? '')),
        // `?: $serverHost` reste au site (SmarterMailDnsCheck.php:381-382).
        'autodiscover_host'      => strtolower(trim((string) ($g('configoption19') ?? ''))),
        'srv_target'             => strtolower(trim((string) ($g('configoption20') ?? ''))),
        'dmarc_check'            => (($g('configoption21') ?? 'on') === 'on'),
        'dmarc_rua'              => trim((string) ($g('configoption22') ?? '')),
        'dmarc_policy'           => trim((string) ($g('configoption23') ?? 'none')),
        // ── Disque & facturation (verbatim de _sm_getProductSettings) ─────────
        'billing_model'          => $ps['billing_model'],
        'quota_gb'               => $ps['quota_gb'],
        'overage_mode'           => $ps['overage_mode'],
        'overage_price'          => $ps['overage_price'],
        'notify_threshold_pct'   => $ps['notify_threshold_pct'],
        'per_mailbox_price'      => $ps['per_mailbox_price'],
        'included_gb'            => $ps['included_gb'],
        'included_mailboxes'     => $ps['included_mailboxes'],
        'max_mailbox_size_gb'    => 0,  // NOUVEAU — no-op en legacy
        // ── Meta ──────────────────────────────────────────────────────────────
        '_source'                => 'legacy',
        '_package_id'            => null,
    ];
}


// ============================================================================
//  LECTURE d'un forfait (mod_sm_packages) → forme normalisée
// ============================================================================

/**
 * Charge et normalise un forfait par id. Cache statique par id. Renvoie null si
 * id invalide, forfait absent/supprimé, ou erreur (→ l'appelant retombe legacy).
 *
 * @param  int $id
 * @return array|null
 */
function _sm_getPackage(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    static $cache = [];
    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }

    try {
        _sm_ensurePackagesTable();
        $row = Capsule::table('mod_sm_packages')
            ->where('id', $id)
            ->where('is_deleted', 0)
            ->first();

        if (!$row) {
            return $cache[$id] = null;
        }

        $model  = (string) ($row->billing_model ?? 'tiers');
        $mode   = (string) ($row->overage_mode ?? 'notify');
        $policy = (string) ($row->dmarc_policy ?? 'none');

        return $cache[$id] = [
            'offer_eas'              => (bool) $row->offer_eas,
            'offer_mapi'             => (bool) $row->offer_mapi,
            'price_eas'              => max(0.0, (float) $row->price_eas),
            'price_mapi'             => max(0.0, (float) $row->price_mapi),
            'price_bundle'           => max(0.0, (float) $row->price_bundle),
            'billing_threshold_days' => max(0, (int) $row->billing_threshold_days),
            'gb_per_tier'            => max(0, (int) $row->gb_per_tier),
            'domain_path'            => (string) $row->domain_path,
            'outbound_ip'            => (string) $row->outbound_ip,
            'max_users'              => max(0, (int) $row->max_users),
            'max_domain_aliases'     => max(0, (int) $row->max_domain_aliases),
            'delete_on_terminate'    => (bool) $row->delete_on_terminate,
            'pwd_min_len'            => max(0, (int) $row->pwd_min_len),
            'pwd_require_upper'      => (bool) $row->pwd_require_upper,
            'pwd_require_lower'      => (bool) $row->pwd_require_lower,
            'pwd_require_digit'      => (bool) $row->pwd_require_digit,
            'pwd_require_special'    => (bool) $row->pwd_require_special,
            'spf_primary'            => trim((string) $row->spf_primary),
            'spf_secondary'          => trim((string) $row->spf_secondary),
            'autodiscover_host'      => strtolower(trim((string) $row->autodiscover_host)),
            'srv_target'             => strtolower(trim((string) $row->srv_target)),
            'dmarc_check'            => (bool) $row->dmarc_check,
            'dmarc_rua'              => trim((string) $row->dmarc_rua),
            'dmarc_policy'           => in_array($policy, SM_DMARC_POLICIES, true) ? $policy : 'none',
            'billing_model'          => in_array($model, SM_BILLING_MODELS, true) ? $model : 'tiers',
            'quota_gb'               => max(0, (int) $row->quota_gb),
            'overage_mode'           => in_array($mode, SM_OVERAGE_MODES, true) ? $mode : 'notify',
            'overage_price'          => max(0.0, (float) $row->overage_price),
            'notify_threshold_pct'   => min(100, max(1, (int) $row->notify_threshold_pct)),
            'per_mailbox_price'      => max(0.0, (float) $row->per_mailbox_price),
            'included_gb'            => max(0, (int) $row->included_gb),
            'included_mailboxes'     => max(0, (int) $row->included_mailboxes),
            'max_mailbox_size_gb'    => max(0, (int) $row->max_mailbox_size_gb),
            '_source'                => 'package',
            '_package_id'            => $id,
        ];
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] lecture forfait #' . $id . ' échouée : ' . $e->getMessage());
        return $cache[$id] = null;
    }
}


// ============================================================================
//  RESOLVER (point d'entrée unique) + adaptateurs de contexte
// ============================================================================

/**
 * Extrait l'id de forfait depuis la valeur de configoption24. Tolère la valeur
 * brute ("3") comme un libellé ("#3 — Argent") → survit aux renommages.
 *
 * @param  string $raw
 * @return int    0 si aucun.
 */
function _sm_parsePackageId(string $raw): int
{
    $raw = trim($raw);
    if ($raw === '') {
        return 0;
    }
    if (preg_match('/^#?(\d+)/', $raw, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/**
 * POINT D'ENTRÉE. Résout la config normalisée depuis une carte de configoptions
 * + un contexte. Ne lève JAMAIS : forfait → legacy → défauts.
 *
 * @param  array $co   ['configoption1'..'configoption24'] (brutes, ?null)
 * @param  array $ctx  ['pid'=>int, 'serverhostname'=>string]
 * @return array       Forme normalisée.
 */
function _sm_resolvePackage(array $co, array $ctx = []): array
{
    try {
        $pkgId = _sm_parsePackageId((string) ($co['configoption24'] ?? ''));
        if ($pkgId > 0) {
            $pkg = _sm_getPackage($pkgId);
            if ($pkg !== null) {
                return $pkg;
            }
            // Forfait référencé mais absent/supprimé → repli legacy (journalisé 1×).
            logActivity('SmarterMail [packages] forfait #' . $pkgId
                . ' référencé mais introuvable → repli sur la config héritée.');
        }
        return _sm_legacyConfigToPackage($co, $ctx);
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] resolve EXCEPTION → repli : ' . $e->getMessage());
        try {
            return _sm_legacyConfigToPackage($co, $ctx);
        } catch (\Throwable $e2) {
            return _sm_packageDefaults();
        }
    }
}

/**
 * Adaptateur — contexte MODULE : $params (fusionnés par service) fournis par WHMCS
 * aux fonctions du module serveur.
 *
 * @param  array $params
 * @return array
 */
function _sm_packageFromParams(array $params): array
{
    $co = [];
    for ($i = 1; $i <= 24; $i++) {
        $co['configoption' . $i] = $params['configoption' . $i] ?? null;
    }
    $ctx = [
        'pid'            => (int) ($params['pid'] ?? 0),
        'serverhostname' => (string) ($params['serverhostname'] ?? ''),
    ];
    return _sm_resolvePackage($co, $ctx);
}

/**
 * Adaptateur — contexte HOOK : ligne $service issue d'une jointure tblproducts
 * (le hook InvoiceCreation lit $service->configoptionN, pas $params). Le SELECT
 * du hook doit exposer configoption1..24 + packageid + serverhostname.
 *
 * @param  object $row
 * @return array
 */
function _sm_packageFromServiceRow(object $row): array
{
    $co = [];
    for ($i = 1; $i <= 24; $i++) {
        $k = 'configoption' . $i;
        $co[$k] = $row->$k ?? null;
    }
    $ctx = [
        'pid'            => (int) ($row->packageid ?? 0),
        'serverhostname' => (string) ($row->serverhostname ?? ''),
    ];
    return _sm_resolvePackage($co, $ctx);
}


// ============================================================================
//  DROPDOWN WHMCS (configoption24) — choix de forfait
// ============================================================================

/**
 * Choix pour le dropdown configoption24 :
 *   ['' => '(Hérité — options ci-dessus)', '<id>' => '#<id> — <nom>', …]
 *
 * Tableau associatif (clé = id STABLE) → survit au renommage d'un forfait, et
 * insensible aux virgules dans les noms. _sm_parsePackageId() récupère l'id que
 * WHMCS stocke la clé OU le libellé. En cas d'absence de table / d'erreur, seule
 * l'option « héritée » est renvoyée → la page produit ne casse jamais.
 *
 * @return array<string,string>
 */
function _sm_configOptionPackageChoices(): array
{
    $choices = ['' => '(Hérité — options ci-dessus)'];
    try {
        _sm_ensurePackagesTable();
        $rows = Capsule::table('mod_sm_packages')
            ->where('is_deleted', 0)
            ->orderBy('name')
            ->get(['id', 'name']);
        foreach ($rows as $r) {
            $choices[(string) $r->id] = '#' . $r->id . ' — ' . $r->name;
        }
    } catch (\Throwable $e) {
        // Table absente / erreur → uniquement l'option héritée (aucune casse).
    }
    return $choices;
}


// ============================================================================
//  CRUD forfaits (utilisé par la page addon d'administration)
// ============================================================================

/**
 * Liste les forfaits actifs (non soft-supprimés), triés par nom. Lignes brutes.
 *
 * @return array<int,object>
 */
function _sm_listPackages(): array
{
    try {
        _sm_ensurePackagesTable();
        return Capsule::table('mod_sm_packages')
            ->where('is_deleted', 0)
            ->orderBy('name')
            ->get()
            ->all();
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Écrit (insert si $id vide, sinon update) un forfait depuis les données brutes
 * du formulaire addon. Valide/borne toutes les valeurs. Le « type de forfait »
 * (projection UI) est reprojeté sur overage_mode ; billing_model reste 'tiers'.
 *
 * @param  int|null $id    null/0 = création ; >0 = mise à jour.
 * @param  array    $data  Données brutes du formulaire ($_POST).
 * @return int             Id du forfait (>0) ou 0 en cas d'échec (nom manquant / erreur).
 */
function _sm_savePackage(?int $id, array $data): int
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        return 0; // nom obligatoire
    }

    // Type de forfait (usage_notify / usage_bill / blocked) → overage_mode.
    $planType    = (string) ($data['plan_type'] ?? 'usage_notify');
    $overageMode = 'notify';
    if ($planType === 'blocked') {
        $overageMode = 'block';
    } elseif ($planType === 'usage_bill') {
        $overageMode = 'bill';
    }

    $dmarcPolicy = (string) ($data['dmarc_policy'] ?? 'none');

    $row = [
        'name'                   => mb_substr($name, 0, 190),
        // Général / protocoles
        'offer_eas'              => !empty($data['offer_eas']) ? 1 : 0,
        'offer_mapi'             => !empty($data['offer_mapi']) ? 1 : 0,
        'price_eas'              => max(0.0, (float) ($data['price_eas'] ?? 0)),
        'price_mapi'             => max(0.0, (float) ($data['price_mapi'] ?? 0)),
        'price_bundle'           => max(0.0, (float) ($data['price_bundle'] ?? 0)),
        'billing_threshold_days' => max(0, (int) ($data['billing_threshold_days'] ?? 0)),
        'gb_per_tier'            => max(1, (int) ($data['gb_per_tier'] ?? 10)),
        'domain_path'            => mb_substr(trim((string) ($data['domain_path'] ?? 'C:\\SmarterMail\\Domains\\')), 0, 190),
        'outbound_ip'            => mb_substr(trim((string) ($data['outbound_ip'] ?? 'default')), 0, 63),
        'max_users'              => max(0, (int) ($data['max_users'] ?? 0)),
        'max_domain_aliases'     => max(0, (int) ($data['max_domain_aliases'] ?? 0)),
        'delete_on_terminate'    => !empty($data['delete_on_terminate']) ? 1 : 0,
        // Mot de passe
        'pwd_min_len'            => max(1, (int) ($data['pwd_min_len'] ?? 8)),
        'pwd_require_upper'      => !empty($data['pwd_require_upper']) ? 1 : 0,
        'pwd_require_lower'      => !empty($data['pwd_require_lower']) ? 1 : 0,
        'pwd_require_digit'      => !empty($data['pwd_require_digit']) ? 1 : 0,
        'pwd_require_special'    => !empty($data['pwd_require_special']) ? 1 : 0,
        // DNS
        'spf_primary'            => mb_substr(trim((string) ($data['spf_primary'] ?? '')), 0, 190),
        'spf_secondary'          => mb_substr(trim((string) ($data['spf_secondary'] ?? '')), 0, 190),
        'autodiscover_host'      => mb_substr(strtolower(trim((string) ($data['autodiscover_host'] ?? ''))), 0, 190),
        'srv_target'             => mb_substr(strtolower(trim((string) ($data['srv_target'] ?? ''))), 0, 190),
        'dmarc_check'            => !empty($data['dmarc_check']) ? 1 : 0,
        'dmarc_rua'              => mb_substr(trim((string) ($data['dmarc_rua'] ?? '')), 0, 190),
        'dmarc_policy'           => in_array($dmarcPolicy, SM_DMARC_POLICIES, true) ? $dmarcPolicy : 'none',
        // Disque & facturation
        'billing_model'          => 'tiers',
        'quota_gb'               => max(0, (int) ($data['quota_gb'] ?? 0)),
        'max_mailbox_size_gb'    => max(0, (int) ($data['max_mailbox_size_gb'] ?? 0)),
        'overage_mode'           => $overageMode,
        'overage_price'          => max(0.0, (float) ($data['overage_price'] ?? 0)),
        'notify_threshold_pct'   => min(100, max(1, (int) ($data['notify_threshold_pct'] ?? 90))),
    ];

    try {
        _sm_ensurePackagesTable();
        $now = date('Y-m-d H:i:s');
        if ($id && $id > 0) {
            $row['updated_at'] = $now;
            Capsule::table('mod_sm_packages')->where('id', $id)->update($row);
            return $id;
        }
        $row['is_deleted'] = 0;
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        return (int) Capsule::table('mod_sm_packages')->insertGetId($row);
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] enregistrement forfait échoué : ' . $e->getMessage());
        return 0;
    }
}

/**
 * Soft-delete d'un forfait (is_deleted=1). Ne casse PAS les produits qui le
 * référencent encore : le resolver retombe alors sur la config héritée (+ log).
 *
 * @param  int $id
 * @return bool
 */
function _sm_deletePackage(int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    try {
        _sm_ensurePackagesTable();
        Capsule::table('mod_sm_packages')->where('id', $id)->update([
            'is_deleted' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    } catch (\Throwable $e) {
        logActivity('SmarterMail [packages] suppression forfait #' . $id . ' échouée : ' . $e->getMessage());
        return false;
    }
}

/**
 * Nameservers par défaut (valeurs historiques Astral) — repli quand aucun réglage
 * global 'provider_nameservers' n'est défini. Un revendeur les surcharge via la
 * page addon (Réglages globaux). N'affecte QUE l'onglet pré-sélectionné du guide
 * DNS (aucune conséquence fonctionnelle).
 *
 * @return array<string,string[]>
 */
function _sm_defaultNameservers(): array
{
    return [
        'cpanel'      => [
            'ns3.astralinternet.com', 'ns4.astralinternet.com',
            'ns3.hosting-management.com', 'ns4.hosting-management.com',
        ],
        'plesk'       => ['ns20.astralinternet.com', 'ns21.astralinternet.com'],
        'clientspace' => ['zone1.astralinternet.com', 'zone2.astralinternet.com', 'zone3.astralinternet.com'],
    ];
}
