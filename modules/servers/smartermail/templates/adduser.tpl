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
var SM_LANG_PWD_DEFINED   = '{$lang.js_pwd_defined|escape:"javascript"}';
</script>
<style>
{literal}
/* ── Layout identique à edituser ──────────────────────────── */
.sm-back{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#777;text-decoration:none;margin-bottom:14px}
.sm-back:hover{color:#3949ab}
.sm-header{background:linear-gradient(135deg,#2c3e50 0%,#3d5166 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-title{font-size:16px;font-weight:700}
.sm-header-title i{margin-right:8px;opacity:.8}
.sm-card{background:#fff;border:1px solid #e0e0e0;border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:#f7f8fa;border-bottom:1px solid #e0e0e0;padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}
/* ── Grille Alias | Redirection ─────────────────────────────── */
.sm-2col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
@media(max-width:600px){.sm-2col{grid-template-columns:1fr}}
.sm-2col .sm-card{margin-bottom:0}
/* ── Pills ──────────────────────────────────────────────────── */
.sm-pills-wrap{display:flex;flex-wrap:wrap;gap:5px;min-height:28px;margin-bottom:10px}
.sm-pill{display:inline-flex;align-items:center;gap:5px;background:#e8eaf6;color:#3949ab;border-radius:20px;padding:3px 10px 3px 12px;font-size:12px;font-weight:500}
.sm-pill.fwd{background:#e8f5e9;color:#2e7d32}
.sm-pill-x{background:none;border:none;cursor:pointer;padding:0;line-height:1;font-size:14px;color:inherit;opacity:.6;display:flex;align-items:center}
.sm-pill-x:hover{opacity:1}
.sm-pills-empty{font-size:12px;color:#bbb;font-style:italic;padding:2px 0}
/* ── Bouton + Ajouter ───────────────────────────────────────── */
.sm-add-trigger{display:flex;justify-content:flex-end;margin-top:6px}
.sm-btn-add{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px dashed #3949ab;color:#3949ab;border-radius:4px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.sm-btn-add:hover{background:#3949ab;color:#fff;border-style:solid}
/* ── Options ─────────────────────────────────────────────────── */
.sm-opts-inner{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
@media(max-width:600px){.sm-opts-inner{grid-template-columns:1fr}}
.sm-form-label{display:block;font-size:12px;color:#666;margin-bottom:4px;font-weight:600}
.sm-form-hint{color:#aaa;font-weight:400}
input.sm-number{max-width:160px;width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box}
input.sm-number:focus{border-color:#3949ab;outline:none}
.sm-chk-row{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px}
.sm-chk-row label{margin:0;cursor:pointer;font-weight:500;color:#333}
.sm-info-btn{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:#e8eaf6;color:#3949ab;border-radius:50%;font-size:10px;font-weight:700;cursor:pointer;border:none;line-height:1;flex-shrink:0}
.sm-info-btn:hover{background:#3949ab;color:#fff}
/* ── Champ mot de passe readonly ───────────────────────────── */
.sm-email-row{display:flex;align-items:center;gap:0;max-width:420px}
.sm-email-row input{flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:4px 0 0 4px;border-right:none;font-size:13px}
.sm-email-row input:focus{border-color:#3949ab;outline:none}
.sm-email-suffix{padding:7px 12px;background:#f7f8fa;border:1px solid #ddd;border-radius:0 4px 4px 0;font-size:13px;color:#666;white-space:nowrap}
.sm-pwd-row{display:flex;align-items:center;gap:10px;margin-top:6px}
.sm-pwd-status{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #ddd;border-radius:4px;background:#f7f8fa;font-size:12px;color:#aaa;min-width:180px}
.sm-pwd-status.set{color:#27ae60;border-color:#a5d6a7;background:#f1f9f1}
.sm-btn-setpwd{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:#f39c12;color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer}
.sm-btn-setpwd:hover{background:#e67e22}
.sm-fwd-opts{display:flex;gap:14px;margin-top:8px;font-size:12px;color:#666}
.sm-fwd-opts label{display:flex;align-items:center;gap:5px;cursor:pointer}
/* ── Prix ────────────────────────────────────────────────────── */
.sm-price-box{margin-top:10px;padding:8px 12px;border-radius:4px;font-size:12px;background:#f7f8fa;border:1px solid #e0e0e0;display:none}
.sm-proto-billing-notice{margin-top:8px;font-size:11px;color:#757575;display:flex;align-items:flex-start;gap:5px;line-height:1.5}
.sm-proto-billing-notice .fa{color:#f39c12;flex-shrink:0;margin-top:2px}
.sm-price-box.visible{display:block}
.sm-price-badge{display:inline-block;background:#e8eaf6;color:#3949ab;border-radius:3px;padding:1px 7px;font-size:11px;margin-left:4px;font-weight:600}
.sm-price-badge.bundle{background:#e8f5e9;color:#2e7d32}
.sm-price-badge.save{background:#fff3cd;color:#856404;font-size:10px}
/* ── Actions ─────────────────────────────────────────────────── */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:#f7f8fa;border:1px solid #e0e0e0;border-radius:6px}
.sm-btn-create{color:#fff;border:none;padding:8px 20px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;background:#aaa;transition:background .2s}
.sm-btn-create.ready{background:#27ae60}
.sm-btn-create.ready:hover{background:#219a52}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid #ddd;padding:8px 16px;border-radius:4px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sm-btn-cancel:hover{background:#f5f5f5;color:#333}
/* ── Modales ─────────────────────────────────────────────────── */
.sm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center}
.sm-overlay.open{display:flex}
.sm-mbox{background:#fff;border-radius:8px;width:100%;max-width:420px;margin:16px;box-shadow:0 8px 32px rgba(0,0,0,.2);animation:smFadeIn .18s ease}
.sm-mbox.wide{max-width:480px}
@keyframes smFadeIn{from{transform:translateY(-12px);opacity:0}to{transform:translateY(0);opacity:1}}
.sm-mhead{padding:13px 16px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
.sm-mhead h4{margin:0;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.sm-mclose{background:none;border:none;font-size:20px;cursor:pointer;color:#999;line-height:1;padding:0}
.sm-mclose:hover{color:#333}
.sm-mbody{padding:18px}
.sm-mfoot{padding:12px 16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px}
.sm-mhead.pwd{background:#f39c12;border-bottom:none}
.sm-mhead.pwd h4,.sm-mhead.pwd .sm-mclose{color:#fff}
.sm-mhead.info{background:#e8eaf6;border-bottom:1px solid #c5cae9}
.sm-mhead.info h4{color:#3949ab}
.sm-mlabel{display:block;font-size:12px;color:#666;font-weight:600;margin-bottom:4px}
.sm-minput-full{width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box;text-decoration:none}
.sm-minput-full:focus{border-color:#3949ab;outline:none}
.sm-minput-row{display:flex;align-items:center;gap:6px}
.sm-minput-row input[type="text"]{flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px}
.sm-minput-row input[type="text"]::placeholder{color:#bbb}
.sm-minput-row input[type="text"]:focus{border-color:#3949ab;outline:none}
.sm-minput-suffix{font-size:12px;color:#888;white-space:nowrap}
.sm-merr{color:#e74c3c;font-size:12px;margin-top:6px;display:none}
.sm-mdesc{font-size:13px;color:#555;line-height:1.6;margin:0 0 8px}
/* ── Widget mot de passe ─────────────────────────────────────── */
.sm-ig{display:flex}
.sm-ig input{flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:4px 0 0 4px;border-right:none;font-size:13px;text-decoration:none}
.sm-ig input::placeholder{color:#bbb}
.sm-ig input:focus{border-color:#3949ab;outline:none}
.sm-ig-btns{display:flex}
.sm-ig-btns button{padding:7px 10px;border:1px solid #ddd;background:#f7f8fa;cursor:pointer;font-size:13px;color:#555}
.sm-ig-btns button:last-child{border-radius:0 4px 4px 0}
.sm-ig-btns button:hover{background:#eee}
.sm-pwd-strength{height:4px;background:#eee;border-radius:2px;margin:6px 0 4px}
.sm-pwd-bar{height:100%;border-radius:2px;transition:width .3s,background .3s}
.sm-pwd-crit{list-style:none;padding:0;margin:8px 0 0;font-size:12px}
.sm-pwd-crit li{padding:3px 0;display:flex;align-items:center;gap:6px;color:#aaa}
.sm-pwd-crit li.ok{color:#27ae60}
{/literal}
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
  <input type="hidden" name="password"     id="hid-password" value="">

  {* ── Adresse + Mot de passe ──────────────────────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header"><i class="fa fa-envelope-o"></i> {$lang.add_user_lbl_email}</div>
    <div class="sm-card-body">

      <div style="margin-bottom:14px;">
        <label class="sm-form-label">{$lang.add_user_lbl_email} <span style="color:#e74c3c;">*</span></label>
        <div class="sm-email-row">
          <input type="text" name="username" id="field-username"
                 placeholder="{$lang.add_user_ph_username}" pattern="[a-zA-Z0-9._\-]+"
                 autocomplete="off" autofocus required>
          <span class="sm-email-suffix">@{$domain|escape}</span>
        </div>
        <div style="font-size:11px;color:#aaa;margin-top:3px;">{$lang.add_user_help_email}</div>
      </div>

      <div>
        <label class="sm-form-label">{$lang.add_user_lbl_pwd} <span style="color:#e74c3c;">*</span></label>
        <div class="sm-pwd-row">
          <div class="sm-pwd-status" id="pwd-status-display">
            <i class="fa fa-lock"></i> {$lang.pwd_not_set}
          </div>
          <button type="button" class="sm-btn-setpwd" onclick="smOpen('sm-setpwd-modal')">
            <i class="fa fa-key"></i> {$lang.pwd_btn_define}
          </button>
        </div>
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
        <div class="sm-add-trigger">
          <button type="button" class="sm-btn-add" onclick="smOpen('sm-alias-modal')">
            <i class="fa fa-plus"></i> {$lang.eu_btn_add}
          </button>
        </div>
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
        <div class="sm-add-trigger">
          <button type="button" class="sm-btn-add" onclick="smOpen('sm-fwd-modal')">
            <i class="fa fa-plus"></i> {$lang.eu_btn_add}
          </button>
        </div>
        <div class="sm-fwd-opts">
          <label><input type="checkbox" name="fwd_keep"   id="fwd_keep">   {$lang.eu_fwd_keep}</label>
          <label><input type="checkbox" name="fwd_delete" id="fwd_delete"> {$lang.eu_fwd_delete}</label>
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
          <label class="sm-form-label">
            {$lang.eu_disk_limit} <span class="sm-form-hint">— {$lang.zero_unlimited}</span>
          </label>
          <input type="number" class="sm-number" name="mailboxsize_mb" value="0" min="0">
        </div>

        {* Droite : protocoles *}
        <div>
          {if $canEAS}
          <div class="sm-chk-row">
            <input type="checkbox" name="enable_eas" value="1" id="chk-eas" onchange="smUpdatePrice()">
            <label for="chk-eas">{$lang.eu_eas_label}</label>
            <button type="button" class="sm-info-btn" onclick="smOpen('sm-info-eas')">i</button>
          </div>
          {/if}
          {if $canMAPI}
          <div class="sm-chk-row">
            <input type="checkbox" name="enable_mapi" value="1" id="chk-mapi" onchange="smUpdatePrice()">
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

{* ── Définir mot de passe (ne soumet PAS — remplit le champ caché) ── *}
<div class="sm-overlay" id="sm-setpwd-modal">
  <div class="sm-mbox wide">
    <div class="sm-mhead pwd">
      <h4><i class="fa fa-key"></i> {$lang.pwd_modal_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-setpwd-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <div style="margin-bottom:14px;">
        <label class="sm-mlabel">{$lang.pwd_new_label}</label>
        <div class="sm-ig">
          <input type="password" id="sm-pwd-input" autocomplete="new-password"
                 placeholder="{$lang.pwd_new_label}">
          <div class="sm-ig-btns">
            <button type="button" onclick="smTogglePwd()"><i class="fa fa-eye" id="sm-eye-icon"></i></button>
            <button type="button" onclick="smGeneratePwd()" title="{$lang.btn_generate_pwd}"><i class="fa fa-random"></i></button>
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
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-setpwd-modal')">{$lang.btn_cancel}</button>
      <button type="button" class="btn btn-warning btn-sm" id="sm-pwd-apply" onclick="smApplyPwd()" disabled>
        <i class="fa fa-check"></i> {$lang.pwd_btn_apply}
      </button>
    </div>
  </div>
</div>

{* ── Ajouter alias ─────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-alias-modal" onclick="smBg(event,'sm-alias-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead">
      <h4><i class="fa fa-at" style="color:#3949ab;"></i> {$lang.eu_alias_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-alias-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <label class="sm-mlabel">{$lang.eu_alias_ph|default:'{$lang.eu_alias_ph}'}</label>
      <div class="sm-minput-row">
        <input type="text" id="sm-alias-input" placeholder="{$lang.eu_alias_ph_ex}"
               pattern="[a-zA-Z0-9._-]+" autocomplete="off"
               data-errchars="{$lang.eu_alias_err_chars}"
               data-errdup="{$lang.eu_alias_err_dup}">
        <span class="sm-minput-suffix">@{$domain|escape}</span>
      </div>
      <div class="sm-merr" id="sm-alias-err"></div>
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-alias-modal')">{$lang.btn_cancel}</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="smAddAlias()">
        <i class="fa fa-plus"></i> {$lang.eu_btn_add}
      </button>
    </div>
  </div>
</div>

{* ── Ajouter redirection ───────────────────────────────────────── *}
<div class="sm-overlay" id="sm-fwd-modal" onclick="smBg(event,'sm-fwd-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead">
      <h4><i class="fa fa-share" style="color:#2e7d32;"></i> {$lang.eu_fwd_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-fwd-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <label class="sm-mlabel">{$lang.eu_fwd_ph|default:'{$lang.eu_fwd_ph}'}</label>
      <input type="text" class="sm-minput-full" id="sm-fwd-input"
             placeholder="{$lang.eu_fwd_ph_ex}"
             autocomplete="off"
             data-errinvalid="{$lang.eu_fwd_err_invalid}"
             data-errdup="{$lang.eu_fwd_err_dup}">
      <div class="sm-merr" id="sm-fwd-err"></div>
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-fwd-modal')">{$lang.btn_cancel}</button>
      <button type="button" class="btn btn-success btn-sm" onclick="smAddFwd()">
        <i class="fa fa-plus"></i> {$lang.eu_btn_add}
      </button>
    </div>
  </div>
</div>

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
        <i class="fa fa-tag"></i> +{$easPrice|number_format:2} $/mois
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
        <i class="fa fa-tag"></i> +{$mapiPrice|number_format:2} $/mois
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
var SM_DOMAIN       = '{$domain|escape:"javascript"}';
var SM_DOMAIN_BASE  = '{$domainBase|escape:"javascript"}';
var SM_PWD_MIN      = {$pwdMinLength|default:8};
{literal}
var SM_REQ_UPPER = !!document.getElementById('crit-upper');
var SM_REQ_NUM   = !!document.getElementById('crit-num');
var SM_REQ_SPEC  = !!document.getElementById('crit-spec');

var smAliases = [];
var smFwdList = [];
var smPwdSet  = false;

// ── Listeners ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  var pi = document.getElementById('sm-pwd-input');
  var pc = document.getElementById('sm-pwd-confirm');
  if (pi) pi.addEventListener('input', smCheckPwd);
  if (pc) pc.addEventListener('input', smCheckPwd);

  var ai = document.getElementById('sm-alias-input');
  var fi = document.getElementById('sm-fwd-input');
  if (ai) ai.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();smAddAlias();} });
  if (fi) fi.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();smAddFwd();} });
});

// ── Modales ───────────────────────────────────────────────────────
function smOpen(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
  var inp = document.querySelector('#'+id+' input[type="text"], #'+id+' input[type="password"]');
  if (inp) setTimeout(function(){ inp.focus(); }, 80);
}
function smClose(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
function smBg(e, id) {
  if (e.target === document.getElementById(id)) smClose(id);
}

// ── Validation création ───────────────────────────────────────────
function smCreateValidate() {
  var username = (document.getElementById('field-username') || {}).value || '';
  if (!username.trim()) { alert('Veuillez entrer un nom d\'utilisateur.'); return false; }
  if (!smPwdSet) { smOpen('sm-setpwd-modal'); return false; }
  return true;
}

// ── Mot de passe (popup, remplit le champ caché) ──────────────────
function smTogglePwd() {
  var f = document.getElementById('sm-pwd-input');
  var e = document.getElementById('sm-eye-icon');
  f.type = f.type === 'password' ? 'text' : 'password';
  e.className = f.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

function smGeneratePwd() {
  var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*-_=+';
  var pwd = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
  pwd += '0123456789'[Math.floor(Math.random() * 10)];
  pwd += '!@#$%^&*-_=+'[Math.floor(Math.random() * 13)];
  for (var i = pwd.length; i < Math.max(SM_PWD_MIN, 14); i++)
    pwd += chars[Math.floor(Math.random() * chars.length)];
  pwd = pwd.split('').sort(function() { return Math.random() - .5; }).join('');
  var f = document.getElementById('sm-pwd-input');
  f.value = pwd; f.type = 'text';
  document.getElementById('sm-eye-icon').className = 'fa fa-eye-slash';
  // Remplir aussi le champ de confirmation pour que okMatch = true
  // et ainsi activer le bouton "Appliquer"
  var fc = document.getElementById('sm-pwd-confirm');
  if (fc) { fc.value = pwd; }
  smCheckPwd();
}

function smCrit(id, ok) {
  var el = document.getElementById(id);
  if (!el) return;
  el.className = ok ? 'ok' : '';
  el.querySelector('i').className = ok ? 'fa fa-check' : 'fa fa-times';
}

function smCheckPwd() {
  var username = (document.getElementById('field-username') || {}).value || '';
  username = username.trim().toLowerCase();
  var pwd      = (document.getElementById('sm-pwd-input') || {}).value || '';
  var conf     = (document.getElementById('sm-pwd-confirm') || {}).value || '';
  var pwdLower = pwd.toLowerCase();

  var okLen      = pwd.length >= SM_PWD_MIN;
  var okUpper    = !SM_REQ_UPPER || /[A-Z]/.test(pwd);
  var okNum      = !SM_REQ_NUM   || /[0-9]/.test(pwd);
  var okSpec     = !SM_REQ_SPEC  || /[!@#$%^&*\-_=+]/.test(pwd);
  var okNoUser   = username.length === 0 || pwdLower.indexOf(username) === -1;
  var domBase    = SM_DOMAIN_BASE.length >= 4 ? SM_DOMAIN_BASE.toLowerCase() : '';
  var okNoDomain = pwdLower.indexOf(SM_DOMAIN.toLowerCase()) === -1
                && (domBase === '' || pwdLower.indexOf(domBase) === -1);
  var okMatch    = pwd.length > 0 && pwd === conf;

  smCrit('crit-len', okLen); smCrit('crit-upper', okUpper);
  smCrit('crit-num', okNum); smCrit('crit-spec', okSpec);
  smCrit('crit-no-user', okNoUser); smCrit('crit-no-domain', okNoDomain);
  smCrit('crit-match', okMatch);

  var allOk = okLen && okUpper && okNum && okSpec && okNoUser && okNoDomain && okMatch;
  var score  = [okLen,okUpper,okNum,okSpec,okNoUser,okNoDomain,pwd.length>=16].filter(Boolean).length;
  var pct    = Math.min(100, Math.round(score / 7 * 100));
  var bar    = document.getElementById('sm-pwd-bar');
  if (bar) { bar.style.width = pct+'%'; bar.style.background = pct<45?'#e74c3c':pct<80?'#f39c12':'#27ae60'; }
  var btn = document.getElementById('sm-pwd-apply');
  if (btn) btn.disabled = !allOk;
  return allOk;
}

function smApplyPwd() {
  var pwd = document.getElementById('sm-pwd-input').value;
  document.getElementById('hid-password').value = pwd;
  smPwdSet = true;

  // Mettre à jour l'indicateur visuel
  var status = document.getElementById('pwd-status-display');
  status.className = 'sm-pwd-status set';
  status.innerHTML = '<i class="fa fa-check-circle"></i> ' + SM_LANG_PWD_DEFINED;

  // Rendre le bouton Créer actif
  var btn = document.getElementById('btn-create');
  if (btn) btn.className = btn.className.replace('sm-btn-create', 'sm-btn-create ready').replace('ready ready','ready');

  smClose('sm-setpwd-modal');
  // Vider les champs pour sécurité
  document.getElementById('sm-pwd-input').value = '';
  document.getElementById('sm-pwd-confirm').value = '';
  smCheckPwd();
}

// ── Alias ─────────────────────────────────────────────────────────
function smAddAlias() {
  var input = document.getElementById('sm-alias-input');
  var errEl = document.getElementById('sm-alias-err');
  var name  = input.value.trim().toLowerCase().replace(/\s+/g,'');
  errEl.style.display = 'none';
  if (!name) return;
  if (!/^[a-z0-9._\-]+$/.test(name)) {
    errEl.textContent = input.dataset.errchars || 'Caractères non valides';
    errEl.style.display = 'block'; return;
  }
  if (smAliases.indexOf(name) !== -1) {
    errEl.textContent = input.dataset.errdup || 'Alias déjà présent';
    errEl.style.display = 'block'; return;
  }
  smAliases.push(name);
  smRenderAliasPills();
  input.value = '';
  smClose('sm-alias-modal');
}

function smRemoveAlias(name) {
  smAliases = smAliases.filter(function(a){ return a !== name; });
  smRenderAliasPills();
}

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
          +'<button type="button" class="sm-pill-x" onclick="smRemoveAlias(\''+escAttr(a)+'\')">&times;</button></span>';
  }).join('');
  h.innerHTML = smAliases.map(function(a){
    return '<input type="hidden" name="aliases[]" value="'+escAttr(a)+'">';
  }).join('');
}

// ── Redirection ───────────────────────────────────────────────────
function smAddFwd() {
  var input = document.getElementById('sm-fwd-input');
  var errEl = document.getElementById('sm-fwd-err');
  var addr  = input.value.trim().toLowerCase();
  errEl.style.display = 'none';
  if (!addr) return;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addr)) {
    errEl.textContent = input.dataset.errinvalid || 'Adresse invalide';
    errEl.style.display = 'block'; return;
  }
  if (smFwdList.indexOf(addr) !== -1) {
    errEl.textContent = input.dataset.errdup || 'Adresse déjà présente';
    errEl.style.display = 'block'; return;
  }
  smFwdList.push(addr);
  smRenderFwdPills();
  input.value = '';
  smClose('sm-fwd-modal');
}

function smRemoveFwd(addr) {
  smFwdList = smFwdList.filter(function(a){ return a !== addr; });
  smRenderFwdPills();
}

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
          +'<button type="button" class="sm-pill-x" onclick="smRemoveFwd(\''+escAttr(a)+'\')">&times;</button></span>';
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
    var html = '<i class="fa fa-tag"></i> ' + SM_LANG_PRICE_BUNDLE + '\u00a0: <span class="sm-price-badge bundle">+'+bp.toFixed(2)+' $/mois</span>';
    if (sv > 0.005) html += ' <span class="sm-price-badge save">' + SM_LANG_PRICE_SAVING + ' '+sv.toFixed(2)+' $</span>';
    bundle.innerHTML = html;
  } else {
    bundle.style.display = 'none'; single.style.display = 'block';
    var price = easOn ? SM_EAS_PRICE : SM_MAPI_PRICE;
    var label = easOn ? 'ActiveSync' : 'MAPI / Exchange';
    single.innerHTML = price > 0
      ? '<i class="fa fa-tag"></i> '+label+'\u00a0: <span class="sm-price-badge">+'+price.toFixed(2)+' $/mois</span>'
      : '<i class="fa fa-tag"></i> '+label+'\u00a0: <span class="sm-price-badge">' + SM_LANG_PRICE_INCL + '</span>';
  }
}

// ── Utilitaires ───────────────────────────────────────────────────
function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s){ return String(s).replace(/'/g,"\\'").replace(/"/g,'&quot;'); }
{/literal}
</script>
