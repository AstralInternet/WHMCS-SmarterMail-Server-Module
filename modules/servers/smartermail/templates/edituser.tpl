{* SmarterMail — Gérer une adresse courriel *}

{* Variables de langue JS dans un <script> séparé — NE PAS mettre dans <style>  *}
{* Sans cette séparation, les variables sont traitées comme du CSS et ne sont   *}
{* jamais exécutées, causant des ReferenceError dans smRenderAliasPills etc.   *}
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
/* ── En-tête : email + stockage (spécifique edituser) ───────────────── */
.sm-header{background:linear-gradient(135deg,var(--sm-slate) 0%,var(--sm-slate-2) 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-email{font-size:16px;font-weight:700;margin-bottom:8px}
.sm-header-email i{margin-right:8px;opacity:.8}
.sm-header-storage{font-size:12px;color:rgba(255,255,255,.75);margin-bottom:6px}
.sm-header-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px}
.sm-header-top .sm-header-email{margin-bottom:0}
/* color:#fff en !important : le thème clair colore les <a> en orange (couleur
   d'accent) et gagnerait sur une simple classe — on force le blanc sur l'en-tête
   foncé, dans tous les états, icône comprise. */
.sm-header-webmail{background:rgba(255,255,255,.16);color:#fff !important;border:1px solid rgba(255,255,255,.5);padding:7px 14px;border-radius:4px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;transition:background .15s}
.sm-header-webmail:hover,.sm-header-webmail:focus,.sm-header-webmail:visited{background:rgba(255,255,255,.3);color:#fff !important;text-decoration:none}
.sm-header-webmail i{color:#fff !important}
.sm-progress{height:8px;background:rgba(255,255,255,.2);border-radius:4px;overflow:hidden}
.sm-progress-bar{height:100%;border-radius:4px;transition:width .4s}
.sm-progress-bar.low{background:var(--sm-success)}.sm-progress-bar.mid{background:var(--sm-warning)}.sm-progress-bar.high{background:var(--sm-danger)}

/* ── Cartes (surcharge locale ; base commune : _sm_styles.css) ──────── */
.sm-card{background:#fff;border:1px solid var(--sm-border);border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:var(--sm-surface);border-bottom:1px solid var(--sm-border);padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}

/* ── Spécifiques : indice de formulaire, bouton info, prix ──────────── */
.sm-form-hint{color:#aaa;font-weight:400;}
.sm-info-btn{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:var(--sm-primary-tint);color:var(--sm-primary);border-radius:50%;font-size:10px;font-weight:700;cursor:pointer;border:none;line-height:1;flex-shrink:0;transition:background .15s}
.sm-info-btn:hover{background:var(--sm-primary);color:#fff}
.sm-price-bundle{color:var(--sm-success);font-weight:600}

/* ── Barre d'actions (spécifique edituser) ──────────────────────────── */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:var(--sm-surface);border:1px solid var(--sm-border);border-radius:6px;justify-content:space-between}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid var(--sm-border-input);padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.sm-btn-cancel:hover{background:#f5f5f5;color:var(--sm-text);text-decoration:none}
.sm-btn-pwd{background:var(--sm-warning);color:#fff;border:none;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.sm-btn-pwd:hover{background:var(--sm-warning-dark)}
.sm-btn-save{color:#fff;border:none;padding:8px 18px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;background:#aaa;transition:background .2s,box-shadow .2s,transform .15s}
.sm-btn-save.dirty{background:var(--sm-success);box-shadow:0 0 0 3px rgba(39,174,96,.35);transform:scale(1.04)}
.sm-btn-save.dirty:hover{background:var(--sm-success-dark)}
.sm-btn-del{background:#fff;color:var(--sm-danger);border:1px solid var(--sm-danger);padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.sm-btn-del:hover{background:var(--sm-danger-tint)}
@media(max-width:600px){.sm-btn-del{margin-left:0}}

/* Kit modale + widget mot de passe + del-warn : centralisés dans
   _sm_styles.css (injecté dans le <head> par le hook ClientAreaHeadOutput).
   (A2) Ces règles étaient copiées à l'identique dans chaque template. */
{/literal}
{* Dark mode + fix <code> : injectés via hook (voir hooks.php). *}
</style>

<a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-back">
  <i class="fa fa-arrow-left"></i> {$lang.back_dashboard}
</a>

{* ── En-tête ─────────────────────────────────────────────────────────── *}
<div class="sm-header">
  <div class="sm-header-top">
    <div class="sm-header-email"><i class="fa fa-envelope-o"></i>{$email|escape}</div>
    {* Auto-login vers le webmail de CETTE boîte (customAction=webmailsso&ssouser). *}
    <a href="clientarea.php?action=productdetails&id={$serviceid|intval}&customAction=webmailsso&ssouser={$username|escape:'url'}"
       target="_blank"
       rel="noopener noreferrer"
       class="sm-header-webmail"
       title="{$lang.btn_open_webmail|escape}">
      <i class="fa fa-external-link"></i> {$lang.btn_open_webmail}
    </a>
  </div>
  {if $maxBytes > 0}
    {if $usagePct > 85}{assign var="barCls" value="high"}
    {elseif $usagePct > 55}{assign var="barCls" value="mid"}
    {else}{assign var="barCls" value="low"}{/if}
    <div class="sm-header-storage">
      {$lang.eu_usage_title} &mdash;
      <strong style="color:#fff;">{$currentMB|number_format:2} Mo</strong> / {$maxMBInput} Mo ({$usagePct}%)
    </div>
    <div class="sm-progress">
      <div class="sm-progress-bar {$barCls}" style="width:{$usagePct}%;"></div>
    </div>
  {else}
    <div class="sm-header-storage">
      {$lang.eu_usage_title} &mdash;
      <strong style="color:#fff;">{$currentMB|number_format:2} Mo</strong>
      <span style="opacity:.6;">/ {$lang.unlimited}</span>
    </div>
  {/if}
</div>

{* Bannière de succès (flash PRG) — ex. après un changement de mot de passe. *}
{if $flashSuccess}
  <div class="sm-flash-success" role="status">
    <i class="fa fa-check-circle"></i> {$flashSuccess|escape}
  </div>
{/if}

{* Bannière d'erreur inline — la saisie est préservée (sauf le mot de passe). *}
{if $formError}
  <div class="sm-form-error" role="alert">
    <i class="fa fa-exclamation-triangle"></i> {$formError|escape}
  </div>
{/if}

{* ── Alias | Redirection ─────────────────────────────────────────────── *}
<div class="sm-2col">

  {* Alias *}
  <div class="sm-card">
    <div class="sm-card-header"><i class="fa fa-at"></i> {$lang.eu_alias_title}</div>
    <div class="sm-card-body">
      <div class="sm-pills-wrap" id="sm-alias-pills">
        {foreach $userAliases as $a}
          <span class="sm-pill" data-name="{$a|escape}">
            {$a|escape}@{$domain|escape}
            <button type="button" class="sm-pill-x" title="{$lang.btn_remove_alias}">&times;</button>
          </span>
        {foreachelse}
          <span class="sm-pills-empty">{$lang.eu_alias_empty}</span>
        {/foreach}
      </div>
      <div class="sm-inline-add">
        <input type="text" id="sm-alias-input" placeholder="{$lang.eu_alias_ph}"
               pattern="[a-zA-Z0-9._-]+" autocomplete="off"
               data-errchars="{$lang.eu_alias_err_chars}" data-errdup="{$lang.eu_alias_err_dup}">
        <span class="sm-inline-suffix">@{$domain|escape}</span>
        <button type="button" class="sm-inline-btn" onclick="smAddAlias()" title="{$lang.eu_btn_add}"><i class="fa fa-plus"></i></button>
      </div>
      <div class="sm-merr" id="sm-alias-err"></div>
      {* Champs cachés *}
      <div id="sm-alias-hidden">
        {foreach $userAliases as $a}
          <input type="hidden" name="aliases[]" value="{$a|escape}">
        {/foreach}
      </div>
      {foreach $userAliases as $a}
        <input type="hidden" name="orig_aliases[]" value="{$a|escape}">
      {/foreach}
    </div>
  </div>

  {* Redirection *}
  <div class="sm-card">
    <div class="sm-card-header"><i class="fa fa-share"></i> {$lang.eu_fwd_title}</div>
    <div class="sm-card-body">
      <div class="sm-pills-wrap" id="sm-fwd-pills">
        {foreach $fwdList as $addr}
          <span class="sm-pill fwd" data-addr="{$addr|escape}">
            <i class="fa fa-share" style="font-size:10px;"></i> {$addr|escape}
            <button type="button" class="sm-pill-x" title="{$lang.btn_remove}">&times;</button>
          </span>
        {foreachelse}
          <span class="sm-pills-empty">{$lang.eu_fwd_empty}</span>
        {/foreach}
      </div>
      <div class="sm-inline-add">
        <input type="text" id="sm-fwd-input" placeholder="{$lang.eu_fwd_ph}"
               autocomplete="off"
               data-errinvalid="{$lang.eu_fwd_err_invalid}" data-errdup="{$lang.eu_fwd_err_dup}">
        <button type="button" class="sm-inline-btn" onclick="smAddFwd()" title="{$lang.eu_btn_add}"><i class="fa fa-plus"></i></button>
      </div>
      <div class="sm-merr" id="sm-fwd-err"></div>
      <div class="sm-fwd-opts">
        <label><input type="checkbox" name="fwd_keep"   id="fwd_keep"   {if $fwdKeep}checked{/if}   onchange="smMarkDirty()"> {$lang.eu_fwd_keep}</label>
        <label><input type="checkbox" name="fwd_delete" id="fwd_delete" {if $fwdDelete}checked{/if} onchange="smMarkDirty()"> {$lang.eu_fwd_delete}</label>
      </div>
      <input type="hidden" name="fwd_spam" value="{$fwdSpam|escape}">
      <input type="hidden" name="fwd_updated" value="1">
      <div id="sm-fwd-hidden">
        {foreach $fwdList as $addr}
          <input type="hidden" name="fwd_list[]" value="{$addr|escape}">
        {/foreach}
      </div>
    </div>
  </div>

</div>

{* ── Options ─────────────────────────────────────────────────────────── *}
<div class="sm-card">
  <div class="sm-card-header"><i class="fa fa-cog"></i> {$lang.eu_settings_title}</div>
  <div class="sm-card-body">
    <div class="sm-opts-inner">

      {* Gauche — limite de stockage *}
      <div>
        <label class="sm-form-label">
          {$lang.eu_disk_limit}
          <span class="sm-form-hint">— {$lang.zero_unlimited}</span>
        </label>
        <input type="number" class="sm-number" name="mailboxsize_mb" id="field-size"
               value="{$maxMBInput}" min="0" onchange="smMarkDirty()">
      </div>

      {* Droite — protocoles *}
      <div>
        {if $canEAS}
        <div class="sm-chk-row">
          <input type="checkbox" name="enable_eas" value="1" id="chk-eas"
                 {if $easEnabled}checked{/if} onchange="smMarkDirty();smUpdatePrice()">
          <label for="chk-eas">{$lang.eu_eas_label}</label>
          <button type="button" class="sm-info-btn" onclick="smOpen('sm-info-eas')" title="{$lang.btn_more_info}">i</button>
        </div>
        {/if}

        {if $canMAPI}
        <div class="sm-chk-row">
          <input type="checkbox" name="enable_mapi" value="1" id="chk-mapi"
                 {if $mapiEnabled}checked{/if} onchange="smMarkDirty();smUpdatePrice()">
          <label for="chk-mapi">{$lang.eu_mapi_label}</label>
          <button type="button" class="sm-info-btn" onclick="smOpen('sm-info-mapi')" title="{$lang.btn_more_info}">i</button>
        </div>
        {/if}

        {* ── Avis de facturation — affiché si lockDays >= 1 et au moins un protocole disponible *}
        {if ($canEAS || $canMAPI) && $lockDays >= 1}
        <div class="sm-proto-billing-notice">
          <i class="fa fa-info-circle"></i>
          {$lang.proto_billing_notice|replace:'{days}':$lockDays}
        </div>
        {/if}

        {if $canEAS || $canMAPI}
        <div class="sm-price-box" id="sm-price-box">
          <div id="sm-price-single"></div>
          <div id="sm-price-bundle" class="sm-price-bundle" style="display:none;"></div>
        </div>
        {/if}
      </div>

    </div>
  </div>
</div>

{* ── Barre d'actions ─────────────────────────────────────────────────── *}
{* Supprimer seul à gauche — Mot de passe + Sauvegarder groupés à droite  *}
<div class="sm-actions">

  {* ── Gauche : Supprimer ─────────────────────────────────────── *}
  <button type="button" class="sm-btn-del" onclick="smOpen('sm-del-modal')"
          style="margin-left:0;">
    <i class="fa fa-trash"></i> {$lang.eu_btn_delete}
  </button>

  {* ── Droite : Annuler + Mot de passe + Sauvegarder ──────────── *}
  {* Le bouton "Annuler" renvoie au tableau de bord du service sans   *}
  {* soumettre le formulaire — duplique l'action du lien "Retour"     *}
  {* en haut de page mais le rend accessible depuis la barre d'action *}
  {* en bas (ergonomie : éviter au client de remonter en haut).       *}
  <div style="display:flex;gap:10px;align-items:center;">
    <a href="clientarea.php?action=productdetails&id={$serviceid}"
       class="sm-btn-cancel">
      <i class="fa fa-times"></i> {$lang.btn_cancel}
    </a>
    <button type="button" class="sm-btn-pwd" onclick="smOpen('sm-pwd-modal')">
      <i class="fa fa-key"></i> {$lang.eu_btn_pwd}
    </button>
    <button type="button" id="sm-save-btn" class="sm-btn-save" onclick="smSave()">
      <i class="fa fa-save"></i> {$lang.eu_btn_save}
    </button>
  </div>

</div>

{* ── Formulaire caché ────────────────────────────────────────────────── *}
<form method="post" action="clientarea.php" id="sm-save-form" style="display:none;">
  <input type="hidden" name="action"       value="productdetails">
  <input type="hidden" name="id"           value="{$serviceid}">
  <input type="hidden" name="customAction" value="saveuser">
  {* Jeton CSRF — bloque les requêtes inter-sites (voir _sm_checkCsrf). *}
  <input type="hidden" name="token"        value="{$csrfToken|escape}">
  <input type="hidden" name="selectuser"   value="{$username|escape}">
  <input type="hidden" name="mailboxsize_mb" id="hid-size">
  {if $canEAS}<input type="hidden" name="enable_eas"  id="hid-eas">{/if}
  {if $canMAPI}<input type="hidden" name="enable_mapi" id="hid-mapi">{/if}
  {* ── État précédent EAS/MAPI — nécessaire pour détecter les transitions ON→OFF et OFF→ON ── *}
  {* saveuser() compare was_eas/was_mapi avec enable_eas/enable_mapi pour enregistrer          *}
  {* précisément les activations/désactivations dans mod_sm_proto_usage.                       *}
  {if $canEAS}<input type="hidden" name="was_eas"  value="{if $easWas}1{else}0{/if}">{/if}
  {if $canMAPI}<input type="hidden" name="was_mapi" value="{if $mapiWas}1{else}0{/if}">{/if}
  <input type="hidden" name="fwd_spam"    value="{$fwdSpam|escape}">
  <input type="hidden" name="fwd_updated" value="1">
  <div id="hid-aliases"></div>
  <div id="hid-orig-aliases">
    {foreach $userAliases as $a}<input type="hidden" name="orig_aliases[]" value="{$a|escape}">{/foreach}
  </div>
  <div id="hid-fwd-list"></div>
  <div id="hid-fwd-opts"></div>
</form>


{* ════════ MODALES ════════════════════════════════════════════════════ *}

{* Ajout d'alias : désormais INLINE dans la carte (plus de modale). *}

{* Ajout de redirection : désormais INLINE dans la carte (plus de modale). *}

{* ── Info : ActiveSync ───────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-info-eas" onclick="smBg(event,'sm-info-eas')">
  <div class="sm-mbox">
    <div class="sm-mhead info">
      <h4><i class="fa fa-mobile"></i> {$lang.info_eas_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-info-eas')">&times;</button>
    </div>
    <div class="sm-mbody">
      <p class="sm-mdesc">
        {$lang.info_eas_desc}
      </p>
      <p class="sm-mdesc">
        Idéal pour iPhone et Android, sans configuration complexe.
      </p>
      {if $easPrice > 0}
      <div style="margin-top:10px;padding:8px 12px;background:#e8eaf6;border-radius:4px;font-size:12px;color:#3949ab;font-weight:600;">
        <i class="fa fa-tag"></i> +{$easPrice|number_format:2} $/mois
      </div>
      {/if}
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-info-eas')">{$lang.btn_close_modal}</button>
    </div>
  </div>
</div>

{* ── Info : MAPI ─────────────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-info-mapi" onclick="smBg(event,'sm-info-mapi')">
  <div class="sm-mbox">
    <div class="sm-mhead info">
      <h4><i class="fa fa-exchange"></i> {$lang.info_mapi_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-info-mapi')">&times;</button>
    </div>
    <div class="sm-mbody">
      <p class="sm-mdesc">
        {$lang.info_mapi_desc}
      </p>
      <p class="sm-mdesc">
        Recommandé pour les utilisateurs Outlook en entreprise.
      </p>
      {if $mapiPrice > 0}
      <div style="margin-top:10px;padding:8px 12px;background:#e8eaf6;border-radius:4px;font-size:12px;color:#3949ab;font-weight:600;">
        <i class="fa fa-tag"></i> +{$mapiPrice|number_format:2} $/mois
      </div>
      {/if}
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-info-mapi')">{$lang.btn_close_modal}</button>
    </div>
  </div>
</div>

{* ── Mot de passe ────────────────────────────────────────────────────── *}
{* ── Modal : Changement de mot de passe ─────────────────────────────────────── *}
{* Variables requises : $serviceid, $username, $domain, $domainBase,            *}
{*                      $pwdMinLength, $pwdRequireUpper, $pwdRequireNumber,     *}
{*                      $pwdRequireSpecial, $lang                               *}
{* Note : PAS de onclick="smBg(event,...)" — ferme uniquement via X / Annuler  *}

<div class="sm-overlay" id="sm-pwd-modal">
  <div class="sm-mbox" style="max-width:480px;">
    <div class="sm-mhead pwd">
      <h4><i class="fa fa-key"></i> {$lang.pwd_modal_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-pwd-modal')">&times;</button>
    </div>
    <form method="post" action="clientarea.php" id="sm-pwd-form">
      <input type="hidden" name="action"       value="productdetails">
      <input type="hidden" name="id"           value="{$serviceid}">
      <input type="hidden" name="customAction" value="savepassword">
      {* Jeton CSRF — empêche un changement de mot de passe déclenché *}
      {* depuis un site tiers via la session WHMCS du client.         *}
      <input type="hidden" name="token"        value="{$csrfToken|escape}">
      <input type="hidden" name="selectuser"   value="{$username|escape}">
      <div class="sm-mbody">
<div style="margin-bottom:14px;">
          <label class="sm-mlabel">{$lang.pwd_new_label}</label>
          <div class="sm-ig">
            <input type="password" id="sm-pwd-input" name="password"
                   placeholder="{$lang.pwd_new_label}" autocomplete="new-password">
            <div class="sm-ig-btns">
              <button type="button" onclick="smTogglePwd()" title="{$lang.btn_show_pwd}">
                <i class="fa fa-eye" id="sm-eye-icon"></i>
              </button>
              <button type="button" onclick="smGeneratePwd()" title="{$lang.btn_generate}">
                <i class="fa fa-random"></i> ⟳
              </button>
            </div>
          </div>
          <div class="sm-pwd-strength"><div class="sm-pwd-bar" id="sm-pwd-bar" style="width:0;background:#e74c3c;"></div></div>
        </div>
        
        <div style="margin-bottom:10px;">
          <label class="sm-mlabel">{$lang.pwd_confirm_label}</label>
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
      <div class="sm-mfoot">
        <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-pwd-modal')">{$lang.btn_cancel}</button>
        <button type="submit" class="btn btn-warning btn-sm" id="sm-pwd-submit" disabled>
          <i class="fa fa-key"></i> {$lang.pwd_change_btn}
        </button>
      </div>
    </form>
  </div>
</div>

<script>
var SM_PWD_MIN    = {$pwdMinLength|default:8};
var SM_USERNAME   = '{$username|escape:"javascript"}';
var SM_DOMAIN     = '{$domain|escape:"javascript"}';
var SM_DOMAIN_BASE = '{$domainBase|escape:"javascript"}';
{literal}
var SM_REQ_UPPER = !!document.getElementById('crit-upper');
var SM_REQ_NUM   = !!document.getElementById('crit-num');
var SM_REQ_SPEC  = !!document.getElementById('crit-spec');

// Initialiser les listeners après rendu
document.addEventListener('DOMContentLoaded', function() {
  var pi = document.getElementById('sm-pwd-input');
  var pc = document.getElementById('sm-pwd-confirm');
  if (pi) pi.addEventListener('input', smCheckPwd);
  if (pc) pc.addEventListener('input', smCheckPwd);
});

// ── Widget mot de passe : smTogglePwd / smGeneratePwd / smCrit / smCheckPwd ──
// → _sm_common.js (injecté dans le <head>). Dépend des globals SM_PWD_MIN,
//   SM_REQ_*, SM_DOMAIN(_BASE), SM_USERNAME déclarés dans le préambule ci-dessus.
{/literal}
</script>

{* ── Suppression ─────────────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-del-modal" onclick="smBg(event,'sm-del-modal')">
  <div class="sm-mbox" style="max-width:460px;">
    <div class="sm-mhead del">
      <h4><i class="fa fa-trash"></i> {$lang.del_modal_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-del-modal')">&times;</button>
    </div>
    <div class="sm-mbody" style="text-align:center;">
      <i class="fa fa-exclamation-triangle fa-3x" style="color:#e74c3c;margin-bottom:14px;display:block;"></i>
      <p style="font-size:14px;font-weight:600;">{$lang.del_irreversible}</p>
      <p style="font-size:13px;color:#555;">
        Tous les courriels de <strong>{$email|escape}</strong><br>{$lang.del_irreversible}
      </p>
      <div class="sm-del-warn">
        <i class="fa fa-info-circle"></i> {$lang.del_no_recovery}
      </div>
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-del-modal')">{$lang.btn_cancel}</button>
      <form method="post" action="clientarea.php" style="display:inline;">
        <input type="hidden" name="action"       value="productdetails">
        <input type="hidden" name="id"           value="{$serviceid}">
        <input type="hidden" name="customAction" value="deleteuser">
        {* Jeton CSRF — la suppression d'une boîte est irréversible : *}
        {* protection critique contre une requête forgée.             *}
        <input type="hidden" name="token"        value="{$csrfToken|escape}">
        <input type="hidden" name="selectuser"   value="{$username|escape}">
        <button type="submit" class="btn btn-danger btn-sm">
          <i class="fa fa-trash"></i> {$lang.del_confirm_btn}
        </button>
      </form>
    </div>
  </div>
</div>


<script>
var SM_EAS_PRICE    = {$easPrice|default:0};
var SM_MAPI_PRICE   = {$mapiPrice|default:0};
var SM_BUNDLE_PRICE = {$bundlePrice|default:0};
var SM_DOMAIN       = '{$domain|escape:"javascript"}';
var SM_LOCK_DAYS    = {$lockDays|default:1};

{literal}

// ── État ──────────────────────────────────────────────────────────────
var smDirty   = false;
var smAliases = [];
var smFwdList = [];

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {

  // Alias existants
  document.querySelectorAll('#sm-alias-pills .sm-pill').forEach(function(p) {
    var n = p.dataset.name; if (n) smAliases.push(n);
    p.querySelector('.sm-pill-x').addEventListener('click', function() { smRemoveAlias(n); });
  });

  // Forwards existants
  document.querySelectorAll('#sm-fwd-pills .sm-pill').forEach(function(p) {
    var a = p.dataset.addr; if (a) smFwdList.push(a);
    p.querySelector('.sm-pill-x').addEventListener('click', function() { smRemoveFwd(a); });
  });

  // Surveillance des champs pour l'état "modifié"
  ['field-size','chk-eas','chk-mapi','fwd_keep','fwd_delete'].forEach(function(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', smMarkDirty);
    if (el.type === 'number' || el.type === 'text') el.addEventListener('input', smMarkDirty);
  });

  // Touche Entrée dans les inputs de modales
  var ai = document.getElementById('sm-alias-input');
  var fi = document.getElementById('sm-fwd-input');
  if (ai) ai.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();smAddAlias();} });
  if (fi) fi.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();smAddFwd();} });

  // Init affichage prix
  smUpdatePrice();
});

// ── Helpers modales : smOpen / smClose / smBg + fermeture Échap ────────────
// → _sm_common.js (injecté dans le <head>). Le smOpen partagé gère le focus du
//   premier champ éditable de la modale.

// ── État modifié ──────────────────────────────────────────────────────
function smMarkDirty() {
  smDirty = true;
  var btn = document.getElementById('sm-save-btn');
  if (btn && btn.className.indexOf('dirty') === -1) btn.className += ' dirty';
}
window.addEventListener('beforeunload', function(e) {
  if (smDirty) { e.preventDefault(); e.returnValue = ''; return ''; }
});

// ── Sauvegarde ────────────────────────────────────────────────────────
function smSave() {
  document.getElementById('hid-size').value = document.getElementById('field-size').value;

  var easEl = document.getElementById('chk-eas'), hidEas = document.getElementById('hid-eas');
  if (easEl && hidEas) hidEas.value = easEl.checked ? '1' : '';

  var mapiEl = document.getElementById('chk-mapi'), hidMapi = document.getElementById('hid-mapi');
  if (mapiEl && hidMapi) hidMapi.value = mapiEl.checked ? '1' : '';

  document.getElementById('hid-aliases').innerHTML = smAliases.map(function(a){
    return '<input type="hidden" name="aliases[]" value="'+escAttr(a)+'">';
  }).join('');

  document.getElementById('hid-fwd-list').innerHTML = smFwdList.map(function(a){
    return '<input type="hidden" name="fwd_list[]" value="'+escAttr(a)+'">';
  }).join('');

  var keepEl = document.getElementById('fwd_keep'), delEl = document.getElementById('fwd_delete');
  var opts = '';
  if (keepEl && keepEl.checked) opts += '<input type="hidden" name="fwd_keep" value="1">';
  if (delEl  && delEl.checked)  opts += '<input type="hidden" name="fwd_delete" value="1">';
  document.getElementById('hid-fwd-opts').innerHTML = opts;

  smDirty = false;
  // Anti double-soumission : l'envoi se fait en JS (form.submit() ne déclenche
  // pas le verrou global) → on verrouille explicitement (bouton Save hors du form).
  var f = document.getElementById('sm-save-form');
  if (!smLockForm(f, document.getElementById('sm-save-btn'))) return;  // envoi déjà en cours
  f.submit();
}

// ── Alias ─────────────────────────────────────────────────────────────
// ── Alias : smAddAlias / smRemoveAlias → _sm_common.js (head). Le rendu
//   smRenderAliasPills reste LOCAL ici (soumission via smSave()→#hid-aliases,
//   donc ne synchronise PAS #sm-alias-hidden — contrairement à adduser). ──
function smRenderAliasPills() {
  var c = document.getElementById('sm-alias-pills');
  if (!smAliases.length) { c.innerHTML = '<span class="sm-pills-empty">' + SM_LANG_ALIAS_EMPTY + '</span>'; return; }
  c.innerHTML = smAliases.map(function(a){
    return '<span class="sm-pill" data-name="'+escAttr(a)+'">'+escHtml(a)+'@'+escHtml(SM_DOMAIN)
          +'<button type="button" class="sm-pill-x" onclick="smRemoveAlias(\''+escAttr(a)+'\')" title="'+SM_LANG_BTN_REMOVE+'">&times;</button></span>';
  }).join('');
}

// ── Redirection : smAddFwd / smRemoveFwd → _sm_common.js (head). Le rendu
//   smRenderFwdPills reste LOCAL ici (même raison que les alias). ──
function smRenderFwdPills() {
  var c = document.getElementById('sm-fwd-pills');
  if (!smFwdList.length) { c.innerHTML = '<span class="sm-pills-empty">' + SM_LANG_FWD_EMPTY + '</span>'; return; }
  c.innerHTML = smFwdList.map(function(a){
    return '<span class="sm-pill fwd" data-addr="'+escAttr(a)+'">'
          +'<i class="fa fa-share" style="font-size:10px;"></i> '+escHtml(a)
          +'<button type="button" class="sm-pill-x" onclick="smRemoveFwd(\''+escAttr(a)+'\')" title="'+SM_LANG_BTN_REMOVE+'">&times;</button></span>';
  }).join('');
}

// ── Prix dynamiques ───────────────────────────────────────────────────
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
    single.style.display = 'none';
    bundle.style.display = 'block';
    var bprice = SM_BUNDLE_PRICE > 0 ? SM_BUNDLE_PRICE : (SM_EAS_PRICE + SM_MAPI_PRICE);
    var saved  = (SM_EAS_PRICE + SM_MAPI_PRICE) - bprice;
    var html   = '<i class="fa fa-tag"></i> ' + SM_LANG_PRICE_BUNDLE + '&nbsp;: <span class="sm-price-badge bundle">+'+bprice.toFixed(2)+' $/mois</span>';
    if (saved > 0.005) html += ' <span class="sm-price-badge save">' + SM_LANG_PRICE_SAVING + ' '+saved.toFixed(2)+' $</span>';
    bundle.innerHTML = html;
  } else {
    bundle.style.display = 'none';
    single.style.display = 'block';
    var price = easOn ? SM_EAS_PRICE : SM_MAPI_PRICE;
    var label = easOn ? 'ActiveSync' : 'MAPI / Exchange';
    single.innerHTML = price > 0
      ? '<i class="fa fa-tag"></i> '+label+'&nbsp;: <span class="sm-price-badge">+'+price.toFixed(2)+' $/mois</span>'
      : '<i class="fa fa-tag"></i> '+label+'&nbsp;: <span class="sm-price-badge">' + SM_LANG_PRICE_INCL + '</span>';
  }
}

// ── Utilitaires : escHtml / escAttr → _sm_common.js (head) ────────────────

// ── Mot de passe — fonctions définies ci-dessus (smTogglePwd, smGeneratePwd, smCrit, smCheckPwd)
{/literal}
</script>
