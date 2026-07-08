/* ============================================================================
 *  _sm_common.js — Fonctions JavaScript PARTAGÉES du module SmarterMail
 * ============================================================================
 *  Injecté dans le <head> par le hook ClientAreaHeadOutput (voir hooks.php),
 *  AVANT les <script> inline des templates. Centralise les fonctions autrefois
 *  copiées à l'identique dans chaque template (kit modale, échappement).
 *
 *  MIGRATION : un template peut encore redéfinir localement une de ces fonctions
 *  dans son <script> inline (du <body>) — sa définition, plus tardive, l'emporte.
 *  Le retrait progressif des copies locales est donc sans risque de conflit.
 * ============================================================================ */

/* ── Kit modale ─────────────────────────────────────────────────────────────
   Accessibilité : la boîte reçoit role="dialog" + aria-modal + aria-labelledby
   (posés une seule fois) ; le focus est déplacé dans la modale à l'ouverture,
   PIÉGÉ tant qu'elle est ouverte (Tab cyclique), et RESTAURÉ sur l'élément
   déclencheur à la fermeture. */

/* Liste des éléments focusables VISIBLES d'un conteneur. */
function smFocusable(container) {
  var sel = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),' +
            'select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  return Array.prototype.filter.call(container.querySelectorAll(sel), function (el) {
    return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
  });
}

function smOpen(id) {
  var m = document.getElementById(id);
  if (!m) return;
  m._smReturn = document.activeElement;          // focus à restaurer à la fermeture
  m.classList.add('open');
  document.body.style.overflow = 'hidden';
  // ARIA (posé une seule fois) sur la boîte de dialogue.
  var box = m.querySelector('.sm-mbox') || m;
  if (!box.getAttribute('role')) {
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    var h = box.querySelector('h4');
    if (h) {
      if (!h.id) h.id = id + '-title';
      box.setAttribute('aria-labelledby', h.id);
    }
  }
  // Focus : premier champ ÉDITABLE, sinon premier élément focusable (bouton…).
  var inp = m.querySelector(
    'input[type="text"]:not([readonly]),input[type="password"],input[type="number"],input[type="email"],textarea:not([readonly])'
  ) || smFocusable(box)[0];
  if (inp) setTimeout(function () { try { inp.focus(); } catch (e) {} }, 80);
}

function smClose(id) {
  var m = document.getElementById(id);
  if (!m) return;
  m.classList.remove('open');
  document.body.style.overflow = '';
  var r = m._smReturn;                            // rendre le focus au déclencheur
  if (r && typeof r.focus === 'function') { try { r.focus(); } catch (e) {} }
}

function smBg(e, id) {
  if (e.target === document.getElementById(id)) smClose(id);
}

/* Touche Échap (ferme les modales ouvertes) + piège de focus (Tab cyclique). */
document.addEventListener('keydown', function (e) {
  var open = document.querySelector('.sm-overlay.open');
  if (!open) return;
  if (e.key === 'Escape' || e.keyCode === 27) {
    document.querySelectorAll('.sm-overlay.open').forEach(function (el) { smClose(el.id); });
    return;
  }
  if (e.key === 'Tab' || e.keyCode === 9) {
    var box = open.querySelector('.sm-mbox') || open;
    var f = smFocusable(box);
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }
});

/* ── Échappement (rendu de pills/tags via innerHTML) ────────────────────── */
function escHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function escAttr(s) {
  return String(s).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

/* ── Widget mot de passe (pages edituser + adduser uniquement) ───────────────
   Dépend de globals Smarty-interpolés déclarés dans le préambule de CHAQUE
   template concerné : SM_PWD_MIN, SM_REQ_UPPER, SM_REQ_NUM, SM_REQ_SPEC,
   SM_DOMAIN, SM_DOMAIN_BASE. Le username provient soit du champ saisi
   #field-username (adduser), soit — à défaut d'un tel champ — du global
   SM_USERNAME (edituser : la boîte est fixe). Sur les autres pages ces fonctions
   ne sont jamais appelées (aucun élément #sm-pwd-* présent). */
function smTogglePwd() {
  var f = document.getElementById('sm-pwd-input');
  var e = document.getElementById('sm-eye-icon');
  if (!f || !e) return;
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
  pwd = pwd.split('').sort(function () { return Math.random() - .5; }).join('');
  var f = document.getElementById('sm-pwd-input');
  if (!f) return;
  f.value = pwd; f.type = 'text';
  var eye = document.getElementById('sm-eye-icon');
  if (eye) eye.className = 'fa fa-eye-slash';
  // Remplir aussi le champ de confirmation, sinon okMatch reste faux dans
  // smCheckPwd() et le bouton de soumission demeure bloqué après « Générer ».
  var fc = document.getElementById('sm-pwd-confirm');
  if (fc) fc.value = pwd;
  smCheckPwd();
}

function smCrit(id, ok) {
  var el = document.getElementById(id);
  if (!el) return;
  el.className = ok ? 'ok' : '';
  var ic = el.querySelector('i');
  if (ic) ic.className = ok ? 'fa fa-check' : 'fa fa-times';
}

function smCheckPwd() {
  var unameEl  = document.getElementById('field-username');
  var username = (unameEl ? unameEl.value
                          : (typeof SM_USERNAME !== 'undefined' ? SM_USERNAME : '')).trim().toLowerCase();
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

  smCrit('crit-len',       okLen);
  smCrit('crit-upper',     okUpper);
  smCrit('crit-num',       okNum);
  smCrit('crit-spec',      okSpec);
  smCrit('crit-no-user',   okNoUser);
  smCrit('crit-no-domain', okNoDomain);
  smCrit('crit-match',     okMatch);

  var allOk = okLen && okUpper && okNum && okSpec && okNoUser && okNoDomain && okMatch;
  var score = [okLen, okUpper, okNum, okSpec, okNoUser, okNoDomain, pwd.length >= 16].filter(Boolean).length;
  var pct   = Math.min(100, Math.round(score / 7 * 100));
  var bar   = document.getElementById('sm-pwd-bar');
  if (bar) { bar.style.width = pct + '%'; bar.style.background = pct < 45 ? '#e74c3c' : pct < 80 ? '#f39c12' : '#27ae60'; }
  // edituser utilise #sm-pwd-submit, adduser #sm-pwd-apply — on cible les deux.
  var btn = document.getElementById('sm-pwd-submit') || document.getElementById('sm-pwd-apply');
  if (btn) btn.disabled = !allOk;
  return allOk;
}

/* ── Alias & redirections de boîte — ajout/retrait (edituser + adduser) ──────
   État dans les globals smAliases / smFwdList (préambule de chaque template).
   Le RENDU des pills reste LOCAL à chaque template : edituser et adduser ont des
   modèles de soumission différents (edituser sérialise via smSave()→#hid-aliases ;
   adduser via #sm-alias-hidden rempli par son render). Ces fonctions n'appellent
   que le rendu local (smRenderAliasPills / smRenderFwdPills) et, si présente,
   smMarkDirty (edituser uniquement) → garde typeof. */
function smAddAlias() {
  var input = document.getElementById('sm-alias-input');
  var errEl = document.getElementById('sm-alias-err');
  var name  = input.value.trim().toLowerCase().replace(/\s+/g, '');
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
  if (typeof smMarkDirty === 'function') smMarkDirty();
  smClose('sm-alias-modal');
}

function smRemoveAlias(name) {
  smAliases = smAliases.filter(function (a) { return a !== name; });
  smRenderAliasPills();
  if (typeof smMarkDirty === 'function') smMarkDirty();
}

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
  if (typeof smMarkDirty === 'function') smMarkDirty();
  smClose('sm-fwd-modal');
}

function smRemoveFwd(addr) {
  smFwdList = smFwdList.filter(function (a) { return a !== addr; });
  smRenderFwdPills();
  if (typeof smMarkDirty === 'function') smMarkDirty();
}

/* ── Redirections de domaine — cibles (addredirect + editredirect) ───────────
   État dans le global smTargets. Les deux templates partagent le MÊME modèle de
   soumission (champs cachés #sm-targets-hidden → targets[]), d'où l'extraction
   complète (rendu inclus). smCheckReady n'existe qu'en création (addredirect)
   → garde typeof. */
function smAddTarget() {
  var input = document.getElementById('sm-target-input');
  var errEl = document.getElementById('sm-target-err');
  var addr  = input.value.trim().toLowerCase();
  errEl.style.display = 'none';
  if (!addr) return;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addr)) {
    errEl.textContent = input.dataset.errinvalid || 'Adresse courriel invalide.';
    errEl.style.display = 'block'; return;
  }
  if (smTargets.indexOf(addr) !== -1) {
    errEl.textContent = input.dataset.errdup || 'Cette adresse est déjà dans la liste.';
    errEl.style.display = 'block'; return;
  }
  smTargets.push(addr);
  smRenderTargets();
  input.value = '';
  smClose('sm-target-modal');
  if (typeof smCheckReady === 'function') smCheckReady();
}

function smRemoveTarget(addr) {
  smTargets = smTargets.filter(function (a) { return a !== addr; });
  smRenderTargets();
  if (typeof smCheckReady === 'function') smCheckReady();
}

function smRenderTargets() {
  var pillsEl = document.getElementById('sm-target-pills');
  if (!pillsEl) return;
  var hiddenEl = document.getElementById('sm-targets-hidden');
  var emptyEl  = document.getElementById('sm-targets-empty');

  // Retirer les pills existantes (conserve un éventuel span « vide »).
  pillsEl.querySelectorAll('.sm-pill').forEach(function (p) { p.remove(); });

  if (!smTargets.length) {
    if (emptyEl)  emptyEl.style.display = '';
    if (hiddenEl) hiddenEl.innerHTML = '';
    return;
  }
  if (emptyEl) emptyEl.style.display = 'none';

  smTargets.forEach(function (addr) {
    var pill = document.createElement('span');
    pill.className = 'sm-pill';
    pill.innerHTML = '<i class="fa fa-share" style="font-size:10px;"></i> '
      + escHtml(addr)
      + '<button type="button" class="sm-pill-x" onclick="smRemoveTarget(\'' + escAttr(addr) + '\')">&times;</button>';
    pillsEl.appendChild(pill);
  });

  if (hiddenEl) {
    hiddenEl.innerHTML = smTargets.map(function (addr) {
      return '<input type="hidden" name="targets[]" value="' + escAttr(addr) + '">';
    }).join('');
  }
}

/* ── Anti double-soumission ─────────────────────────────────────────────────
   Verrouille un <form> au premier envoi (drapeau + désactivation des boutons de
   soumission + spinner) et bloque tout envoi ultérieur. Les soumissions étant
   des POST PLEINE PAGE, le verrou tient jusqu'à la navigation (pas de
   déverrouillage nécessaire). `btn` permet de viser aussi un bouton déclencheur
   situé HORS du <form> (cas des envois JS via form.submit()). */
function smLockForm(form, btn) {
  if (form && form.dataset.smSubmitting === '1') return false;
  if (form) form.dataset.smSubmitting = '1';
  var targets = form
    ? Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'))
    : [];
  if (btn && targets.indexOf(btn) === -1) targets.push(btn);
  targets.forEach(function (b) {
    if (b.dataset.smBusy === '1') return;
    b.dataset.smBusy = '1';
    if (b.tagName === 'BUTTON') {
      var txt = (b.textContent || '').trim();
      b.innerHTML = '<i class="fa fa-spinner fa-spin"></i>' + (txt ? ' ' + txt : '');
    }
    b.disabled = true;
  });
  return true;
}

/* Verrou GLOBAL des soumissions NATIVES (clic sur un bouton type=submit ou touche
   Entrée). Ne cible QUE nos formulaires mutatifs (présence d'un champ caché
   customAction) et respecte la validation : si un onsubmit/onclick a bloqué
   l'envoi, e.defaultPrevented est vrai → pas de verrou. Les envois JS via
   form.submit() ne déclenchent PAS cet événement et appellent smLockForm()
   eux-mêmes (voir edituser smSave / editredirect smConfirmDelete). */
document.addEventListener('submit', function (e) {
  if (e.defaultPrevented) return;
  var form = e.target;
  if (!form || form.tagName !== 'FORM') return;
  if (!form.querySelector('input[name="customAction"]')) return;
  if (form.dataset.smSubmitting === '1') { e.preventDefault(); return; }
  smLockForm(form);
});

/* ── Flash de succès : nettoyage de l'URL ────────────────────────────────────
   La bannière verte est rendue côté serveur d'après ?smok=<action>. Une fois la
   page affichée, on retire ce paramètre de la barre d'adresse (sans recharger)
   pour qu'un rafraîchissement ne ré-affiche pas la bannière (comportement
   « flash » : visible une seule fois). */
(function () {
  if (!window.history || !history.replaceState || typeof URLSearchParams === 'undefined') return;
  var params = new URLSearchParams(location.search);
  if (!params.has('smok')) return;
  params.delete('smok');
  var qs = params.toString();
  history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
})();
