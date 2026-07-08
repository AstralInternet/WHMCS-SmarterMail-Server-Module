{* SmarterMail — Nouvelle adresse courriel *}

{* Variables de langue injectées par Smarty dans un <script> séparé,         *}
{* avant le bloc {literal} du CSS. Sans ce séparateur, les var JS se retrouvent  *}
{* à l'intérieur du <style> et ne sont jamais exécutées par le navigateur.       *}
<script>
var SM_LANG_PRICE_BUNDLE  = '{$lang.js_price_bundle|escape:"javascript"}';
var SM_LANG_PRICE_SAVING  = '{$lang.js_price_saving|escape:"javascript"}';
var SM_LANG_PRICE_INCL    = '{$lang.js_price_included|escape:"javascript"}';
var SM_LANG_ALIAS_EMPTY   = '{$lang.js_alias_empty|escape:"javascript"}';
var SM_LANG_FWD_EMPTY     = '{$lang.js_fwd_empty|escape:"javascript"}';
var SM_LANG_BTN_REMOVE    = '{$lang.btn_remove|escape:"html"}';
</script>
<style>
{literal}
/* ── En-tête (spécifique adduser) ────────────────────────────── */
.sm-header{background:linear-gradient(135deg,var(--sm-slate) 0%,var(--sm-slate-2) 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-title{font-size:16px;font-weight:700}
.sm-header-title i{margin-right:8px;opacity:.8}

/* ── Cartes (base commune : _sm_styles.css) ─────────────────── */
.sm-card{background:#fff;border:1px solid var(--sm-border);border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:var(--sm-surface);border-bottom:1px solid var(--sm-border);padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}
.sm-form-hint{color:#aaa;font-weight:400}
.sm-info-btn{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:var(--sm-primary-tint);color:var(--sm-primary);border-radius:50%;font-size:10px;font-weight:700;cursor:pointer;border:none;line-height:1;flex-shrink:0}
.sm-info-btn:hover{background:var(--sm-primary);color:#fff}

/* ── Champ adresse + mot de passe (spécifique adduser) ──────── */
.sm-email-row{display:flex;align-items:center;gap:0;max-width:420px}
.sm-email-row input{flex:1;padding:7px 10px;border:1px solid var(--sm-border-input);border-radius:4px 0 0 4px;border-right:none;font-size:13px}
.sm-email-row input:focus{border-color:var(--sm-primary);outline:none}
.sm-email-suffix{padding:7px 12px;background:var(--sm-surface);border:1px solid var(--sm-border-input);border-radius:0 4px 4px 0;font-size:13px;color:var(--sm-text-2);white-space:nowrap}
/* Le widget mot de passe est désormais INLINE dans la carte (kit .sm-ig /
   .sm-pwd-strength / .sm-pwd-crit de _sm_styles.css) — plus de modale ni de statut. */

/* ── Actions (spécifique adduser) ───────────────────────────── */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:var(--sm-surface);border:1px solid var(--sm-border);border-radius:6px}
.sm-btn-create{color:#fff;border:none;padding:8px 20px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;background:#aaa;transition:background .2s}
.sm-btn-create.ready{background:var(--sm-success)}
.sm-btn-create.ready:hover{background:var(--sm-success-dark)}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid var(--sm-border-input);padding:8px 16px;border-radius:4px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sm-btn-cancel:hover{background:#f5f5f5;color:var(--sm-text)}
/* ── Modales : kit modale + widget mot de passe centralisés dans _sm_styles.css
   (injecté par le hook ClientAreaHeadOutput). Surcharge locale conservée. ── */
.sm-mbox.wide{max-width:480px}
{/literal}
{* Dark mode + fix <code> : injectés via hook (voir hooks.php). *}
</style>

<a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-back">
  <i class="fa fa-arrow-left"></i> {$lang.back_dashboard}
</a>

{* ── En-tête ─────────────────────────────────────────────────────── *}
<div class="sm-header">
  <div class="sm-header-title"><i class="fa fa-plus-circle"></i>{$lang.add_user_title} &mdash; {$domain}</div>
</div>

{* ── Formulaire principal ────────────────────────────────────────── *}
<form method="post" action="clientarea.php" id="form-adduser">
  <input type="hidden" name="action"       value="productdetails">
  <input type="hidden" name="id"           value="{$serviceid}">
  <input type="hidden" name="customAction" value="createuser">
  {* Jeton CSRF — validé côté PHP par _sm_checkCsrf() avant exécution *}
  {* de l'action mutative. Sans ce jeton, un site tiers pourrait soumettre *}
  {* un formulaire createuser depuis l'extérieur en exploitant la session. *}
  <input type="hidden" name="token"        value="{$csrfToken|escape}">
  {* Bannière d'erreur inline — la saisie est préservée (sauf le mot de passe). *}
  {if $formError}
    <div class="sm-form-error" role="alert">
      <i class="fa fa-exclamation-triangle"></i> {$formError|escape}
    </div>
  {/if}

  {* ── Adresse + Mot de passe ──────────────────────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header"><i class="fa fa-envelope-o"></i> {$lang.add_user_lbl_email}</div>
    <div class="sm-card-body">

      <div style="margin-bottom:14px;">
        <label class="sm-form-label" for="field-username">{$lang.add_user_lbl_email} <span style="color:#e74c3c;">*</span></label>
        <div class="sm-email-row">
          <input type="text" name="username" id="field-username"
                 value="{$prefillUsername|escape}"
                 placeholder="{$lang.add_user_ph_username}" pattern="[a-zA-Z0-9._\-]+"
                 autocomplete="off" autofocus required>
          <span class="sm-email-suffix">@{$domain|escape}</span>
        </div>
        <div style="font-size:11px;color:#aaa;margin-top:3px;">{$lang.add_user_help_email}</div>
      </div>

      <div>
        <label class="sm-form-label" for="sm-pwd-input">{$lang.add_user_lbl_pwd} <span style="color:#e74c3c;">*</span></label>
        <div class="sm-ig">
          <input type="password" name="password" id="sm-pwd-input" autocomplete="new-password"
                 placeholder="{$lang.pwd_new_label}" required>
          <div class="sm-ig-btns">
            <button type="button" onclick="smTogglePwd()"><i class="fa fa-eye" id="sm-eye-icon"></i></button>
            <button type="button" onclick="smGeneratePwd();smUpdateCreate();" title="{$lang.btn_generate_pwd}"><i class="fa fa-random"></i></button>
          </div>
        </div>
        <div class="sm-pwd-strength"><div class="sm-pwd-bar" id="sm-pwd-bar" style="width:0;background:#e74c3c;"></div></div>
        <div style="margin-top:10px;">
          <label class="sm-form-label" for="sm-pwd-confirm">{$lang.pwd_confirm_label}</label>
          <input type="password" class="sm-minput-full" id="sm-pwd-confirm"
                 placeholder="{$lang.pwd_confirm_label}" autocomplete="new-password">
        </div>
        <ul class="sm-pwd-crit">
          <li id="crit-len"><i class="fa fa-times"></i> {$lang.pwd_crit_min|replace:'%d':$pwdMinLength}</li>
          {if $pwdRequireUpper}  <li id="crit-upper"><i class="fa fa-times"></i> {$lang.pwd_crit_upper}</li>{/if}
          {if $pwdRequireNumber} <li id="crit-num"><i class="fa fa-times"></i> {$lang.pwd_crit_number}</li>{/if}
          {if $pwdRequireSpecial}<li id="crit-spec"><i class="fa fa-times"></i> {$lang.pwd_crit_special}</li>{/if}
          <li id="crit-no-user"><i class="fa fa-times"></i> {$lang.pwd_crit_no_user}</li>
          <li id="crit-no-domain"><i class="fa fa-times"></i> {$lang.pwd_crit_no_domain}</li>
          <li id="crit-match"><i class="fa fa-times"></i> {$lang.pwd_crit_match}</li>
        </ul>
      </div>

    </div>
  </div>

  {* ── Alias | Redirection ──────────────────────────────────────────── *}
  <div class="sm-2col">

    {* Alias *}
    <div class="sm-card">
      <div class="sm-card-header"><i class="fa fa-at"></i> {$lang.eu_alias_title}</div>
      <div class="sm-card-body">
        <div class="sm-pills-wrap" id="sm-alias-pills">
          <span class="sm-pills-empty">{$lang.eu_alias_empty}</span>
        </div>
        <div class="sm-inline-add">
          <input type="text" id="sm-alias-input" aria-label="{$lang.eu_alias_ph_ex}" placeholder="{$lang.eu_alias_ph_ex}"
                 pattern="[a-zA-Z0-9._-]+" autocomplete="off"
                 data-errchars="{$lang.eu_alias_err_chars}" data-errdup="{$lang.eu_alias_err_dup}">
          <span class="sm-inline-suffix">@{$domain|escape}</span>
          <button type="button" class="sm-inline-btn" onclick="smAddAlias()" title="{$lang.eu_btn_add}"><i class="fa fa-plus"></i></button>
        </div>
        <div class="sm-merr" id="sm-alias-err"></div>
        <div id="sm-alias-hidden"></div>
      </div>
    </div>

    {* Redirection *}
    <div class="sm-card">
      <div class="sm-card-header"><i class="fa fa-share"></i> {$lang.eu_fwd_title}</div>
      <div class="sm-card-body">
        <div class="sm-pills-wrap" id="sm-fwd-pills">
          <span class="sm-pills-empty">{$lang.eu_fwd_empty}</span>
        </div>
        <div class="sm-inline-add">
          <input type="text" id="sm-fwd-input" aria-label="{$lang.eu_fwd_ph_ex}" placeholder="{$lang.eu_fwd_ph_ex}"
                 autocomplete="off"
                 data-errinvalid="{$lang.eu_fwd_err_invalid}" data-errdup="{$lang.eu_fwd_err_dup}">
          <button type="button" class="sm-inline-btn" onclick="smAddFwd()" title="{$lang.eu_btn_add}"><i class="fa fa-plus"></i></button>
        </div>
        <div class="sm-merr" id="sm-fwd-err"></div>
        <div class="sm-fwd-opts">
          <label><input type="checkbox" name="fwd_keep"   id="fwd_keep"{if $prefillFwdKeep} checked{/if}>   {$lang.eu_fwd_keep}</label>
          <label><input type="checkbox" name="fwd_delete" id="fwd_delete"{if $prefillFwdDelete} checked{/if}> {$lang.eu_fwd_delete}</label>
        </div>
        <div id="sm-fwd-hidden"></div>
      </div>
    </div>

  </div>

  {* ── Paramètres ──────────────────────────────────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header"><i class="fa fa-cog"></i> {$lang.eu_settings_title}</div>
    <div class="sm-card-body">
      <div class="sm-opts-inner">

        {* Gauche : limite d'espace *}
        <div>
          <label class="sm-form-label" for="field-size">
            {$lang.eu_disk_limit} <span class="sm-form-hint">— {$lang.zero_unlimited}</span>
          </label>
          <input type="number" class="sm-number" name="mailboxsize_mb" id="field-size" value="{$prefillSize|default:0}" min="0">
        </div>

        {* Droite : protocoles *}
        <div>
          {if $canEAS}
          <div class="sm-chk-row">
            <input type="checkbox" name="enable_eas" value="1" id="chk-eas" onchange="smUpdatePrice()"{if $prefillEas} checked{/if}>
            <label for="chk-eas">{$lang.eu_eas_label}</label>
            <button type="button" class="sm-info-btn" onclick="smOpen('sm-info-eas')">i</button>
          </div>
          {/if}
          {if $canMAPI}
          <div class="sm-chk-row">
            <input type="checkbox" name="enable_mapi" value="1" id="chk-mapi" onchange="smUpdatePrice()"{if $prefillMapi} checked{/if}>
            <label for="chk-mapi">{$lang.eu_mapi_label}</label>
            <button type="button" class="sm-info-btn" onclick="smOpen('sm-info-mapi')">i</button>
          </div>
          {/if}
          {* ── Avis de facturation EAS/MAPI ──────────────────────── *}
          {if ($canEAS || $canMAPI) && $lockDays >= 1}
          <div class="sm-proto-billing-notice">
            <i class="fa fa-info-circle"></i>
            {$lang.proto_billing_notice|replace:'{days}':$lockDays}
          </div>
          {/if}

          {if $canEAS || $canMAPI}
          <div class="sm-price-box" id="sm-price-box">
            <div id="sm-price-single"></div>
            <div id="sm-price-bundle" style="display:none;color:#27ae60;font-weight:600;"></div>
          </div>
          {/if}
        </div>

      </div>
    </div>
  </div>

  {* ── Actions ─────────────────────────────────────────────────────── *}
  <div class="sm-actions">
    <a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-btn-cancel">
      {$lang.btn_cancel}
    </a>
    <button type="submit" id="btn-create" class="sm-btn-create" onclick="return smCreateValidate()">
      <i class="fa fa-check"></i> {$lang.add_user_btn}
    </button>
  </div>

</form>


{* ════ MODALES ════════════════════════════════════════════════════ *}

{* Widget mot de passe : désormais INLINE dans la carte (plus de modale). *}

{* Ajout d'alias : désormais INLINE dans la carte (plus de modale). *}

{* Ajout de redirection : désormais INLINE dans la carte (plus de modale). *}

{* ── Info : ActiveSync ─────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-info-eas" onclick="smBg(event,'sm-info-eas')">
  <div class="sm-mbox">
    <div class="sm-mhead info">
      <h4><i class="fa fa-mobile"></i> {$lang.info_eas_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-info-eas')">&times;</button>
    </div>
    <div class="sm-mbody">
      <p class="sm-mdesc">{$lang.info_eas_desc}</p>
      {if $easPrice > 0}
      <div style="margin-top:10px;padding:8px 12px;background:#e8eaf6;border-radius:4px;font-size:12px;color:#3949ab;font-weight:600;">
        <i class="fa fa-tag"></i> +{$easPrice|number_format:2} {$currencySymbol}{$lang.per_month}
      </div>
      {/if}
    </div>
    <div class="sm-mfoot"><button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-info-eas')">{$lang.btn_close_modal}</button></div>
  </div>
</div>

{* ── Info : MAPI ───────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-info-mapi" onclick="smBg(event,'sm-info-mapi')">
  <div class="sm-mbox">
    <div class="sm-mhead info">
      <h4><i class="fa fa-exchange"></i> {$lang.info_mapi_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-info-mapi')">&times;</button>
    </div>
    <div class="sm-mbody">
      <p class="sm-mdesc">{$lang.info_mapi_desc}</p>
      {if $mapiPrice > 0}
      <div style="margin-top:10px;padding:8px 12px;background:#e8eaf6;border-radius:4px;font-size:12px;color:#3949ab;font-weight:600;">
        <i class="fa fa-tag"></i> +{$mapiPrice|number_format:2} {$currencySymbol}{$lang.per_month}
      </div>
      {/if}
    </div>
    <div class="sm-mfoot"><button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-info-mapi')">{$lang.btn_close_modal}</button></div>
  </div>
</div>


<script>
var SM_EAS_PRICE    = {$easPrice|default:0};
var SM_MAPI_PRICE   = {$mapiPrice|default:0};
var SM_BUNDLE_PRICE = {$bundlePrice|default:0};
var SM_CURRENCY  = '{$currencySymbol|default:"$"|escape:"javascript"}';
var SM_PER_MONTH = '{$lang.per_month|escape:"javascript"}';
var SM_DOMAIN       = '{$domain|escape:"javascript"}';
var SM_DOMAIN_BASE  = '{$domainBase|escape:"javascript"}';
var SM_PWD_MIN      = {$pwdMinLength|default:8};
{* Alias / redirections postés à re-remplir après un échec serveur (JSON ; [] sinon). *}
var SM_INITIAL_ALIASES = {$prefillAliases nofilter};
var SM_INITIAL_FWDS    = {$prefillFwds nofilter};
{literal}
var SM_REQ_UPPER = !!document.getElementById('crit-upper');
var SM_REQ_NUM   = !!document.getElementById('crit-num');
var SM_REQ_SPEC  = !!document.getElementById('crit-spec');

var smAliases = [];
var smFwdList = [];

// ── Listeners ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  // Pré-remplissage après un échec serveur (préserver la saisie) : recharger les
  // alias et redirections postés, rendre les pills, puis rafraîchir le prix.
  if (typeof SM_INITIAL_ALIASES !== 'undefined' && Array.isArray(SM_INITIAL_ALIASES)) {
    SM_INITIAL_ALIASES.forEach(function(a){ if (a && typeof a === 'string') smAliases.push(a.toLowerCase().trim()); });
    smRenderAliasPills();
  }
  if (typeof SM_INITIAL_FWDS !== 'undefined' && Array.isArray(SM_INITIAL_FWDS)) {
    SM_INITIAL_FWDS.forEach(function(a){ if (a && typeof a === 'string') smFwdList.push(a.toLowerCase().trim()); });
    smRenderFwdPills();
  }

  var pi = document.getElementById('sm-pwd-input');
  var pc = document.getElementById('sm-pwd-confirm');
  var fu = document.getElementById('field-username');
  if (pi) pi.addEventListener('input', smUpdateCreate);
  if (pc) pc.addEventListener('input', smUpdateCreate);
  if (fu) fu.addEventListener('input', smUpdateCreate);
  smUpdateCreate();  // état initial du bouton Créer

  var ai = document.getElementById('sm-alias-input');
  var fi = document.getElementById('sm-fwd-input');
  if (ai) ai.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();smAddAlias();} });
  if (fi) fi.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();smAddFwd();} });

  smUpdatePrice();
});

// ── Modales : smOpen / smClose / smBg + fermeture Échap ────────────
// → _sm_common.js (injecté dans le <head>). Focus auto du 1er champ éditable.

// ── Validation + état du bouton Créer (widget mot de passe INLINE) ──────────
// smUpdateCreate : appelé à chaque frappe (mot de passe, confirmation, username)
// → revalide le mot de passe (smCheckPwd, partagé) et bascule l'état « prêt » du
//   bouton Créer selon la validité du mot de passe ET du nom d'utilisateur.
function smUpdateCreate() {
  var pwdOk = smCheckPwd();
  var u = ((document.getElementById('field-username') || {}).value || '').trim();
  var uOk = /^[a-z0-9][a-z0-9._\-]{0,63}$/i.test(u);
  var btn = document.getElementById('btn-create');
  if (btn) btn.classList.toggle('ready', pwdOk && uOk);
}

// Garde de soumission : bloque la création si username ou mot de passe invalide.
function smCreateValidate() {
  var u = ((document.getElementById('field-username') || {}).value || '').trim();
  if (!u) { alert('Veuillez entrer un nom d\'utilisateur.'); return false; }
  if (!smCheckPwd()) {
    var pi = document.getElementById('sm-pwd-input');
    if (pi) pi.focus();
    return false;
  }
  return true;
}

// ── Alias ─────────────────────────────────────────────────────────
// ── Alias : smAddAlias / smRemoveAlias → _sm_common.js (head). Le rendu
//   smRenderAliasPills reste LOCAL (il synchronise #sm-alias-hidden → aliases[],
//   la voie de soumission d'adduser). ──
function smRenderAliasPills() {
  var c = document.getElementById('sm-alias-pills');
  var h = document.getElementById('sm-alias-hidden');
  if (!smAliases.length) {
    c.innerHTML = '<span class="sm-pills-empty">' + SM_LANG_ALIAS_EMPTY + '</span>';
    h.innerHTML = '';
    return;
  }
  c.innerHTML = smAliases.map(function(a){
    return '<span class="sm-pill" data-name="'+escAttr(a)+'">'
          +escHtml(a)+'@'+escHtml(SM_DOMAIN)
          +'<button type="button" class="sm-pill-x" onclick="smRemoveAlias(\''+escAttr(a)+'\')" title="'+SM_LANG_BTN_REMOVE+'">&times;</button></span>';
  }).join('');
  h.innerHTML = smAliases.map(function(a){
    return '<input type="hidden" name="aliases[]" value="'+escAttr(a)+'">';
  }).join('');
}

// ── Redirection : smAddFwd / smRemoveFwd → _sm_common.js (head). Le rendu
//   smRenderFwdPills reste LOCAL (synchronise #sm-fwd-hidden → fwd_list[]). ──
function smRenderFwdPills() {
  var c = document.getElementById('sm-fwd-pills');
  var h = document.getElementById('sm-fwd-hidden');
  if (!smFwdList.length) {
    c.innerHTML = '<span class="sm-pills-empty">' + SM_LANG_FWD_EMPTY + '</span>';
    h.innerHTML = '';
    return;
  }
  c.innerHTML = smFwdList.map(function(a){
    return '<span class="sm-pill fwd" data-addr="'+escAttr(a)+'">'
          +'<i class="fa fa-share" style="font-size:10px;"></i> '+escHtml(a)
          +'<button type="button" class="sm-pill-x" onclick="smRemoveFwd(\''+escAttr(a)+'\')" title="'+SM_LANG_BTN_REMOVE+'">&times;</button></span>';
  }).join('');
  h.innerHTML = smFwdList.map(function(a){
    return '<input type="hidden" name="fwd_list[]" value="'+escAttr(a)+'">';
  }).join('');
}

// ── Prix dynamiques ───────────────────────────────────────────────
function smUpdatePrice() {
  var easEl  = document.getElementById('chk-eas');
  var mapiEl = document.getElementById('chk-mapi');
  var box    = document.getElementById('sm-price-box');
  if (!box) return;
  var easOn  = easEl  && easEl.checked;
  var mapiOn = mapiEl && mapiEl.checked;
  if (!easOn && !mapiOn) { box.classList.remove('visible'); return; }
  box.classList.add('visible');
  var single = document.getElementById('sm-price-single');
  var bundle = document.getElementById('sm-price-bundle');
  if (easOn && mapiOn) {
    single.style.display = 'none'; bundle.style.display = 'block';
    var bp = SM_BUNDLE_PRICE > 0 ? SM_BUNDLE_PRICE : (SM_EAS_PRICE + SM_MAPI_PRICE);
    var sv = (SM_EAS_PRICE + SM_MAPI_PRICE) - bp;
    var html = '<i class="fa fa-tag"></i> ' + SM_LANG_PRICE_BUNDLE + '\u00a0: <span class="sm-price-badge bundle">+'+bp.toFixed(2)+' '+SM_CURRENCY+SM_PER_MONTH+'</span>';
    if (sv > 0.005) html += ' <span class="sm-price-badge save">' + SM_LANG_PRICE_SAVING + ' '+sv.toFixed(2)+' '+SM_CURRENCY+'</span>';
    bundle.innerHTML = html;
  } else {
    bundle.style.display = 'none'; single.style.display = 'block';
    var price = easOn ? SM_EAS_PRICE : SM_MAPI_PRICE;
    var label = easOn ? 'ActiveSync' : 'MAPI / Exchange';
    single.innerHTML = price > 0
      ? '<i class="fa fa-tag"></i> '+label+'\u00a0: <span class="sm-price-badge">+'+price.toFixed(2)+' '+SM_CURRENCY+SM_PER_MONTH+'</span>'
      : '<i class="fa fa-tag"></i> '+label+'\u00a0: <span class="sm-price-badge">' + SM_LANG_PRICE_INCL + '</span>';
  }
}

// ── Utilitaires : escHtml / escAttr → _sm_common.js (head) ─────────
{/literal}
</script>
