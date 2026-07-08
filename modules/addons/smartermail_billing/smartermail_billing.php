<?php
/**
 * ============================================================================
 *  smartermail_billing — Addon WHMCS : Gestionnaire de FORFAITS SmarterMail
 * ============================================================================
 *
 *  Interface d'administration à onglets pour :
 *    • créer / modifier / supprimer des FORFAITS réutilisables (table
 *      mod_sm_packages) — protocoles, mot de passe, DNS, disque & facturation ;
 *    • éditer les RÉGLAGES GLOBAUX du revendeur (disponibilité EAS/MAPI,
 *      nameservers du guide DNS) — table mod_sm_settings ;
 *    • conserver l'édition « héritée » des réglages de facturation PAR PRODUIT
 *      (table mod_sm_product_settings) pour les produits non convertis en forfait.
 *
 *  Un produit référence un forfait via l'option de configuration « Forfait »
 *  (configoption24). Sans forfait, il conserve son comportement historique.
 *
 *  Cette page N'ÉCRIT QUE dans les tables du module (jamais dans les tables WHMCS
 *  cœur). Installation : Configuration → Modules complémentaires → activer.
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

use WHMCS\Database\Capsule;

// Bibliothèques partagées avec le module serveur (helpers + constantes).
require_once __DIR__ . '/../../servers/smartermail/lib/SmarterMailProductSettings.php';
require_once __DIR__ . '/../../servers/smartermail/lib/SmarterMailPackages.php';


/**
 * Métadonnées de l'addon (liste des modules complémentaires).
 */
function smartermail_billing_config()
{
    return [
        'name'        => 'SmarterMail — Forfaits',
        'description' => 'Crée et gère les forfaits SmarterMail (protocoles, mot de passe, DNS, '
            . 'disque & facturation) dans une interface à onglets, plus les réglages globaux '
            . 'et les réglages de facturation par produit (hérité).',
        'version'     => '2.0',
        'author'      => 'Astral Internet',
        'fields'      => [],
    ];
}

/**
 * Activation : s'assurer que les tables du module existent.
 */
function smartermail_billing_activate()
{
    try {
        _sm_ensureProductSettingsTable();
        _sm_ensurePackagesTable();
        _sm_ensureSettingsTable();
        return ['status' => 'success', 'description' => 'Module activé — tables prêtes (forfaits, réglages globaux, réglages produits).'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Erreur d\'activation : ' . $e->getMessage()];
    }
}

/**
 * Désactivation : on NE supprime AUCUNE table (préservation des données).
 */
function smartermail_billing_deactivate()
{
    return ['status' => 'success', 'description' => 'Module désactivé — les données (forfaits, réglages) sont conservées.'];
}


// ============================================================================
//  HELPERS UI
// ============================================================================

/** Échappement HTML (attributs + contenu). */
function _sm_billing_h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Alerte Bootstrap (thème admin WHMCS). */
function _sm_billing_alert(string $type, string $msg): string
{
    return '<div class="alert alert-' . _sm_billing_h($type) . '">' . _sm_billing_h($msg) . '</div>';
}

/** Barre de navigation entre les 3 vues de l'addon. */
function _sm_billing_nav(string $active): string
{
    $base = 'addonmodules.php?module=smartermail_billing';
    $tabs = [
        'packages' => 'Forfaits',
        'global'   => 'Réglages globaux',
        'products' => 'Réglages produits (hérité)',
    ];
    $h = '<ul class="nav nav-tabs" style="margin-bottom:16px;">';
    foreach ($tabs as $key => $label) {
        $cls = ($active === $key) ? ' class="active"' : '';
        $h  .= '<li' . $cls . '><a href="' . $base . '&view=' . $key . '">' . _sm_billing_h($label) . '</a></li>';
    }
    return $h . '</ul>';
}


// ============================================================================
//  VUE — Liste des forfaits
// ============================================================================

function _sm_billing_packagesList(string $csrf): string
{
    $base     = 'addonmodules.php?module=smartermail_billing&view=packages';
    $packages = _sm_listPackages();

    // Nombre de produits liés à chaque forfait (via configoption24 parsé).
    $tally = [];
    try {
        $prods = Capsule::table('tblproducts')->where('servertype', 'smartermail')->get(['configoption24']);
        foreach ($prods as $pr) {
            $pkgId = _sm_parsePackageId((string) ($pr->configoption24 ?? ''));
            if ($pkgId > 0) {
                $tally[$pkgId] = ($tally[$pkgId] ?? 0) + 1;
            }
        }
    } catch (\Throwable $e) {
        // best-effort
    }

    $h  = '<p>Créez des forfaits réutilisables. Un produit les référence via l\'option de configuration '
        . '<strong>« Forfait »</strong> (dernier champ des réglages du module). Sans forfait sélectionné, '
        . 'le produit conserve son comportement historique (options individuelles).</p>';
    $h .= '<p><a href="' . $base . '&create=1" class="btn btn-primary"><i class="fas fa-plus"></i> Créer un forfait</a></p>';

    if (empty($packages)) {
        return $h . _sm_billing_alert('info', 'Aucun forfait pour l\'instant. Cliquez « Créer un forfait » pour commencer.');
    }

    $h .= '<table class="table table-striped table-condensed"><thead><tr>'
        . '<th>#</th><th>Nom</th><th>Type</th><th>Quota</th><th>Boîte max</th><th>Produits liés</th><th></th>'
        . '</tr></thead><tbody>';

    foreach ($packages as $p) {
        $type = ($p->overage_mode === 'block')
            ? 'Espace bloqué'
            : (($p->overage_mode === 'bill') ? 'À l\'usage (+ excédent)' : 'À l\'usage');
        $quota = ((int) $p->quota_gb > 0) ? ((int) $p->quota_gb . ' Go') : 'illimité';
        $mbox  = ((int) $p->max_mailbox_size_gb > 0) ? ((int) $p->max_mailbox_size_gb . ' Go') : 'illimité';
        $used  = (int) ($tally[(int) $p->id] ?? 0);

        $confirm = $used > 0
            ? 'Ce forfait est utilisé par ' . $used . ' produit(s), qui retomberont sur leurs options individuelles. Supprimer quand même ?'
            : 'Supprimer ce forfait ?';

        $h .= '<tr>';
        $h .= '<td>' . (int) $p->id . '</td>';
        $h .= '<td><strong>' . _sm_billing_h($p->name) . '</strong></td>';
        $h .= '<td>' . _sm_billing_h($type) . '</td>';
        $h .= '<td>' . _sm_billing_h($quota) . '</td>';
        $h .= '<td>' . _sm_billing_h($mbox) . '</td>';
        $h .= '<td>' . ($used > 0
            ? '<span class="label label-info">' . $used . '</span>'
            : '<span class="text-muted">0</span>') . '</td>';
        $h .= '<td class="text-right" style="white-space:nowrap;">';
        $h .= '<a href="' . $base . '&edit=' . (int) $p->id . '" class="btn btn-default btn-xs"><i class="fas fa-edit"></i> Modifier</a> ';
        $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" style="display:inline;" '
            . 'onsubmit="return confirm(\'' . _sm_billing_h($confirm) . '\');">';
        $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
        $h .= '<input type="hidden" name="action" value="deletepackage">';
        $h .= '<input type="hidden" name="id" value="' . (int) $p->id . '">';
        $h .= '<button type="submit" class="btn btn-danger btn-xs" title="Supprimer"><i class="fas fa-trash"></i></button>';
        $h .= '</form>';
        $h .= '</td></tr>';
    }

    return $h . '</tbody></table>';
}


// ============================================================================
//  VUE — Éditeur de forfait (onglets)
// ============================================================================

function _sm_billing_packageEditor(?object $row, string $csrf, bool $easMapiAvailable): string
{
    $isEdit = ($row !== null);
    $id     = $isEdit ? (int) $row->id : 0;
    $base   = 'addonmodules.php?module=smartermail_billing&view=packages';

    // Getters : valeur (échappée) et état de case à cocher (avec défaut).
    $v = function (string $k, $def = '') use ($row) {
        return _sm_billing_h($row->$k ?? $def);
    };
    $ck = function (string $k, int $def = 1) use ($row) {
        $val = isset($row->$k) ? (int) $row->$k : $def;
        return $val ? ' checked' : '';
    };
    // Ligne de formulaire (label + champ + aide facultative).
    $fg = function (string $label, string $field, string $help = '') {
        $h = '<div class="form-group"><label class="col-sm-4 control-label">' . $label
            . '</label><div class="col-sm-6">' . $field;
        if ($help !== '') {
            $h .= '<span class="help-block" style="margin:4px 0 0;">' . $help . '</span>';
        }
        return $h . '</div></div>';
    };
    // Case à cocher pleine largeur.
    $cb = function (string $name, string $label, string $checked) {
        return '<div class="form-group"><div class="col-sm-offset-4 col-sm-6"><div class="checkbox"><label>'
            . '<input type="checkbox" name="' . $name . '" value="1"' . $checked . '> ' . $label
            . '</label></div></div></div>';
    };

    // plan_type (projection UI) déduit de overage_mode.
    $om       = $row->overage_mode ?? 'notify';
    $planType = ($om === 'block') ? 'blocked' : (($om === 'bill') ? 'usage_bill' : 'usage_notify');
    $ptOpt = function (string $val, string $label) use ($planType) {
        return '<option value="' . $val . '"' . ($planType === $val ? ' selected' : '') . '>' . _sm_billing_h($label) . '</option>';
    };
    $pol    = $row->dmarc_policy ?? 'none';
    $polOpt = function (string $val) use ($pol) {
        return '<option value="' . $val . '"' . ($pol === $val ? ' selected' : '') . '>' . $val . '</option>';
    };

    $title = $isEdit ? ('Modifier le forfait #' . $id) : 'Nouveau forfait';

    $h  = '<p><a href="' . $base . '" class="btn btn-default btn-xs"><i class="fas fa-arrow-left"></i> Retour à la liste</a></p>';
    $h .= '<div class="panel panel-default"><div class="panel-heading"><strong>' . _sm_billing_h($title) . '</strong></div><div class="panel-body">';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" class="form-horizontal">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="savepackage">';
    $h .= '<input type="hidden" name="id" value="' . $id . '">';

    // Nom (toujours visible, hors onglets).
    $h .= $fg('Nom du forfait',
        '<input type="text" name="name" required value="' . $v('name') . '" class="form-control" placeholder="Ex. Argent, Or, Entreprise…">');

    // Onglets.
    $h .= '<ul class="nav nav-tabs" style="margin:12px 0;">'
        . '<li class="active"><a href="#sm-tab-gen" data-toggle="tab">Général</a></li>'
        . '<li><a href="#sm-tab-pwd" data-toggle="tab">Mot de passe</a></li>'
        . '<li><a href="#sm-tab-dns" data-toggle="tab">DNS</a></li>'
        . '<li><a href="#sm-tab-disk" data-toggle="tab">Disque &amp; facturation</a></li>'
        . '</ul><div class="tab-content">';

    // ── Onglet Général ────────────────────────────────────────────────────────
    $h .= '<div class="tab-pane active" id="sm-tab-gen" style="padding-top:12px;">';
    if ($easMapiAvailable) {
        $h .= '<h4>ActiveSync (EAS) / MAPI</h4>';
        $h .= $cb('offer_eas', 'Proposer ActiveSync (EAS) aux clients', $ck('offer_eas', 1));
        $h .= $cb('offer_mapi', 'Proposer MAPI/Exchange aux clients', $ck('offer_mapi', 1));
        $h .= $fg('Prix EAS / boîte / mois', '<input type="number" step="0.01" min="0" name="price_eas" value="' . $v('price_eas', '0.00') . '" class="form-control">');
        $h .= $fg('Prix MAPI / boîte / mois', '<input type="number" step="0.01" min="0" name="price_mapi" value="' . $v('price_mapi', '0.00') . '" class="form-control">');
        $h .= $fg('Prix combiné EAS+MAPI / mois',
            '<input type="number" step="0.01" min="0" name="price_bundle" value="' . $v('price_bundle', '0.00') . '" class="form-control">',
            'Tarif promo si une boîte a EAS ET MAPI. 0 = additionner les deux prix.');
        $h .= $fg('Seuil de facturation (jours)',
            '<input type="number" min="0" name="billing_threshold_days" value="' . $v('billing_threshold_days', '0') . '" class="form-control">',
            'Délai avant de facturer un protocole (période d\'essai). 0 = facturer immédiatement (mode live).');
        $h .= '<hr>';
    } else {
        $h .= _sm_billing_alert('info', 'EAS/MAPI est désactivé globalement (Réglages globaux) — les champs EAS/MAPI sont masqués et ignorés.');
        // Préserver les valeurs enregistrées même quand la section est masquée.
        $h .= '<input type="hidden" name="offer_eas" value="' . (isset($row->offer_eas) ? (int) $row->offer_eas : 1) . '">';
        $h .= '<input type="hidden" name="offer_mapi" value="' . (isset($row->offer_mapi) ? (int) $row->offer_mapi : 1) . '">';
        $h .= '<input type="hidden" name="price_eas" value="' . $v('price_eas', '0') . '">';
        $h .= '<input type="hidden" name="price_mapi" value="' . $v('price_mapi', '0') . '">';
        $h .= '<input type="hidden" name="price_bundle" value="' . $v('price_bundle', '0') . '">';
        $h .= '<input type="hidden" name="billing_threshold_days" value="' . $v('billing_threshold_days', '0') . '">';
    }
    $h .= '<h4>Serveur</h4>';
    $h .= $fg('Chemin des domaines',
        '<input type="text" name="domain_path" value="' . $v('domain_path', 'C:\\SmarterMail\\Domains\\') . '" class="form-control">',
        'Le nom du domaine est ajouté à la fin. Ex. C:\\SmarterMail\\Domains\\');
    $h .= $fg('IP de sortie (outbound)',
        '<input type="text" name="outbound_ip" value="' . $v('outbound_ip', 'default') . '" class="form-control">',
        '« default » = IP par défaut du serveur (sinon IP dédiée).');
    $h .= $fg('Nombre max de boîtes',
        '<input type="number" min="0" name="max_users" value="' . $v('max_users', '0') . '" class="form-control">', '0 = illimité.');
    $h .= $fg('Alias de domaine max',
        '<input type="number" min="0" name="max_domain_aliases" value="' . $v('max_domain_aliases', '0') . '" class="form-control">', '0 = fonctionnalité désactivée.');
    $h .= $cb('delete_on_terminate', 'Supprimer le compte SmarterMail à la résiliation', $ck('delete_on_terminate', 1));
    $h .= '</div>';

    // ── Onglet Mot de passe ─────────────────────────────────────────────────
    $h .= '<div class="tab-pane" id="sm-tab-pwd" style="padding-top:12px;">';
    $h .= $fg('Longueur minimale',
        '<input type="number" min="1" name="pwd_min_len" value="' . $v('pwd_min_len', '8') . '" class="form-control">',
        'Doit correspondre à la politique configurée dans SmarterMail.');
    $h .= $cb('pwd_require_upper', 'Exiger une lettre majuscule', $ck('pwd_require_upper', 1));
    $h .= $cb('pwd_require_lower', 'Exiger une lettre minuscule', $ck('pwd_require_lower', 0));
    $h .= $cb('pwd_require_digit', 'Exiger un chiffre', $ck('pwd_require_digit', 1));
    $h .= $cb('pwd_require_special', 'Exiger un caractère spécial', $ck('pwd_require_special', 1));
    $h .= '</div>';

    // ── Onglet DNS ──────────────────────────────────────────────────────────
    $h .= '<div class="tab-pane" id="sm-tab-dns" style="padding-top:12px;">';
    $h .= $fg('Mécanisme SPF primaire',
        '<input type="text" name="spf_primary" value="' . $v('spf_primary') . '" class="form-control" placeholder="include:mail.example.com">');
    $h .= $fg('Mécanismes SPF secondaires',
        '<input type="text" name="spf_secondary" value="' . $v('spf_secondary') . '" class="form-control" placeholder="include:…, ip4:…">',
        'Acceptés en plus du primaire (validation). Séparés par des virgules.');
    $h .= $fg('Hôte Autodiscover',
        '<input type="text" name="autodiscover_host" value="' . $v('autodiscover_host') . '" class="form-control">',
        'Vide = nom d\'hôte du serveur.');
    $h .= $fg('Cible SRV Autodiscover',
        '<input type="text" name="srv_target" value="' . $v('srv_target') . '" class="form-control">',
        'Vide = nom d\'hôte du serveur.');
    $h .= $cb('dmarc_check', 'Vérifier / suggérer DMARC', $ck('dmarc_check', 1));
    $h .= $fg('RUA DMARC suggéré',
        '<input type="text" name="dmarc_rua" value="' . $v('dmarc_rua') . '" class="form-control" placeholder="dmarc-reports@example.com">');
    $h .= $fg('Politique DMARC suggérée',
        '<select name="dmarc_policy" class="form-control">' . $polOpt('none') . $polOpt('quarantine') . $polOpt('reject') . '</select>');
    $h .= '</div>';

    // ── Onglet Disque & facturation ─────────────────────────────────────────
    $h .= '<div class="tab-pane" id="sm-tab-disk" style="padding-top:12px;">';
    $h .= $fg('Type de forfait',
        '<select name="plan_type" class="form-control">'
        . $ptOpt('usage_notify', 'À l\'usage — jauge / alerte seulement')
        . $ptOpt('usage_bill', 'À l\'usage — facturer l\'excédent au-delà du quota')
        . $ptOpt('blocked', 'Espace maximal bloqué (le serveur refuse au-delà)')
        . '</select>',
        'Détermine comment l\'espace disque est facturé.');
    $h .= $fg('Go par tranche',
        '<input type="number" min="1" name="gb_per_tier" value="' . $v('gb_per_tier', '10') . '" class="form-control">',
        'Incrément de facturation à l\'usage.');
    $h .= $fg('Quota / espace max (Go)',
        '<input type="number" min="0" name="quota_gb" value="' . $v('quota_gb', '0') . '" class="form-control">',
        '0 = illimité. Requis pour « bloqué » et « facturer l\'excédent ».');
    $h .= $fg('Prix tranche excédentaire',
        '<input type="number" step="0.01" min="0" name="overage_price" value="' . $v('overage_price', '0.00') . '" class="form-control">',
        'Utilisé par « facturer l\'excédent ».');
    $h .= $fg('Taille max par boîte (Go)',
        '<input type="number" min="0" name="max_mailbox_size_gb" value="' . $v('max_mailbox_size_gb', '0') . '" class="form-control">',
        '0 = illimité.');
    $h .= $fg('Seuil d\'alerte (%)',
        '<input type="number" min="1" max="100" name="notify_threshold_pct" value="' . $v('notify_threshold_pct', '90') . '" class="form-control">');
    $h .= '</div>';

    $h .= '</div>'; // tab-content

    $h .= '<div class="form-group" style="margin-top:16px;"><div class="col-sm-offset-4 col-sm-6">'
        . '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ' . ($isEdit ? 'Enregistrer' : 'Créer le forfait') . '</button> '
        . '<a href="' . $base . '" class="btn btn-default">Annuler</a></div></div>';

    return $h . '</form></div></div>';
}


// ============================================================================
//  VUE — Réglages globaux
// ============================================================================

function _sm_billing_globalForm(string $csrf): string
{
    $easMapi = (bool) _sm_getGlobalSetting('eas_mapi_available', true);
    $ns      = _sm_getGlobalSetting('provider_nameservers', _sm_defaultNameservers());
    if (!is_array($ns)) {
        $ns = _sm_defaultNameservers();
    }
    $nsText = function (string $tab) use ($ns) {
        $list = $ns[$tab] ?? [];
        return _sm_billing_h(is_array($list) ? implode("\n", $list) : '');
    };

    $h  = '<div class="panel panel-default"><div class="panel-heading"><strong>Réglages globaux</strong></div><div class="panel-body">';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" class="form-horizontal">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="saveglobal">';

    $h .= '<div class="form-group"><div class="col-sm-offset-3 col-sm-7"><div class="checkbox"><label>'
        . '<input type="checkbox" name="eas_mapi_available" value="1"' . ($easMapi ? ' checked' : '') . '> '
        . 'Le serveur SmarterMail propose EAS / MAPI</label>'
        . '<span class="help-block">Décochez si votre licence n\'inclut pas ActiveSync/MAPI : la section EAS/MAPI '
        . 'sera masquée dans les forfaits (et, à terme, dans l\'espace client).</span></div></div></div>';

    $h .= '<hr><h4>Nameservers (pré-sélection de l\'onglet du guide DNS)</h4>';
    $h .= '<p class="text-muted" style="margin-left:15px;">Un nom de serveur par ligne. Sert uniquement à pré-sélectionner '
        . 'le bon onglet du guide DNS selon les NS du domaine du client (aucun effet fonctionnel). '
        . 'Videz un champ pour retomber sur les valeurs par défaut.</p>';
    foreach (['cpanel' => 'cPanel', 'plesk' => 'Plesk', 'clientspace' => 'ClientSpace'] as $key => $label) {
        $h .= '<div class="form-group"><label class="col-sm-3 control-label">' . $label . '</label><div class="col-sm-7">'
            . '<textarea name="ns_' . $key . '" rows="3" class="form-control">' . $nsText($key) . '</textarea></div></div>';
    }

    $h .= '<div class="form-group"><div class="col-sm-offset-3 col-sm-7">'
        . '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les réglages globaux</button>'
        . '</div></div>';

    return $h . '</form></div></div>';
}


// ============================================================================
//  VUE — Réglages produits (hérité : mod_sm_product_settings)
// ============================================================================

/**
 * Applique la limite disque (maxSize) au serveur SmarterMail pour TOUS les
 * services actifs d'un produit (parc déjà provisionné avant l'activation d'un quota).
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
 * Panneau éditable des réglages « hérités » d'un produit (mod_sm_product_settings).
 */
function _sm_billing_productForm($product, array $s, int $activeCount, string $csrf): string
{
    $pid  = (int) $product->id;
    $name = _sm_billing_h((string) $product->name);
    $e    = fn($k) => _sm_billing_h((string) ($s[$k] ?? ''));

    $modelOpt = function (string $val, string $label) use ($s): string {
        $sel = (($s['billing_model'] ?? 'tiers') === $val) ? ' selected' : '';
        return '<option value="' . $val . '"' . $sel . '>' . _sm_billing_h($label) . '</option>';
    };
    $modeOpt = function (string $val, string $label) use ($s): string {
        $sel = (($s['overage_mode'] ?? 'notify') === $val) ? ' selected' : '';
        return '<option value="' . $val . '"' . $sel . '>' . _sm_billing_h($label) . '</option>';
    };

    $h  = '<div class="panel panel-default"><div class="panel-heading"><strong>' . $name
        . '</strong> <span class="label label-default">#' . $pid . '</span> '
        . '<span class="text-muted" style="margin-left:8px;">' . $activeCount . ' service(s) actif(s)</span></div>';
    $h .= '<div class="panel-body">';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" class="form-horizontal">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="savesettings">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
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
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="applylimits">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    $h .= '<button type="submit" class="btn btn-default btn-sm"' . ($activeCount > 0 ? '' : ' disabled')
        . '><i class="fas fa-server"></i> Appliquer aux services actifs</button>';
    $h .= ' <span class="text-muted">Pousse <code>maxSize</code> au serveur (mode « bloquer » uniquement ; sinon illimité).</span>';
    $h .= '</form>';

    // Convertir la config héritée de ce produit en forfait réutilisable (+ liaison).
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" '
        . 'onsubmit="return confirm(\'Créer un forfait depuis la configuration actuelle de ce produit, et le lier si aucun forfait n est encore sélectionné ?\');" style="margin-top:6px;">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="convertproduct">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    $h .= '<button type="submit" class="btn btn-default btn-sm"><i class="fas fa-magic"></i> Convertir en forfait</button>';
    $h .= ' <span class="text-muted">Crée un forfait reflétant les options actuelles et le sélectionne pour ce produit.</span>';
    $h .= '</form>';

    return $h . '</div></div>';
}

/** Vue « Réglages produits (hérité) » : un panneau par produit smartermail. */
function _sm_billing_productsView(string $csrf): string
{
    $products = Capsule::table('tblproducts')
        ->where('servertype', 'smartermail')
        ->orderBy('name')
        ->get(['id', 'name']);

    $html  = '<p>Réglages de facturation par produit (modèle « hérité », table <code>mod_sm_product_settings</code>) — '
        . 'utilisés pour les produits <strong>sans forfait</strong>. Sans réglage, un produit conserve le comportement historique '
        . '(par tranches disque, aucun quota).</p>';

    if (count($products) === 0) {
        return $html . _sm_billing_alert('info', 'Aucun produit avec le type de serveur « smartermail » n\'a été trouvé.');
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

/**
 * Convertit un produit (config héritée : configoptions + mod_sm_product_settings)
 * en un FORFAIT réutilisable, puis lie le produit au nouveau forfait (via
 * configoption24) UNIQUEMENT s'il n'en a pas déjà un — ne jamais écraser un choix.
 *
 * Les offres EAS/MAPI sont enregistrées à leur valeur BRUTE (co14/co15), sans le
 * repli de disponibilité globale (le forfait garde l'intention du produit).
 *
 * @param  int $productId
 * @return int Id du nouveau forfait (>0) ou 0 en cas d'échec.
 */
function _sm_billing_convertProduct(int $productId): int
{
    if ($productId <= 0) {
        return 0;
    }
    try {
        $p = Capsule::table('tblproducts')
            ->where('id', $productId)
            ->where('servertype', 'smartermail')
            ->first();
        if (!$p) {
            return 0;
        }

        $co = [];
        for ($i = 1; $i <= 24; $i++) {
            $k = 'configoption' . $i;
            $co[$k] = $p->$k ?? null;
        }

        // Config normalisée (champs non-offre + sous-forme disque via product_settings).
        $norm = _sm_legacyConfigToPackage($co, ['pid' => $productId]);
        $plan = ($norm['overage_mode'] === 'block')
            ? 'blocked'
            : (($norm['overage_mode'] === 'bill') ? 'usage_bill' : 'usage_notify');

        $data = [
            'name'                   => mb_substr('Converti — ' . (string) $p->name, 0, 190),
            'offer_eas'              => (($co['configoption14'] ?? 'on') === 'on') ? 1 : 0,
            'offer_mapi'             => (($co['configoption15'] ?? 'on') === 'on') ? 1 : 0,
            'price_eas'              => $norm['price_eas'],
            'price_mapi'             => $norm['price_mapi'],
            'price_bundle'           => $norm['price_bundle'],
            'billing_threshold_days' => (int) ($co['configoption16'] ?? 0),
            'gb_per_tier'            => max(1, (int) ($co['configoption1'] ?? 10)),
            'domain_path'            => $norm['domain_path'],
            'outbound_ip'            => $norm['outbound_ip'],
            'max_users'              => $norm['max_users'],
            'max_domain_aliases'     => $norm['max_domain_aliases'],
            'delete_on_terminate'    => $norm['delete_on_terminate'] ? 1 : 0,
            'pwd_min_len'            => $norm['pwd_min_len'],
            'pwd_require_upper'      => $norm['pwd_require_upper'] ? 1 : 0,
            'pwd_require_lower'      => 0,
            'pwd_require_digit'      => $norm['pwd_require_digit'] ? 1 : 0,
            'pwd_require_special'    => $norm['pwd_require_special'] ? 1 : 0,
            'spf_primary'            => $norm['spf_primary'],
            'spf_secondary'          => $norm['spf_secondary'],
            'autodiscover_host'      => $norm['autodiscover_host'],
            'srv_target'             => $norm['srv_target'],
            'dmarc_check'            => $norm['dmarc_check'] ? 1 : 0,
            'dmarc_rua'              => $norm['dmarc_rua'],
            'dmarc_policy'           => $norm['dmarc_policy'],
            'plan_type'              => $plan,
            'quota_gb'               => $norm['quota_gb'],
            'max_mailbox_size_gb'    => 0,
            'overage_price'          => $norm['overage_price'],
            'notify_threshold_pct'   => $norm['notify_threshold_pct'],
        ];

        $newId = _sm_savePackage(null, $data);
        if ($newId <= 0) {
            return 0;
        }

        // Lier le produit au forfait SEULEMENT s'il n'en référence pas déjà un.
        if (_sm_parsePackageId((string) ($co['configoption24'] ?? '')) <= 0) {
            Capsule::table('tblproducts')->where('id', $productId)->update(['configoption24' => (string) $newId]);
        }
        logActivity('SmarterMail [forfaits] Produit #' . $productId . ' converti en forfait #' . $newId . '.');
        return $newId;
    } catch (\Throwable $e) {
        logActivity('SmarterMail [forfaits] conversion produit #' . $productId . ' échouée : ' . $e->getMessage());
        return 0;
    }
}


// ============================================================================
//  SORTIE — routeur + traitement des POST
// ============================================================================

function smartermail_billing_output($vars)
{
  try {
    // CSRF : jeton auto-géré en session (l'accès admin est déjà restreint).
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

            if ($action === 'savepackage') {
                $pid   = (int) ($_POST['id'] ?? 0);
                $newId = _sm_savePackage($pid ?: null, $_POST);
                $notice = $newId > 0
                    ? _sm_billing_alert('success', 'Forfait enregistré (#' . $newId . ').')
                    : _sm_billing_alert('danger', 'Échec de l\'enregistrement (nom manquant ? voir le journal d\'activité).');

            } elseif ($action === 'deletepackage') {
                $notice = _sm_deletePackage((int) ($_POST['id'] ?? 0))
                    ? _sm_billing_alert('success', 'Forfait supprimé.')
                    : _sm_billing_alert('danger', 'Échec de la suppression (voir le journal d\'activité).');

            } elseif ($action === 'saveglobal') {
                $ns = [];
                foreach (['cpanel', 'plesk', 'clientspace'] as $key) {
                    $lines    = preg_split('/[\r\n,]+/', (string) ($_POST['ns_' . $key] ?? ''));
                    $ns[$key] = array_values(array_filter(
                        array_map(fn($x) => strtolower(trim($x)), $lines),
                        fn($x) => $x !== ''
                    ));
                }
                _sm_setGlobalSetting('eas_mapi_available', !empty($_POST['eas_mapi_available']));
                _sm_setGlobalSetting('provider_nameservers', $ns);
                $notice = _sm_billing_alert('success', 'Réglages globaux enregistrés.');

            } elseif ($action === 'savesettings') {
                $pid = (int) ($_POST['product_id'] ?? 0);
                $notice = ($pid > 0 && _sm_saveProductSettings($pid, $_POST))
                    ? _sm_billing_alert('success', 'Réglages du produit #' . $pid . ' enregistrés.')
                    : _sm_billing_alert('danger', 'Échec de l\'enregistrement (voir le journal d\'activité).');

            } elseif ($action === 'applylimits') {
                $pid = (int) ($_POST['product_id'] ?? 0);
                if ($pid > 0) {
                    $r = _sm_billing_applyLimits($pid);
                    $notice = _sm_billing_alert(
                        $r['fail'] > 0 ? 'warning' : 'success',
                        sprintf('Limites appliquées : %d OK, %d échec(s) sur %d service(s) actif(s).', $r['ok'], $r['fail'], $r['total'])
                    );
                }

            } elseif ($action === 'convertproduct') {
                $pid   = (int) ($_POST['product_id'] ?? 0);
                $newId = _sm_billing_convertProduct($pid);
                $notice = $newId > 0
                    ? _sm_billing_alert('success', 'Produit #' . $pid . ' converti en forfait #' . $newId
                        . ' (lié au produit si aucun forfait n\'était sélectionné). À vérifier dans l\'onglet « Forfaits ».')
                    : _sm_billing_alert('danger', 'Échec de la conversion (voir le journal d\'activité).');
            }
        }
    }

    // Routage des vues.
    $view = (string) ($_GET['view'] ?? 'packages');
    if (!in_array($view, ['packages', 'global', 'products'], true)) {
        $view = 'packages';
    }

    $html = $notice . _sm_billing_nav($view);

    if ($view === 'global') {
        $html .= _sm_billing_globalForm($csrf);

    } elseif ($view === 'products') {
        $html .= _sm_billing_productsView($csrf);

    } else { // packages
        $easMapiAvailable = (bool) _sm_getGlobalSetting('eas_mapi_available', true);
        if (isset($_GET['create'])) {
            $html .= _sm_billing_packageEditor(null, $csrf, $easMapiAvailable);
        } elseif (isset($_GET['edit'])) {
            $row = null;
            try {
                $row = Capsule::table('mod_sm_packages')
                    ->where('id', (int) $_GET['edit'])
                    ->where('is_deleted', 0)
                    ->first();
            } catch (\Throwable $e) {
                // repli liste ci-dessous
            }
            $html .= $row
                ? _sm_billing_packageEditor($row, $csrf, $easMapiAvailable)
                : (_sm_billing_alert('warning', 'Forfait introuvable.') . _sm_billing_packagesList($csrf));
        } else {
            $html .= _sm_billing_packagesList($csrf);
        }
    }

    echo $html;

  } catch (\Throwable $e) {
      logActivity('SmarterMail [billing-addon] output EXCEPTION: ' . $e->getMessage()
          . ' @ ' . $e->getFile() . ':' . $e->getLine());
      echo '<div class="alert alert-danger"><strong>Erreur du module SmarterMail Forfaits :</strong> '
          . htmlspecialchars($e->getMessage()) . '</div>';
  }
}
