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
        {include file="pwdwidget.tpl"}
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
  // Remplir aussi le champ de confirmation pour que okMatch = true
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
