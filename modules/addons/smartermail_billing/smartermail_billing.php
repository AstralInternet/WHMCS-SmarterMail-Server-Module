<?php
/**
 * ============================================================================
 *  smartermail_billing — Addon WHMCS : Gestionnaire de FORFAITS SmarterMail
 * ============================================================================
 *
 *  Interface d'administration moderne (en-tête + onglets de vue, éditeur en
 *  ACCORDÉON, cartes arrondies) pour :
 *    • créer / modifier / supprimer des FORFAITS réutilisables (mod_sm_packages) ;
 *    • éditer les RÉGLAGES GLOBAUX du revendeur (mod_sm_settings) ;
 *    • conserver l'édition « héritée » par produit (mod_sm_product_settings).
 *
 *  Un produit référence un forfait via l'option de configuration « Forfait »
 *  (configoption24). Sans forfait, il conserve son comportement historique.
 *
 *  Présentation : styles scopés `.smx-*` + Inter (CDN) + FontAwesome (fourni par
 *  WHMCS). Accordéon piloté par un petit JS vanilla (smxToggle / smxAll).
 *  Cette page N'ÉCRIT QUE dans les tables du module (jamais les tables WHMCS cœur).
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
            . 'disque & facturation) dans une interface moderne, plus les réglages globaux '
            . 'et les réglages de facturation par produit (hérité).',
        'version'     => '2.2',
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
//  ASSETS (styles + JS accordéon) — injectés une fois par page
// ============================================================================

function _sm_billing_assets(): string
{
    return <<<'HTML'
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
.smx,.smx *{box-sizing:border-box;}
.smx{max-width:920px;margin:0 auto;font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;color:#0f172a;padding:8px 0 40px;}
.smx a{text-decoration:none;}
.smx-head{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
.smx-logo{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#1e40af);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;box-shadow:0 4px 12px rgba(37,99,235,.28);}
.smx-eyebrow{font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#64748b;}
.smx-title{font-size:22px;font-weight:700;line-height:1.1;color:#0f172a;}
.smx-tabs{display:flex;gap:4px;border-bottom:1px solid #dbe2ec;margin-bottom:20px;flex-wrap:wrap;}
.smx-tab{padding:10px 16px;font-size:14px;font-weight:500;color:#64748b;}
.smx-tab:hover{color:#2563eb;}
.smx-tab.active{color:#2563eb;font-weight:600;border-bottom:2px solid #2563eb;margin-bottom:-1px;}
.smx-back{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;color:#475569;margin-bottom:16px;}
.smx-back:hover{color:#2563eb;}
.smx-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 1px 3px rgba(15,23,42,.06),0 12px 32px -18px rgba(15,23,42,.22);overflow:hidden;margin-bottom:16px;}
.smx-card-head{padding:24px 26px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fbfcfe,#fff);}
.smx-card-body{padding:22px 26px;}
.smx-chip{font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#2563eb;background:#eff4ff;padding:4px 9px;border-radius:6px;}
.smx-acc-body-outer{padding:20px 26px;background:#f8fafc;}
.smx-acc{border:1px solid #e2e8f0;border-radius:14px;background:#fff;margin-bottom:14px;overflow:hidden;}
.smx-acc-head{display:flex;align-items:center;gap:14px;padding:18px 20px;cursor:pointer;user-select:none;}
.smx-acc-icon{width:38px;height:38px;flex:0 0 38px;border-radius:10px;background:#eff4ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:16px;}
.smx-acc-title{font-size:15px;font-weight:700;color:#0f172a;}
.smx-acc-sub{font-size:12.5px;color:#64748b;margin-top:1px;}
.smx-acc-chev{color:#94a3b8;font-size:14px;transition:transform .2s ease;}
.smx-acc-body{padding:8px 20px 22px;border-top:1px solid #eef2f7;}
.smx-fg{margin-bottom:16px;}
.smx-lbl{display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:6px;}
.smx-unit{font-weight:400;color:#94a3b8;font-size:11.5px;}
.smx-inp{width:100%;padding:9px 12px;border:1px solid #d0d7e2;border-radius:9px;font-size:14px;color:#0f172a;background:#fff;outline:none;}
.smx-inp:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
textarea.smx-inp{resize:vertical;min-height:70px;}
.smx-hint{margin:6px 0 0;font-size:12px;color:#64748b;line-height:1.4;}
.smx-name{width:100%;max-width:420px;padding:11px 14px;border:1px solid #d0d7e2;border-radius:10px;font-size:15px;font-weight:500;color:#0f172a;outline:none;}
.smx-name:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.smx-chk{display:flex;align-items:center;gap:11px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:11px;background:#f8fafc;cursor:pointer;font-size:14px;}
.smx-chk input{width:18px;height:18px;accent-color:#2563eb;flex:0 0 18px;}
.smx-chk.danger{border-color:#fee2e2;background:#fef5f5;}
.smx-chk.danger input{accent-color:#dc2626;}
.smx-g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.smx-g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.smx-g21{display:grid;grid-template-columns:2fr 1fr;gap:16px;}
.smx-stack{display:flex;flex-direction:column;gap:10px;}
.smx-money{display:flex;align-items:stretch;border:1px solid #d0d7e2;border-radius:9px;overflow:hidden;background:#fff;}
.smx-money:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.smx-money-adorn{display:flex;align-items:center;padding:0 11px;background:#f1f5f9;color:#64748b;font-size:14px;font-weight:600;border-right:1px solid #e2e8f0;}
.smx-money input{flex:1;min-width:0;padding:9px 12px;border:none;font-size:14px;color:#0f172a;outline:none;background:transparent;}
.smx-actionbar{position:sticky;bottom:0;display:flex;align-items:center;gap:12px;padding:16px 26px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-top:1px solid #e2e8f0;}
.smx-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid transparent;line-height:1.2;}
.smx-btn.sm{padding:8px 14px;font-size:13px;}
.smx-btn-primary{background:#2563eb;color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.28);}
.smx-btn-primary:hover{background:#1d4ed8;color:#fff;}
.smx-btn-ghost{background:#fff;color:#475569;border-color:#d0d7e2;}
.smx-btn-ghost:hover{background:#f8fafc;color:#334155;}
.smx-btn-danger{background:#fff;color:#dc2626;border-color:#fecaca;}
.smx-btn-danger:hover{background:#fef2f2;color:#b91c1c;}
.smx-toollink{font-size:12.5px;font-weight:600;color:#2563eb;cursor:pointer;}
.smx-toollink.mut{color:#64748b;}
.smx-lead{font-size:14px;color:#475569;line-height:1.5;margin:0 0 16px;}
.smx-table{width:100%;border-collapse:collapse;font-size:14px;}
.smx-table th{text-align:left;font-size:11.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#94a3b8;padding:0 14px 10px;border-bottom:1px solid #eef2f7;}
.smx-table td{padding:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.smx-table tr:last-child td{border-bottom:none;}
.smx-pill{display:inline-block;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;background:#eff4ff;color:#2563eb;}
.smx-pill.mut{background:#f1f5f9;color:#94a3b8;}
.smx-tag{display:inline-block;font-size:12px;font-weight:500;color:#475569;background:#f1f5f9;padding:3px 9px;border-radius:6px;}
.smx-alert{border-radius:12px;padding:13px 16px;font-size:14px;margin-bottom:16px;border:1px solid transparent;}
.smx-empty{text-align:center;padding:40px 20px;color:#64748b;}
.smx-sub{font-size:12.5px;color:#94a3b8;font-weight:400;}
@media(max-width:640px){.smx-g2,.smx-g3,.smx-g21{grid-template-columns:1fr;}}
</style>
<script>
function smxToggle(id){
  var b=document.getElementById('smx-b-'+id),c=document.getElementById('smx-c-'+id);
  if(!b)return;var open=b.style.display!=='none';
  b.style.display=open?'none':'block';
  if(c)c.style.transform=open?'rotate(0deg)':'rotate(180deg)';
}
function smxAll(open){
  ['quota','proto','dns','server'].forEach(function(id){
    var b=document.getElementById('smx-b-'+id),c=document.getElementById('smx-c-'+id);
    if(b)b.style.display=open?'block':'none';
    if(c)c.style.transform=open?'rotate(180deg)':'rotate(0deg)';
  });
}
function smxPlan(v){
  var w=document.getElementById('smx-tierwrap');
  if(w)w.style.display=(v==='usage')?'block':'none';
}
</script>
HTML;
}


// ============================================================================
//  HELPERS UI
// ============================================================================

/** Échappement HTML (attributs + contenu). */
function _sm_billing_h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Alerte moderne (success / danger / warning / info). */
function _sm_billing_alert(string $type, string $msg): string
{
    $c = [
        'success' => ['#065f46', '#ecfdf5', '#a7f3d0'],
        'danger'  => ['#991b1b', '#fef2f2', '#fecaca'],
        'warning' => ['#92400e', '#fffbeb', '#fde68a'],
        'info'    => ['#1e40af', '#eff6ff', '#bfdbfe'],
    ][$type] ?? ['#1e40af', '#eff6ff', '#bfdbfe'];
    return '<div class="smx-alert" style="color:' . $c[0] . ';background:' . $c[1] . ';border-color:' . $c[2] . ';">'
        . _sm_billing_h($msg) . '</div>';
}

/** En-tête de page (logo + titre) + barre d'onglets de vue. */
function _sm_billing_header(string $active): string
{
    $base = 'addonmodules.php?module=smartermail_billing';
    $tabs = [
        'packages' => 'Forfaits',
        'global'   => 'Réglages globaux',
        'products' => 'Réglages produits <span class="smx-sub">(hérité)</span>',
    ];
    $h  = '<div class="smx-head">'
        . '<div class="smx-logo"><i class="fas fa-envelope"></i></div>'
        . '<div><div class="smx-eyebrow">SmarterMail</div><div class="smx-title">Forfaits</div></div>'
        . '</div>';
    $h .= '<div class="smx-tabs">';
    foreach ($tabs as $key => $label) {
        $cls = 'smx-tab' . ($active === $key ? ' active' : '');
        $h  .= '<a class="' . $cls . '" href="' . $base . '&view=' . $key . '">' . $label . '</a>';
    }
    return $h . '</div>';
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

    $h  = '<p class="smx-lead">Créez des forfaits réutilisables. Un produit les référence via l\'option '
        . '<strong>« Forfait »</strong> de ses réglages de module. Sans forfait sélectionné, le produit '
        . 'conserve son comportement historique.</p>';
    $h .= '<p style="margin:0 0 16px;"><a class="smx-btn smx-btn-primary" href="' . $base . '&create=1">'
        . '<i class="fas fa-plus"></i> Créer un forfait</a></p>';

    $h .= '<div class="smx-card"><div class="smx-card-body">';
    if (empty($packages)) {
        $h .= '<div class="smx-empty"><div style="font-size:34px;color:#cbd5e1;margin-bottom:10px;">'
            . '<i class="fas fa-box-open"></i></div>Aucun forfait pour l\'instant. Cliquez « Créer un forfait » pour commencer.</div>';
        return $h . '</div></div>';
    }

    $h .= '<table class="smx-table"><thead><tr>'
        . '<th>#</th><th>Nom</th><th>Type</th><th>Quota</th><th>Boîte max</th><th>Produits liés</th><th></th>'
        . '</tr></thead><tbody>';
    foreach ($packages as $p) {
        $type = (($p->billing_model ?? 'tiers') === 'flat') ? 'Fixe' : 'À l\'usage';
        $quota = ((int) $p->quota_gb > 0) ? ((int) $p->quota_gb . ' Go') : 'illimité';
        $mbox  = ((int) $p->max_mailbox_size_gb > 0) ? ((int) $p->max_mailbox_size_gb . ' Go') : 'illimité';
        $used  = (int) ($tally[(int) $p->id] ?? 0);
        $confirm = $used > 0
            ? 'Ce forfait est utilisé par ' . $used . ' produit(s), qui retomberont sur leurs options individuelles. Supprimer quand même ?'
            : 'Supprimer ce forfait ?';

        $h .= '<tr>';
        $h .= '<td style="color:#94a3b8;">' . (int) $p->id . '</td>';
        $h .= '<td><strong style="font-weight:600;">' . _sm_billing_h($p->name) . '</strong></td>';
        $h .= '<td><span class="smx-tag">' . _sm_billing_h($type) . '</span></td>';
        $h .= '<td>' . _sm_billing_h($quota) . '</td>';
        $h .= '<td>' . _sm_billing_h($mbox) . '</td>';
        $h .= '<td>' . ($used > 0 ? '<span class="smx-pill">' . $used . '</span>' : '<span class="smx-pill mut">0</span>') . '</td>';
        $h .= '<td style="text-align:right;white-space:nowrap;">';
        $h .= '<a class="smx-btn smx-btn-ghost sm" href="' . $base . '&edit=' . (int) $p->id . '"><i class="fas fa-edit"></i> Modifier</a> ';
        $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" style="display:inline;" '
            . 'onsubmit="return confirm(\'' . _sm_billing_h($confirm) . '\');">';
        $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
        $h .= '<input type="hidden" name="action" value="deletepackage">';
        $h .= '<input type="hidden" name="id" value="' . (int) $p->id . '">';
        $h .= '<button type="submit" class="smx-btn smx-btn-danger sm" title="Supprimer"><i class="fas fa-trash"></i></button>';
        $h .= '</form></td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}


// ============================================================================
//  VUE — Éditeur de forfait (accordéon)
// ============================================================================

function _sm_billing_packageEditor(?object $row, string $csrf, bool $easMapiAvailable): string
{
    $isEdit = ($row !== null);
    $id     = $isEdit ? (int) $row->id : 0;
    $base   = 'addonmodules.php?module=smartermail_billing&view=packages';

    $v = function (string $k, $def = '') use ($row) {
        return _sm_billing_h($row->$k ?? $def);
    };
    $ck = function (string $k, int $def = 1) use ($row) {
        $val = isset($row->$k) ? (int) $row->$k : $def;
        return $val ? ' checked' : '';
    };
    // Ligne de champ standard.
    $fg = function (string $label, string $control, string $hint = '') {
        $h = '<div class="smx-fg"><label class="smx-lbl">' . $label . '</label>' . $control;
        if ($hint !== '') {
            $h .= '<p class="smx-hint">' . $hint . '</p>';
        }
        return $h . '</div>';
    };
    // Champ monétaire (adornement $).
    $money = function (string $name, string $val) {
        return '<div class="smx-money"><span class="smx-money-adorn">$</span>'
            . '<input type="number" step="0.01" min="0" name="' . $name . '" value="' . $val . '"></div>';
    };
    // Case à cocher « carte » pleine largeur.
    $chkCard = function (string $name, string $label, string $checked, bool $danger = false) {
        return '<label class="smx-chk' . ($danger ? ' danger' : '') . '">'
            . '<input type="checkbox" name="' . $name . '" value="1"' . $checked . '><span>' . $label . '</span></label>';
    };
    // Section accordéon.
    $section = function (string $key, string $icon, string $title, string $sub, string $inner, bool $open) {
        $bodyStyle = $open ? '' : ' style="display:none;"';
        $chev      = $open ? ' style="transform:rotate(180deg);"' : '';
        return '<div class="smx-acc">'
            . '<div class="smx-acc-head" onclick="smxToggle(\'' . $key . '\')">'
            . '<div class="smx-acc-icon"><i class="fas ' . $icon . '"></i></div>'
            . '<div style="flex:1;"><div class="smx-acc-title">' . $title . '</div><div class="smx-acc-sub">' . $sub . '</div></div>'
            . '<i class="fas fa-chevron-down smx-acc-chev" id="smx-c-' . $key . '"' . $chev . '></i></div>'
            . '<div class="smx-acc-body" id="smx-b-' . $key . '"' . $bodyStyle . '>' . $inner . '</div></div>';
    };

    // ── Projections d'affichage ──────────────────────────────────────────────
    $bm       = $row->billing_model ?? 'tiers';
    $planType = ($bm === 'flat') ? 'fixe' : 'usage';
    $ptOpt = function (string $val, string $label) use ($planType) {
        return '<option value="' . $val . '"' . ($planType === $val ? ' selected' : '') . '>' . _sm_billing_h($label) . '</option>';
    };
    $pol    = $row->dmarc_policy ?? 'none';
    $polOpt = function (string $val) use ($pol) {
        return '<option value="' . $val . '"' . ($pol === $val ? ' selected' : '') . '>' . $val . '</option>';
    };

    // ── Section : Protocoles & tarifs ────────────────────────────────────────
    if ($easMapiAvailable) {
        $proto = '<div class="smx-stack" style="margin:14px 0 18px;">'
            . $chkCard('offer_eas', 'Proposer <strong>ActiveSync (EAS)</strong> aux clients', $ck('offer_eas', 1))
            . $chkCard('offer_mapi', 'Proposer <strong>MAPI/Exchange</strong> aux clients', $ck('offer_mapi', 1))
            . '</div>';
        $proto .= '<div class="smx-g3">'
            . '<div>' . $fg('Prix EAS <span class="smx-unit">/ boîte / mois</span>', $money('price_eas', $v('price_eas', '0.00'))) . '</div>'
            . '<div>' . $fg('Prix MAPI <span class="smx-unit">/ boîte / mois</span>', $money('price_mapi', $v('price_mapi', '0.00'))) . '</div>'
            . '<div>' . $fg('Combiné EAS+MAPI <span class="smx-unit">/ mois</span>', $money('price_bundle', $v('price_bundle', '0.00')),
                'Tarif promo si une boîte a EAS ET MAPI. 0 = additionner les deux prix.') . '</div>'
            . '</div>';
        $proto .= '<div style="max-width:320px;margin-top:2px;">'
            . $fg('Seuil de facturation (jours)',
                '<input type="number" min="0" name="billing_threshold_days" value="' . $v('billing_threshold_days', '0') . '" class="smx-inp">',
                'Délai avant de facturer un protocole (période d\'essai). 0 = facturer immédiatement (mode live).')
            . '</div>';
    } else {
        // EAS/MAPI désactivé globalement : section entièrement masquée, valeurs préservées.
        $proto = '<input type="hidden" name="offer_eas" value="' . (isset($row->offer_eas) ? (int) $row->offer_eas : 1) . '">'
            . '<input type="hidden" name="offer_mapi" value="' . (isset($row->offer_mapi) ? (int) $row->offer_mapi : 1) . '">'
            . '<input type="hidden" name="price_eas" value="' . $v('price_eas', '0') . '">'
            . '<input type="hidden" name="price_mapi" value="' . $v('price_mapi', '0') . '">'
            . '<input type="hidden" name="price_bundle" value="' . $v('price_bundle', '0') . '">'
            . '<input type="hidden" name="billing_threshold_days" value="' . $v('billing_threshold_days', '0') . '">';
    }

    // ── Section : Serveur (placée en bas) ────────────────────────────────────
    // Nombre de boîtes / alias déplacés vers « Quota et limitation ».
    $server = '<div style="margin:14px 0 0;">'
        . $fg('Chemin des domaines',
            '<input type="text" name="domain_path" value="' . $v('domain_path', 'C:\\SmarterMail\\Domains\\') . '" class="smx-inp">',
            'Le nom du domaine est ajouté à la fin. Ex. C:\\SmarterMail\\Domains\\')
        . $fg('IP de sortie (outbound)',
            '<input type="text" name="outbound_ip" value="' . $v('outbound_ip', 'default') . '" class="smx-inp">',
            '« default » = IP par défaut du serveur (sinon IP dédiée).')
        . '<div style="margin-top:2px;">' . $chkCard('delete_on_terminate', 'Supprimer le compte SmarterMail à la résiliation', $ck('delete_on_terminate', 1), true) . '</div>'
        . '</div>';

    // (Section « Mot de passe » déplacée vers les Réglages globaux : politique UNIQUE
    //  pour tout le serveur, plus par forfait.)

    // ── Section : DNS ────────────────────────────────────────────────────────
    $dns = '<div style="margin:14px 0 0;">'
        . $fg('Mécanisme SPF primaire',
            '<input type="text" name="spf_primary" value="' . $v('spf_primary') . '" placeholder="include:mail.example.com" class="smx-inp">')
        . $fg('Mécanismes SPF secondaires',
            '<input type="text" name="spf_secondary" value="' . $v('spf_secondary') . '" placeholder="include:…, ip4:…" class="smx-inp">',
            'Acceptés en plus du primaire (validation). Séparés par des virgules.')
        . '<div class="smx-g2">'
        . '<div>' . $fg('Hôte Autodiscover', '<input type="text" name="autodiscover_host" value="' . $v('autodiscover_host') . '" class="smx-inp">', 'Vide = nom d\'hôte du serveur.') . '</div>'
        . '<div>' . $fg('Cible SRV Autodiscover', '<input type="text" name="srv_target" value="' . $v('srv_target') . '" class="smx-inp">', 'Vide = nom d\'hôte du serveur.') . '</div>'
        . '</div>'
        . '<div style="margin-bottom:16px;">' . $chkCard('dmarc_check', 'Vérifier / suggérer DMARC', $ck('dmarc_check', 1)) . '</div>'
        . '<input type="hidden" name="dmarc_rua" value="' . $v('dmarc_rua') . '">'
        . $fg('Politique DMARC suggérée',
            '<select name="dmarc_policy" class="smx-inp">' . $polOpt('none') . $polOpt('quarantine') . $polOpt('reject') . '</select>')
        . '</div>';

    // ── Section : Quota et limitation ────────────────────────────────────────
    // « Tranche de facturation » n'apparaît qu'en mode « À l'usage » (JS smxPlan()).
    $tierStyle = 'max-width:340px;margin-bottom:14px;' . ($planType === 'usage' ? '' : 'display:none;');
    $quota = '<div style="margin:14px 0 0;">'
        . $fg('Type de facturation',
            '<select name="plan_type" class="smx-inp" onchange="smxPlan(this.value)">'
            . $ptOpt('fixe',  'Fixe — espace maximal bloqué (le serveur refuse au-delà)')
            . $ptOpt('usage', 'À l\'usage — facturer l\'usage réel (plafonné à l\'espace max)')
            . '</select>')
        . '<div id="smx-tierwrap" style="' . $tierStyle . '">'
        . $fg('Tranche de facturation (Go)',
            '<input type="number" min="1" name="gb_per_tier" value="' . $v('gb_per_tier', '10') . '" class="smx-inp">',
            'Incrément facturé à l\'usage (ex. 10 Go). Le prix d\'une tranche = le prix du produit dans WHMCS.')
        . '</div>'
        . '<div class="smx-g2">'
        . '<div>' . $fg('Espace disque maximal (Go)', '<input type="number" min="0" name="quota_gb" value="' . $v('quota_gb', '0') . '" class="smx-inp">',
            '0 = illimité. Poussé comme limite dans SmarterMail ; en « à l\'usage », plafonne aussi la facturation (jamais au-delà).') . '</div>'
        . '<div>' . $fg('Espace disque max par boîte (Go)', '<input type="number" min="0" name="max_mailbox_size_gb" value="' . $v('max_mailbox_size_gb', '0') . '" class="smx-inp">', '0 = illimité.') . '</div>'
        . '<div>' . $fg('Nombre de comptes courriel', '<input type="number" min="0" name="max_users" value="' . $v('max_users', '0') . '" class="smx-inp">', '0 = illimité.') . '</div>'
        . '<div>' . $fg('Nombre d\'alias de domaine', '<input type="number" min="0" name="max_domain_aliases" value="' . $v('max_domain_aliases', '0') . '" class="smx-inp">', '0 = fonctionnalité désactivée.') . '</div>'
        . '</div>'
        . '<input type="hidden" name="notify_threshold_pct" value="' . $v('notify_threshold_pct', '90') . '">'
        . '</div>';

    // ── Assemblage ───────────────────────────────────────────────────────────
    $chipLabel = $isEdit ? 'Modifier' : 'Nouveau';
    $chipRef   = $isEdit ? ('Forfait #' . $id) : 'Nouveau forfait';

    $h  = '<a class="smx-back" href="' . $base . '"><i class="fas fa-arrow-left"></i> Retour à la liste</a>';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="savepackage">';
    $h .= '<input type="hidden" name="id" value="' . $id . '">';
    $h .= '<div class="smx-card">';
    $h .= '<div class="smx-card-head">'
        . '<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">'
        . '<span class="smx-chip">' . $chipLabel . '</span>'
        . '<span style="font-size:15px;font-weight:600;color:#334155;">' . _sm_billing_h($chipRef) . '</span></div>'
        . '<label class="smx-lbl">Nom du forfait</label>'
        . '<input type="text" name="name" required value="' . $v('name') . '" placeholder="Ex. Argent, Or, Entreprise…" class="smx-name">'
        . '</div>';
    $h .= '<div class="smx-acc-body-outer">';
    $h .= '<div style="display:flex;justify-content:flex-end;gap:14px;margin-bottom:14px;">'
        . '<span class="smx-toollink" onclick="smxAll(true)">Tout déplier</span>'
        . '<span style="color:#cbd5e1;">·</span>'
        . '<span class="smx-toollink mut" onclick="smxAll(false)">Tout replier</span></div>';
    $h .= $section('quota', 'fa-hdd', 'Quota et limitation', 'Type de facturation, espace disque, comptes et alias', $quota, true);
    if ($easMapiAvailable) {
        $h .= $section('proto', 'fa-envelope-open-text', 'Protocole MAPI et EAS', 'ActiveSync (EAS), MAPI/Exchange et leur tarification', $proto, false);
    } else {
        $h .= $proto; // EAS/MAPI désactivé globalement : uniquement les champs cachés, pas de section visible
    }
    $h .= $section('dns', 'fa-globe', 'DNS', 'SPF, Autodiscover et suggestions DMARC', $dns, false);
    $h .= $section('server', 'fa-server', 'Serveur', 'Chemins, IP de sortie, résiliation', $server, false);
    $h .= '</div>'; // acc-body-outer
    $h .= '<div class="smx-actionbar">'
        . '<button type="submit" class="smx-btn smx-btn-primary"><i class="fas fa-save"></i> ' . ($isEdit ? 'Enregistrer' : 'Créer le forfait') . '</button>'
        . '<a class="smx-btn smx-btn-ghost" href="' . $base . '">Annuler</a></div>';
    $h .= '</div></form>';
    return $h;
}


// ============================================================================
//  VUE — Réglages globaux
// ============================================================================

function _sm_billing_globalForm(string $csrf): string
{
    $easMapi = (bool) _sm_getGlobalSetting('eas_mapi_available', true);
    $pw      = _sm_globalPasswordPolicy();
    $ns      = _sm_getGlobalSetting('provider_nameservers', _sm_defaultNameservers());
    if (!is_array($ns)) {
        $ns = _sm_defaultNameservers();
    }
    $nsText = function (string $tab) use ($ns) {
        $list = $ns[$tab] ?? [];
        return _sm_billing_h(is_array($list) ? implode("\n", $list) : '');
    };

    $h  = '<form method="post" action="addonmodules.php?module=smartermail_billing">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="saveglobal">';
    $h .= '<div class="smx-card"><div class="smx-card-body">';

    $h .= '<div style="margin-bottom:20px;"><label class="smx-chk"><input type="checkbox" name="eas_mapi_available" value="1"'
        . ($easMapi ? ' checked' : '') . '><span>Le serveur SmarterMail propose <strong>EAS / MAPI</strong></span></label>'
        . '<p class="smx-hint">Décochez si votre licence n\'inclut pas ActiveSync/MAPI : la section EAS/MAPI sera masquée '
        . 'dans les forfaits et l\'espace client.</p></div>';

    // ── Politique de mot de passe (GLOBALE — déplacée hors des forfaits) ──────
    $h .= '<div style="font-size:15px;font-weight:700;margin:4px 0 4px;">Politique de mot de passe (toutes les boîtes)</div>';
    $h .= '<p class="smx-hint" style="margin:0 0 12px;">Règles appliquées à la création et au changement de mot de passe des boîtes, pour <strong>tous les forfaits</strong>. '
        . 'Doit correspondre à la politique configurée dans SmarterMail.</p>';
    $h .= '<div style="max-width:320px;margin-bottom:12px;"><label class="smx-lbl">Longueur minimale</label>'
        . '<input type="number" min="1" name="pwd_min_len" value="' . (int) $pw['pwd_min_len'] . '" class="smx-inp"></div>';
    $h .= '<div class="smx-g2" style="margin-bottom:22px;">'
        . '<label class="smx-chk"><input type="checkbox" name="pwd_require_upper" value="1"' . ($pw['pwd_require_upper'] ? ' checked' : '') . '><span>Exiger une lettre majuscule</span></label>'
        . '<label class="smx-chk"><input type="checkbox" name="pwd_require_lower" value="1"' . ($pw['pwd_require_lower'] ? ' checked' : '') . '><span>Exiger une lettre minuscule</span></label>'
        . '<label class="smx-chk"><input type="checkbox" name="pwd_require_digit" value="1"' . ($pw['pwd_require_digit'] ? ' checked' : '') . '><span>Exiger un chiffre</span></label>'
        . '<label class="smx-chk"><input type="checkbox" name="pwd_require_special" value="1"' . ($pw['pwd_require_special'] ? ' checked' : '') . '><span>Exiger un caractère spécial</span></label>'
        . '</div>';

    $h .= '<div style="font-size:15px;font-weight:700;margin:4px 0 4px;">Nameservers (pré-sélection de l\'onglet du guide DNS)</div>';
    $h .= '<p class="smx-hint" style="margin:0 0 16px;">Un nom de serveur par ligne. Sert uniquement à pré-sélectionner le bon '
        . 'onglet du guide DNS selon les NS du domaine du client (aucun effet fonctionnel). Videz un champ pour retomber sur les valeurs par défaut.</p>';
    $h .= '<div class="smx-g3">';
    foreach (['cpanel' => 'cPanel', 'plesk' => 'Plesk', 'clientspace' => 'ClientSpace'] as $key => $label) {
        $h .= '<div><label class="smx-lbl">' . $label . '</label>'
            . '<textarea name="ns_' . $key . '" rows="4" class="smx-inp">' . $nsText($key) . '</textarea></div>';
    }
    $h .= '</div>';

    $h .= '</div><div class="smx-actionbar"><button type="submit" class="smx-btn smx-btn-primary">'
        . '<i class="fas fa-save"></i> Enregistrer les réglages globaux</button></div>';
    $h .= '</div></form>';
    return $h;
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

/** Carte d'un produit (réglages hérités mod_sm_product_settings + actions). */
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

    $h  = '<div class="smx-card"><div class="smx-card-head" style="display:flex;align-items:center;gap:10px;">'
        . '<span style="font-size:15px;font-weight:700;">' . $name . '</span>'
        . '<span class="smx-tag">#' . $pid . '</span>'
        . '<span class="smx-sub" style="margin-left:auto;">' . $activeCount . ' service(s) actif(s)</span></div>';
    $h .= '<div class="smx-card-body">';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="savesettings">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    $h .= '<input type="hidden" name="per_mailbox_price" value="' . $e('per_mailbox_price') . '">';
    $h .= '<input type="hidden" name="included_gb" value="' . $e('included_gb') . '">';
    $h .= '<input type="hidden" name="included_mailboxes" value="' . $e('included_mailboxes') . '">';

    $h .= '<div class="smx-g3">';
    $h .= '<div><label class="smx-lbl">Modèle de facturation</label><select name="billing_model" class="smx-inp">'
        . $modelOpt('tiers', 'Par tranches disque (historique)') . $modelOpt('flat', 'Forfait fixe') . '</select></div>';
    $h .= '<div><label class="smx-lbl">Quota disque (Go)</label><input type="number" min="0" step="1" name="quota_gb" value="'
        . $e('quota_gb') . '" class="smx-inp"><p class="smx-hint">0 = illimité.</p></div>';
    $h .= '<div><label class="smx-lbl">Mode de dépassement</label><select name="overage_mode" class="smx-inp">'
        . $modeOpt('block', 'Bloquer (maxSize serveur)') . $modeOpt('bill', 'Facturer l\'excédent') . $modeOpt('notify', 'Alerter seulement')
        . '</select></div>';
    $h .= '</div>';
    $h .= '<div class="smx-g2" style="margin-top:14px;">';
    $h .= '<div><label class="smx-lbl">Prix tranche excédentaire ($)</label><input type="number" min="0" step="0.01" name="overage_price" value="'
        . $e('overage_price') . '" class="smx-inp"><p class="smx-hint">Mode « facturer » uniquement.</p></div>';
    $h .= '<div><label class="smx-lbl">Seuil d\'alerte (%)</label><input type="number" min="1" max="100" step="1" name="notify_threshold_pct" value="'
        . $e('notify_threshold_pct') . '" class="smx-inp"></div>';
    $h .= '</div>';
    $h .= '<div style="margin-top:16px;"><button type="submit" class="smx-btn smx-btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>';
    $h .= '</form>';

    // Actions annexes : appliquer au parc + convertir en forfait.
    $h .= '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #eef2f7;">';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" '
        . 'onsubmit="return confirm(\'Appliquer la limite disque de ce produit à ses ' . $activeCount . ' service(s) actif(s) ?\');" style="display:inline;">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="applylimits">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    $h .= '<button type="submit" class="smx-btn smx-btn-ghost sm"' . ($activeCount > 0 ? '' : ' disabled')
        . '><i class="fas fa-server"></i> Appliquer aux services actifs</button></form>';
    $h .= '<form method="post" action="addonmodules.php?module=smartermail_billing" '
        . 'onsubmit="return confirm(\'Créer un forfait depuis la configuration actuelle de ce produit, et le lier si aucun forfait n est encore sélectionné ?\');" style="display:inline;">';
    $h .= '<input type="hidden" name="csrf" value="' . _sm_billing_h($csrf) . '">';
    $h .= '<input type="hidden" name="action" value="convertproduct">';
    $h .= '<input type="hidden" name="product_id" value="' . $pid . '">';
    $h .= '<button type="submit" class="smx-btn smx-btn-ghost sm"><i class="fas fa-magic"></i> Convertir en forfait</button></form>';
    $h .= '<span class="smx-hint" style="margin:0;">« Appliquer » pousse <code>maxSize</code> (mode bloquer). « Convertir » crée un forfait reflétant ces options.</span>';
    $h .= '</div>';

    return $h . '</div></div>';
}

/** Vue « Réglages produits (hérité) » : une carte par produit smartermail. */
function _sm_billing_productsView(string $csrf): string
{
    $products = Capsule::table('tblproducts')
        ->where('servertype', 'smartermail')
        ->orderBy('name')
        ->get(['id', 'name']);

    $html = '<p class="smx-lead">Réglages de facturation par produit (modèle « hérité », table <code>mod_sm_product_settings</code>) — '
        . 'utilisés pour les produits <strong>sans forfait</strong>. Sans réglage, un produit conserve le comportement historique.</p>';

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
                _sm_setGlobalSetting('password_policy', [
                    'pwd_min_len'         => max(1, (int) ($_POST['pwd_min_len'] ?? 8)),
                    'pwd_require_upper'   => !empty($_POST['pwd_require_upper']),
                    'pwd_require_lower'   => !empty($_POST['pwd_require_lower']),
                    'pwd_require_digit'   => !empty($_POST['pwd_require_digit']),
                    'pwd_require_special' => !empty($_POST['pwd_require_special']),
                ]);
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

    $body = $notice;
    if ($view === 'global') {
        $body .= _sm_billing_globalForm($csrf);

    } elseif ($view === 'products') {
        $body .= _sm_billing_productsView($csrf);

    } else { // packages
        $easMapiAvailable = (bool) _sm_getGlobalSetting('eas_mapi_available', true);
        if (isset($_GET['create'])) {
            $body .= _sm_billing_packageEditor(null, $csrf, $easMapiAvailable);
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
            $body .= $row
                ? _sm_billing_packageEditor($row, $csrf, $easMapiAvailable)
                : (_sm_billing_alert('warning', 'Forfait introuvable.') . _sm_billing_packagesList($csrf));
        } else {
            $body .= _sm_billing_packagesList($csrf);
        }
    }

    echo _sm_billing_assets()
        . '<div class="smx">'
        . _sm_billing_header($view)
        . $body
        . '</div>';

  } catch (\Throwable $e) {
      logActivity('SmarterMail [billing-addon] output EXCEPTION: ' . $e->getMessage()
          . ' @ ' . $e->getFile() . ':' . $e->getLine());
      echo '<div class="alert alert-danger"><strong>Erreur du module SmarterMail Forfaits :</strong> '
          . htmlspecialchars($e->getMessage()) . '</div>';
  }
}


// ============================================================================
//  CONVERSION produit → forfait (config héritée → nouveau forfait + liaison)
// ============================================================================

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
        // Les produits hérités sont TOUJOURS à l'usage (billing_model 'tiers') → 'usage'.
        $plan = 'usage';

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
