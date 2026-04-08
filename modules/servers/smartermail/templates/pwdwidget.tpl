{* ── Widget mot de passe réutilisable ──────────────────────────────────────── *}
{* Utilisé dans pwdmodal.tpl (edituser) et adduser.tpl (inline).               *}
{* Variables requises : $pwdMinLength, $pwdRequireUpper, $pwdRequireNumber,    *}
{*                      $pwdRequireSpecial, $lang                               *}
{* IDs des champs : sm-pwd-input / sm-pwd-confirm / sm-pwd-bar / sm-pwd-submit *}

<div style="margin-bottom:14px;">
  <label class="sm-mlabel">{$lang.pwd_new_label}</label>
  <div class="sm-ig">
    <input type="password" id="sm-pwd-input" name="password"
           placeholder="{$lang.pwd_new_label}" autocomplete="new-password">
    <div class="sm-ig-btns">
      <button type="button" onclick="smTogglePwd()" title="{$lang.btn_show_pwd|default:'Afficher'}">
        <i class="fa fa-eye" id="sm-eye-icon"></i>
      </button>
      <button type="button" onclick="smGeneratePwd()" title="{$lang.btn_generate|default:'Générer'}">
        <i class="fa fa-refresh"></i>
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
  <li id="crit-no-user"><i class="fa fa-times"></i> {$lang.pwd_crit_no_user|default:'Ne doit pas contenir le nom utilisateur'}</li>
  <li id="crit-no-domain"><i class="fa fa-times"></i> {$lang.pwd_crit_no_domain|default:'Ne doit pas contenir le nom de domaine'}</li>
  <li id="crit-match"><i class="fa fa-times"></i> {$lang.pwd_crit_match}</li>
</ul>
