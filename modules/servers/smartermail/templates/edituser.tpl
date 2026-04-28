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
</script>
<style>
{literal}
/* ── Navigation ─────────────────────────────────────────────────────── */
.sm-back{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#777;text-decoration:none;margin-bottom:14px}
.sm-back:hover{color:#3949ab}

/* ── Header : email + stockage ──────────────────────────────────────── */
.sm-header{background:linear-gradient(135deg,#2c3e50 0%,#3d5166 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-email{font-size:16px;font-weight:700;margin-bottom:8px}
.sm-header-email i{margin-right:8px;opacity:.8}
.sm-header-storage{font-size:12px;color:rgba(255,255,255,.75);margin-bottom:6px}
.sm-progress{height:8px;background:rgba(255,255,255,.2);border-radius:4px;overflow:hidden}
.sm-progress-bar{height:100%;border-radius:4px;transition:width .4s}
.sm-progress-bar.low{background:#27ae60}.sm-progress-bar.mid{background:#f39c12}.sm-progress-bar.high{background:#e74c3c}

/* ── Cards ──────────────────────────────────────────────────────────── */
.sm-card{background:#fff;border:1px solid #e0e0e0;border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:#f7f8fa;border-bottom:1px solid #e0e0e0;padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}

/* ── Grille Alias | Redirection ─────────────────────────────────────── */
.sm-2col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
@media(max-width:600px){.sm-2col{grid-template-columns:1fr}}
.sm-2col .sm-card{margin-bottom:0}

/* ── Pills ──────────────────────────────────────────────────────────── */
.sm-pills-wrap{display:flex;flex-wrap:wrap;gap:5px;min-height:28px;margin-bottom:10px}
.sm-pill{display:inline-flex;align-items:center;gap:5px;background:#e8eaf6;color:#3949ab;border-radius:20px;padding:3px 10px 3px 12px;font-size:12px;font-weight:500}
.sm-pill.fwd{background:#e8f5e9;color:#2e7d32}
.sm-pill-x{background:none;border:none;cursor:pointer;padding:0;line-height:1;font-size:14px;color:inherit;opacity:.6;display:flex;align-items:center}
.sm-pill-x:hover{opacity:1}
.sm-pills-empty{font-size:12px;color:#bbb;font-style:italic;padding:2px 0}

/* ── Bouton [+ Ajouter] ─────────────────────────────────────────────── */
.sm-add-trigger{display:flex;justify-content:flex-end;margin-top:6px}
.sm-btn-add{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px dashed #3949ab;color:#3949ab;border-radius:4px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.sm-btn-add:hover{background:#3949ab;color:#fff;border-style:solid}

/* ── Options (2 colonnes internes) ─────────────────────────────────── */
.sm-opts-inner{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
@media(max-width:600px){.sm-opts-inner{grid-template-columns:1fr}}
.sm-form-label{display:block;font-size:12px;color:#666;margin-bottom:4px;font-weight:600}
.sm-form-hint{color:#aaa;font-weight:400;}
input.sm-number{width:100%;max-width:160px;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box}
input.sm-number:focus{border-color:#3949ab;outline:none}

/* ── Lignes de cases à cocher + bouton [i] ──────────────────────────── */
.sm-chk-row{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px}
.sm-chk-row label{margin:0;cursor:pointer;font-weight:500;color:#333}
.sm-info-btn{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:#e8eaf6;color:#3949ab;border-radius:50%;font-size:10px;font-weight:700;cursor:pointer;border:none;line-height:1;flex-shrink:0;transition:background .15s}
.sm-info-btn:hover{background:#3949ab;color:#fff}

/* ── Boîte de prix dynamique ────────────────────────────────────────── */
.sm-price-box{margin-top:10px;padding:8px 12px;border-radius:4px;font-size:12px;background:#f7f8fa;border:1px solid #e0e0e0;display:none}
.sm-proto-billing-notice{margin-top:8px;font-size:11px;color:#757575;display:flex;align-items:flex-start;gap:5px;line-height:1.5}
.sm-proto-billing-notice .fa{color:#f39c12;flex-shrink:0;margin-top:2px}
.sm-price-box.visible{display:block}
.sm-price-badge{display:inline-block;background:#e8eaf6;color:#3949ab;border-radius:3px;padding:1px 7px;font-size:11px;margin-left:4px;font-weight:600}
.sm-price-badge.bundle{background:#e8f5e9;color:#2e7d32}
.sm-price-badge.save{background:#fff3cd;color:#856404;font-size:10px}
.sm-price-bundle{color:#27ae60;font-weight:600}

/* ── Options de redirection ─────────────────────────────────────────── */
.sm-fwd-opts{display:flex;gap:14px;margin-top:8px;font-size:12px;color:#666}
.sm-fwd-opts label{display:flex;align-items:center;gap:5px;cursor:pointer}

/* ── Barre d'actions ────────────────────────────────────────────────── */
/* Barre d'actions : Supprimer à gauche seul, Mot de passe + Sauvegarder à droite */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:#f7f8fa;border:1px solid #e0e0e0;border-radius:6px;justify-content:space-between}
/* Bouton "Annuler" (lien <a> stylé en bouton) — renvoie au tableau de
   bord sans soumettre le formulaire d'édition. text-decoration:none
   neutralise le soulignement par défaut des liens. */
.sm-btn-cancel{background:#fff;color:#555;border:1px solid #ddd;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.sm-btn-cancel:hover{background:#f5f5f5;color:#333;text-decoration:none}
.sm-btn-pwd{background:#f39c12;color:#fff;border:none;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.sm-btn-pwd:hover{background:#e67e22}
.sm-btn-save{color:#fff;border:none;padding:8px 18px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;background:#aaa;transition:background .2s,box-shadow .2s,transform .15s}
.sm-btn-save.dirty{background:#27ae60;box-shadow:0 0 0 3px rgba(39,174,96,.35);transform:scale(1.04)}
.sm-btn-save.dirty:hover{background:#219a52}
.sm-btn-del{background:#fff;color:#e74c3c;border:1px solid #e74c3c;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.sm-btn-del:hover{background:#fce4e4}
@media(max-width:600px){.sm-btn-del{margin-left:0}}

/* ── Overlay + boîte modale générique ──────────────────────────────── */
.sm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center}
.sm-overlay.open{display:flex}
.sm-mbox{background:#fff;border-radius:8px;width:100%;max-width:420px;margin:16px;box-shadow:0 8px 32px rgba(0,0,0,.2);animation:smFadeIn .18s ease}
@keyframes smFadeIn{from{transform:translateY(-12px);opacity:0}to{transform:translateY(0);opacity:1}}
.sm-mhead{padding:13px 16px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
.sm-mhead h4{margin:0;font-size:14px;font-weight:700;color:#333;display:flex;align-items:center;gap:8px}
.sm-mclose{background:none;border:none;font-size:20px;cursor:pointer;color:#999;line-height:1;padding:0}
.sm-mclose:hover{color:#333}
.sm-mbody{padding:18px}
.sm-mfoot{padding:12px 16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px}

/* Variantes de header de modale */
.sm-mhead.info{background:#e8eaf6;border-bottom:1px solid #c5cae9}
.sm-mhead.info h4{color:#3949ab}
.sm-mhead.pwd{background:#f39c12;border-bottom:none}
.sm-mhead.pwd h4,.sm-mhead.pwd .sm-mclose{color:#fff}
.sm-mhead.del{background:#e74c3c;border-bottom:none}
.sm-mhead.del h4,.sm-mhead.del .sm-mclose{color:#fff}

/* Éléments internes des modales */
.sm-mlabel{display:block;font-size:12px;color:#666;font-weight:600;margin-bottom:4px}
.sm-minput-row{display:flex;align-items:center;gap:6px}
.sm-minput-row input[type="text"]{flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px}
.sm-minput-row input[type="text"]:focus{border-color:#3949ab;outline:none}
.sm-minput-suffix{font-size:12px;color:#888;white-space:nowrap}
.sm-minput-full{width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box}
.sm-minput-full::placeholder,.sm-minput-row input[type="text"]::placeholder,.sm-ig input::placeholder{color:#bbb}
.sm-minput-full:focus{border-color:#3949ab;outline:none}
.sm-merr{color:#e74c3c;font-size:12px;margin-top:6px;display:none}
.sm-mdesc{font-size:13px;color:#555;line-height:1.6;margin:0 0 8px}

/* Groupe input + boutons (mot de passe) */
.sm-ig{display:flex}
.sm-ig input{flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:4px 0 0 4px;border-right:none;font-size:13px}
.sm-ig input:focus{border-color:#3949ab;outline:none}
.sm-ig-btns{display:flex}
.sm-ig-btns button{padding:7px 10px;border:1px solid #ddd;background:#f7f8fa;cursor:pointer;font-size:13px;color:#555}
.sm-ig-btns button:last-child{border-radius:0 4px 4px 0}
.sm-ig-btns button:hover{background:#eee}
.sm-pwd-strength{height:4px;background:#eee;border-radius:2px;margin:8px 0 4px}
.sm-pwd-bar{height:100%;border-radius:2px;transition:width .3s,background .3s}
.sm-pwd-crit{list-style:none;padding:0;margin:10px 0 0;font-size:12px}
.sm-pwd-crit li{padding:3px 0;display:flex;align-items:center;gap:6px;color:#aaa}
.sm-pwd-crit li.ok{color:#27ae60}
.sm-del-warn{background:#fce4e4;border:1px solid #f5c6cb;border-radius:4px;padding:8px 12px;font-size:12px;color:#721c24;margin-top:12px}
{/literal}
</style>

<a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-back">
  <i class="fa fa-arrow-left"></i> {$lang.back_dashboard}
</a>

{* ── En-tête ─────────────────────────────────────────────────────────── *}
<div class="sm-header">
  <div class="sm-header-email"><i class="fa fa-envelope-o"></i>{$email|escape}</div>
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
      <div class="sm-add-trigger">
        <button type="button" class="sm-btn-add" onclick="smOpen('sm-alias-modal')">
          <i class="fa fa-plus"></i> {$lang.eu_btn_add}
        </button>
      </div>
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
      <div class="sm-add-trigger">
        <button type="button" class="sm-btn-add" onclick="smOpen('sm-fwd-modal')">
          <i class="fa fa-plus"></i> {$lang.eu_btn_add}
        </button>
      </div>
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
  <input type="hidden" name="selectuser"   value="{$username|escape}">
  <input type="hidden" name="mailboxsize_mb" id="hid-size">
  {if $canEAS}<input type="hidden" name="enable_eas"  id="hid-eas">{/if}
  {if $canMAPI}<input type="hidden" name="enable_mapi" id="hid-mapi">{/if}
  {* ── État précédent EAS/MAPI — nécessaire pour détecter les transitions ON→OFF et OFF→ON ── *}
  {* saveuser() compare was_eas/was_mapi avec enable_eas/enable_mapi pour enregistrer          *}
  {* précisément les activations/désactivations dans mod_sm_proto_usage.                       *}
  {if $canEAS}<input type="hidden" name="was_eas"  value="{if $easEnabled}1{else}0{/if}">{/if}
  {if $canMAPI}<input type="hidden" name="was_mapi" value="{if $mapiEnabled}1{else}0{/if}">{/if}
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

{* ── Ajouter alias ───────────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-alias-modal" onclick="smBg(event,'sm-alias-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead">
      <h4><i class="fa fa-at" style="color:#3949ab;"></i> {$lang.eu_alias_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-alias-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <label class="sm-mlabel">{$lang.eu_alias_ph|default:'Nom de l\'alias'}</label>
      <div class="sm-minput-row">
        <input type="text" id="sm-alias-input"
               placeholder="{$lang.eu_alias_ph}"
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

{* ── Ajouter redirection ─────────────────────────────────────────────── *}
<div class="sm-overlay" id="sm-fwd-modal" onclick="smBg(event,'sm-fwd-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead">
      <h4><i class="fa fa-share" style="color:#2e7d32;"></i> {$lang.eu_fwd_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-fwd-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <label class="sm-mlabel">{$lang.eu_fwd_ph}</label>
      <input type="text" class="sm-minput-full" id="sm-fwd-input"
             placeholder="{$lang.eu_fwd_ph}"
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
  smCheckPwd();
}

function smCrit(id, ok) {
  var el = document.getElementById(id);
  if (!el) return;
  el.className = ok ? 'ok' : '';
  el.querySelector('i').className = ok ? 'fa fa-check' : 'fa fa-times';
}

function smCheckPwd() {
  var pwd      = document.getElementById('sm-pwd-input').value;
  var conf     = document.getElementById('sm-pwd-confirm').value;
  var pwdLower = pwd.toLowerCase();

  var okLen      = pwd.length >= SM_PWD_MIN;
  var okUpper    = !SM_REQ_UPPER || /[A-Z]/.test(pwd);
  var okNum      = !SM_REQ_NUM   || /[0-9]/.test(pwd);
  var okSpec     = !SM_REQ_SPEC  || /[!@#$%^&*\-_=+]/.test(pwd);
  var okNoUser   = SM_USERNAME.length === 0 || pwdLower.indexOf(SM_USERNAME.toLowerCase()) === -1;
  var domBase    = SM_DOMAIN_BASE.length >= 4 ? SM_DOMAIN_BASE.toLowerCase() : '';
  var okNoDomain = pwdLower.indexOf(SM_DOMAIN.toLowerCase()) === -1
                && (domBase === '' || pwdLower.indexOf(domBase) === -1);
  var okMatch    = pwd.length > 0 && pwd === conf;

  smCrit('crit-len',      okLen);
  smCrit('crit-upper',    okUpper);
  smCrit('crit-num',      okNum);
  smCrit('crit-spec',     okSpec);
  smCrit('crit-no-user',  okNoUser);
  smCrit('crit-no-domain',okNoDomain);
  smCrit('crit-match',    okMatch);

  var allOk = okLen && okUpper && okNum && okSpec && okNoUser && okNoDomain && okMatch;
  var score  = [okLen, okUpper, okNum, okSpec, okNoUser, okNoDomain, pwd.length >= 16].filter(Boolean).length;
  var pct    = Math.min(100, Math.round(score / 7 * 100));
  var bar    = document.getElementById('sm-pwd-bar');
  bar.style.width      = pct + '%';
  bar.style.background = pct < 45 ? '#e74c3c' : pct < 80 ? '#f39c12' : '#27ae60';

  var btn = document.getElementById('sm-pwd-submit');
  if (btn) btn.disabled = !allOk;
}
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

// ── Helpers modales ───────────────────────────────────────────────────
function smOpen(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
  var inp = document.querySelector('#'+id+' input[type="text"], #'+id+' input[type="password"], #'+id+' input[type="number"]');
  if (inp) setTimeout(function(){ inp.focus(); }, 80);
}
function smClose(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
function smBg(e, id) {
  if (e.target === document.getElementById(id)) smClose(id);
}

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
  document.getElementById('sm-save-form').submit();
}

// ── Alias ─────────────────────────────────────────────────────────────
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
  smMarkDirty();
  smClose('sm-alias-modal');
}

function smRemoveAlias(name) {
  smAliases = smAliases.filter(function(a){ return a !== name; });
  smRenderAliasPills();
  smMarkDirty();
}

function smRenderAliasPills() {
  var c = document.getElementById('sm-alias-pills');
  if (!smAliases.length) { c.innerHTML = '<span class="sm-pills-empty">' + SM_LANG_ALIAS_EMPTY + '</span>'; return; }
  c.innerHTML = smAliases.map(function(a){
    return '<span class="sm-pill" data-name="'+escAttr(a)+'">'+escHtml(a)+'@'+escHtml(SM_DOMAIN)
          +'<button type="button" class="sm-pill-x" onclick="smRemoveAlias(\''+escAttr(a)+'\')" title="{$lang.btn_remove}">&times;</button></span>';
  }).join('');
}

// ── Redirection ───────────────────────────────────────────────────────
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
  smMarkDirty();
  smClose('sm-fwd-modal');
}

function smRemoveFwd(addr) {
  smFwdList = smFwdList.filter(function(a){ return a !== addr; });
  smRenderFwdPills();
  smMarkDirty();
}

function smRenderFwdPills() {
  var c = document.getElementById('sm-fwd-pills');
  if (!smFwdList.length) { c.innerHTML = '<span class="sm-pills-empty">' + SM_LANG_FWD_EMPTY + '</span>'; return; }
  c.innerHTML = smFwdList.map(function(a){
    return '<span class="sm-pill fwd" data-addr="'+escAttr(a)+'">'
          +'<i class="fa fa-share" style="font-size:10px;"></i> '+escHtml(a)
          +'<button type="button" class="sm-pill-x" onclick="smRemoveFwd(\''+escAttr(a)+'\')" title="{$lang.btn_remove}">&times;</button></span>';
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

// ── Utilitaires ───────────────────────────────────────────────────────
function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s){ return String(s).replace(/'/g,"\\'").replace(/"/g,'&quot;'); }

// ── Mot de passe — voir pwdmodal.tpl (smCheckPwd, smTogglePwd, smGeneratePwd, smCrit)
{/literal}
</script>
