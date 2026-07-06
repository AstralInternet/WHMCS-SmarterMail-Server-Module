<?php
/**
 * ============================================================================
 *  smartermail_billing — Addon WHMCS : facturation & quotas par produit
 * ============================================================================
 *
 * Page d'administration pour éditer mod_sm_product_settings (modèle de
 * facturation + quota disque bloquant) par produit SmarterMail — les 23/24
 * slots configoption étant consommés, ces réglages passent par cette table
 * dédiée (design AUDIT_COMPLET_smartermail.md §3.3).
 *
 * Fournit aussi « Appliquer aux services actifs » : pousse maxSize au serveur
 * pour tout le parc existant d'un produit (les nouveaux comptes et les
 * changements de forfait reçoivent maxSize automatiquement, mais pas le parc
 * déjà provisionné avant l'activation d'un quota).
 *
 * Installation : Configuration → Modules complémentaires → activer, puis
 * donner l'accès au(x) rôle(s) d'admin voulu(s).
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

use WHMCS\Database\Capsule;

// Bibliothèque partagée avec le module serveur (helpers de réglages + constantes).
require_once __DIR__ . '/../../servers/smartermail/lib/SmarterMailProductSettings.php';


/**
 * Métadonnées de l'addon (affichées dans la liste des modules complémentaires).
 */
function smartermail_billing_config()
{
    return [
        'name'        => 'SmarterMail — Facturation & Quotas',
        'description' => 'Configure le modèle de facturation et le quota disque bloquant '
            . 'par produit SmarterMail (table mod_sm_product_settings).',
        'version'     => '1.0',
        'author'      => 'Astral Internet',
        'fields'      => [],
    ];
}

/**
 * Activation : s'assurer que la table des réglages existe.
 */
function smartermail_billing_activate()
{
    try {
        _sm_ensureProductSettingsTable();
        return ['status' => 'success', 'description' => 'Module activé — la table des réglages est prête.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Erreur d\'activation : ' . $e->getMessage()];
    }
}

/**
 * Désactivation : on NE supprime PAS la table (préservation des réglages).
 */
function smartermail_billing_deactivate()
{
    return ['status' => 'success', 'description' => 'Module désactivé — les réglages produits sont conservés.'];
}

/**
 * Petite alerte Bootstrap (thème admin WHMCS).
 */
function _sm_billing_alert(string $type, string $msg): string
{
    return '<div class="alert alert-' . htmlspecialchars($type) . '">' . htmlspecialchars($msg) . '</div>';
}

/**
 * Applique la limite disque (maxSize) au serveur SmarterMail pour TOUS les
 * services actifs d'un produit. Lecture directe des identifiants serveur (comme
 * tools/diag_*), token SA par serveur mis en cache par le wrapper.
 *
 * @param  int   $productId
 * @return array ['ok'=>int, 'fail'=>int, 'total'=>int]
 */
function _sm_billing_applyLimits(int $productId): array
{
    require_once __DIR__ . '/../../servers/smartermail/lib/SmarterMailApi.php';

    $settings     = _sm_getProductSettings($productId);
    $maxSizeBytes = _sm_quotaMaxSizeBytes($settings);

    $services = Capsule::table('tblhosting')
        ->join('tblservers', 'tblhosting.server', '=', 'tblservers.id')
        ->where('tblhosting.packageid', $productId)
        ->where('tblhosting.domainstatus', 'Active')
        ->get([
            'tblhosting.domain',
            'tblservers.hostname', 'tblservers.secure', 'tblservers.port',
            'tblservers.username as sa_user', 'tblservers.password as sa_pass',
        ]);

    $ok = 0;
    $fail = 0;
    foreach ($services as $svc) {
        $domain = strtolower(trim((string) $svc->domain));
        if ($domain === '' || empty($svc->hostname)) {
            $fail++;
            continue;
        }
        try {
            $api = new SmarterMailApi(
                (string) $svc->hostname,
                (bool) $svc->secure,
                (int) ($svc->port ?: ($svc->secure ? 443 : 80))
            );
            // Décodage du mot de passe serveur (brut + html_entity_decode, comme le hook).
            $pwd     = html_entity_decode((string) decrypt($svc->sa_pass), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $saToken = $api->loginSysAdmin((string) $svc->sa_user, $pwd);
            if (!$saToken) {
                $fail++;
                continue;
            }
            $resp = $api->setDomainSettings($domain, ['maxSize' => $maxSizeBytes], $saToken);
            if ($resp['success'] ?? false) {
                $ok++;
            } else {
                $fail++;
            }
        } catch (\Throwable $e) {
            $fail++;
        }
    }

    logActivity(sprintf(
        'SmarterMail [billing-addon] Application maxSize produit #%d : %d OK, %d échec(s) / %d actif(s).',
        $productId, $ok, $fail, count($services)
    ));
    return ['ok' => $ok, 'fail' => $fail, 'total' => count($services)];
}

/**
 * Rend le formulaire d'un produit (panneau éditable).
 */
function _sm_billing_productForm($product, array $s, int $activeCount, string $csrf): string
{
    $pid  = (int) $product->id;
    $name = htmlspecialchars((string) $product->name);
    $e    = fn($k) => htmlspecialchars((string) ($s[$k] ?? ''));

    $modelOpt = function (string $val, string $label) use ($s): string {
        $sel = (($s['billing_model'] ?? 'tiers') === $val) ? ' selected' : '';
        return '<option value="' . $val . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    };
    $modeOpt = function (string $val, string $label) use ($s): string {
        $sel = (($s['overage_mode'] ?? 'notify') === $val) ? ' selected' : '';
        return '<option value="' . $val . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    };

    $h  = '<div class="panel panel-default"><div class="panel-heading"><strong>' . $name
        . '</strong> <span class="label label-default">#' . $pid . '</span> '
        . '<span class="text-muted" style="margin-left:8px;">' . $activeCount . ' service(s) actif(s)</span></div>';
    $h .= '<div class="panel-body">';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" class="form-horizontal">';
    $h .= '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="savesettings">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    // Champs réservés à l'étape 2b (per_mailbox/hybrid) : préservés en hidden.
    $h .= '<input type="hidden" name="per_mailbox_price" value="' . $e('per_mailbox_price') . '">';
    $h .= '<input type="hidden" name="included_gb" value="' . $e('included_gb') . '">';
    $h .= '<input type="hidden" name="included_mailboxes" value="' . $e('included_mailboxes') . '">';

    $row = function (string $label, string $field) {
        return '<div class="form-group"><label class="col-sm-4 control-label">' . $label
            . '</label><div class="col-sm-4">' . $field . '</div></div>';
    };

    $h .= $row('Modèle de facturation',
        '<select name="billing_model" class="form-control">'
        . $modelOpt('tiers', 'Par tranches disque (historique)')
        . $modelOpt('flat', 'Forfait fixe')
        . '</select>');
    $h .= $row('Quota disque (Go)',
        '<input type="number" min="0" step="1" name="quota_gb" value="' . $e('quota_gb')
        . '" class="form-control"> <span class="help-block" style="margin:4px 0 0;">0 = illimité (aucun plafond).</span>');
    $h .= $row('Mode de dépassement',
        '<select name="overage_mode" class="form-control">'
        . $modeOpt('block', 'Bloquer (maxSize serveur)')
        . $modeOpt('bill', 'Facturer l\'excédent')
        . $modeOpt('notify', 'Alerter seulement')
        . '</select>');
    $h .= $row('Prix tranche excédentaire ($)',
        '<input type="number" min="0" step="0.01" name="overage_price" value="' . $e('overage_price')
        . '" class="form-control"> <span class="help-block" style="margin:4px 0 0;">Mode « facturer » uniquement.</span>');
    $h .= $row('Seuil d\'alerte (%)',
        '<input type="number" min="1" max="100" step="1" name="notify_threshold_pct" value="' . $e('notify_threshold_pct')
        . '" class="form-control">');

    $h .= '<div class="form-group"><div class="col-sm-offset-4 col-sm-8">'
        . '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>'
        . '</div></div>';
    $h .= '</form>';

    // Formulaire séparé : appliquer les limites au parc existant.
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" '
        . 'onsubmit="return confirm(\'Appliquer la limite disque de ce produit à ses ' . $activeCount
        . ' service(s) actif(s) ?\');" style="margin-top:6px;">';
    $h .= '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="applylimits">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    $h .= '<button type="submit" class="btn btn-default btn-sm"' . ($activeCount > 0 ? '' : ' disabled')
        . '><i class="fas fa-server"></i> Appliquer aux services actifs</button>';
    $h .= ' <span class="text-muted">Pousse <code>maxSize</code> au serveur (mode « bloquer » uniquement ; sinon illimité).</span>';
    $h .= '</form>';

    $h .= '</div></div>';
    return $h;
}

/**
 * Sortie de la page d'administration de l'addon.
 */
function smartermail_billing_output($vars)
{
    require_once __DIR__ . '/../../servers/smartermail/lib/SmarterMailProductSettings.php';

    // ── CSRF : jeton auto-géré en session (l'accès admin est déjà restreint,
    //    mais on protège en plus les POST mutatifs). ──
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($_SESSION['sm_billing_csrf'])) {
        $_SESSION['sm_billing_csrf'] = bin2hex(random_bytes(16));
    }
    $csrf   = (string) $_SESSION['sm_billing_csrf'];
    $notice = '';

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            $notice = _sm_billing_alert('danger', 'Jeton de sécurité invalide. Rechargez la page et réessayez.');
        } else {
            $action = (string) ($_POST['action'] ?? '');
            $pid    = (int) ($_POST['product_id'] ?? 0);

            if ($action === 'savesettings' && $pid > 0) {
                $notice = _sm_saveProductSettings($pid, $_POST)
                    ? _sm_billing_alert('success', 'Réglages enregistrés pour le produit #' . $pid . '.')
                    : _sm_billing_alert('danger', 'Échec de l\'enregistrement (voir le journal d\'activité).');
            } elseif ($action === 'applylimits' && $pid > 0) {
                $r = _sm_billing_applyLimits($pid);
                $notice = _sm_billing_alert(
                    $r['fail'] > 0 ? 'warning' : 'success',
                    sprintf('Limites appliquées : %d OK, %d échec(s) sur %d service(s) actif(s).',
                        $r['ok'], $r['fail'], $r['total'])
                );
            }
        }
    }

    $products = Capsule::table('tblproducts')
        ->where('servertype', 'smartermail')
        ->orderBy('name')
        ->get(['id', 'name']);

    $html  = $notice;
    $html .= '<p>Réglages de facturation par produit SmarterMail. Sans réglage, un produit conserve '
        . 'le comportement historique (par tranches disque, aucun quota).</p>';
    $html .= '<ul class="text-muted" style="margin-bottom:16px;">'
        . '<li><strong>Bloquer</strong> : <code>maxSize</code> poussé au serveur — le stockage au-delà du quota est refusé.</li>'
        . '<li><strong>Facturer l\'excédent</strong> : la base couvre le quota, l\'excédent est facturé au prix de tranche excédentaire.</li>'
        . '<li><strong>Alerter seulement</strong> : aucun effet sur la facture ni le serveur — jauge + alerte au-delà du seuil.</li>'
        . '</ul>';

    if (count($products) === 0) {
        $html .= _sm_billing_alert('info', 'Aucun produit avec le type de serveur « smartermail » n\'a été trouvé.');
        return $html;
    }

    foreach ($products as $p) {
        $s           = _sm_getProductSettings((int) $p->id);
        $activeCount = (int) Capsule::table('tblhosting')
            ->where('packageid', $p->id)
            ->where('domainstatus', 'Active')
            ->count();
        $html .= _sm_billing_productForm($p, $s, $activeCount, $csrf);
    }

    return $html;
}
