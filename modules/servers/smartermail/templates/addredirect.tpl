{* SmarterMail — Nouvelle redirection autonome *}
{*
 * Ce formulaire permet au client de créer un alias SmarterMail
 * sans boîte courriel associée — une simple redirection vers une
 * ou plusieurs adresses externes (ex: bob@gmail.com).
 *
 * DIFFÉRENCES avec adduser.tpl (intentionnelles) :
 *   - Pas de champ mot de passe     (une redirection n'a pas de boîte)
 *   - Pas de section alias           (on crée déjà un alias — pas besoin d'alias d'alias)
 *   - Pas de section Paramètres      (pas d'EAS, MAPI, limite d'espace)
 *   - Pas des options "Supprimer après transfert" ni "Conserver l'expéditeur"
 *     (ces options sont sur les boîtes courriel, pas sur les alias autonomes)
 *   - Au moins une adresse cible requise pour sauvegarder
 *
 * SÉCURITÉ :
 *   - Le nom d'alias est validé côté JS (pattern alphanumérique) ET côté PHP
 *   - Chaque adresse cible est validée côté JS ET par filter_var(PHP)
 *   - token CSRF : formulaire POST vers une action WHMCS authentifiée
 *   - Toutes les valeurs affichées passent par |escape dans ce template
 *}

<style>
{literal}
/* ── Spécifique aux redirections ──────────────────────────────────
   Le reste (kit modale de base, pills, bouton [+ Ajouter], navigation
   retour, étiquettes) est centralisé dans _sm_styles.css (thème indigo). ── */

/* En-tête — thème ardoise unifié (comme les pages de boîtes) */
.sm-header{background:linear-gradient(135deg,var(--sm-slate) 0%,var(--sm-slate-2) 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-title{font-size:16px;font-weight:700}
.sm-header-title i{margin-right:8px;opacity:.8}
.sm-header-sub{font-size:12px;opacity:.75;margin-top:4px}

/* Cartes */
.sm-card{background:#fff;border:1px solid var(--sm-border);border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:var(--sm-surface);border-bottom:1px solid var(--sm-border);padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}

/* Champ adresse source (éditable) */
.sm-email-row{display:flex;align-items:center;gap:0;max-width:420px}
.sm-email-row input{flex:1;padding:7px 10px;border:1px solid var(--sm-border-input);border-radius:4px 0 0 4px;border-right:none;font-size:13px}
.sm-email-row input:focus{border-color:var(--sm-primary);outline:none}
.sm-email-suffix{padding:7px 12px;background:var(--sm-surface);border:1px solid var(--sm-border-input);border-radius:0 4px 4px 0;font-size:13px;color:var(--sm-text-2);white-space:nowrap}

/* Bandeau info (thème indigo unifié) */
.sm-info-alert{background:var(--sm-primary-tint);border:1px solid var(--sm-primary-tint-border);border-radius:4px;padding:8px 12px;font-size:12px;color:var(--sm-primary);margin-bottom:12px;display:flex;align-items:flex-start;gap:8px}
.sm-info-alert i{flex-shrink:0;margin-top:2px}

/* Boutons d'action */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:var(--sm-surface);border:1px solid var(--sm-border);border-radius:6px}
.sm-btn-create{color:#fff;border:none;padding:8px 20px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;background:#aaa;transition:background .2s}
.sm-btn-create.ready{background:var(--sm-success)}
.sm-btn-create.ready:hover{background:var(--sm-success-dark)}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid var(--sm-border-input);padding:8px 16px;border-radius:4px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sm-btn-cancel:hover{background:#f5f5f5;color:var(--sm-text)}

/* En-tête de modale « bandeau plein » (couleur unifiée indigo). Kit modale
   de base : _sm_styles.css. */
.sm-mhead.green{background:var(--sm-primary);border-bottom:none}
.sm-mhead.green h4,.sm-mhead.green .sm-mclose{color:#fff}
{/literal}
{* Dark mode + fix <code> : injectés via hook (voir hooks.php). *}
</style>

{* ── Lien retour ─────────────────────────────────────────────────────── *}
<a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-back">
  <i class="fa fa-arrow-left"></i> {$lang.back_dashboard}
</a>

{* ── En-tête ─────────────────────────────────────────────────────────── *}
<div class="sm-header">
  <div class="sm-header-title">
    <i class="fa fa-share"></i>{$lang.add_redirect_title} &mdash; {$domain|escape}
  </div>
  <div class="sm-header-sub">{$lang.add_redirect_subtitle}</div>
</div>

{* ── Formulaire principal ────────────────────────────────────────────── *}
<form method="post" action="clientarea.php" id="form-addredirect">
  {*
   * Champs cachés WHMCS requis pour toutes les actions customAction.
   * Le champ aliasname est rempli dynamiquement par JS avant soumission
   * depuis l'input texte (valider avant de copier dans le champ caché).
   *}
  <input type="hidden" name="action"       value="productdetails">
  <input type="hidden" name="id"           value="{$serviceid}">
  <input type="hidden" name="customAction" value="createredirect">
  {* Jeton CSRF — validé par _sm_checkCsrf() dans le dispatcher avant *}
  {* exécution de createredirect.                                     *}
  <input type="hidden" name="token"        value="{$csrfToken|escape}">
  <input type="hidden" name="aliasname"    id="hid-aliasname" value="">

  {* ── Section : Adresse source ────────────────────────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header">
      <i class="fa fa-at"></i> {$lang.add_redirect_source_title}
    </div>
    <div class="sm-card-body">
      <div class="sm-info-alert">
        <i class="fa fa-info-circle"></i>
        {$lang.add_redirect_info}
      </div>

      <div style="margin-bottom:4px;">
        <label class="sm-form-label">
          {$lang.add_redirect_source_label}
          <span style="color:#e74c3c;">*</span>
        </label>
        <div class="sm-email-row">
          {*
           * L'adresse source est la partie avant le @.
           * Pattern alphanumérique + . - _ (identique aux noms d'utilisateur WHMCS).
           * La valeur est copiée dans #hid-aliasname lors de la soumission.
           *}
          <input type="text"
                 id="field-aliasname"
                 placeholder="{$lang.add_redirect_source_ph}"
                 pattern="[a-zA-Z0-9][a-zA-Z0-9._\-]*"
                 autocomplete="off"
                 autofocus
                 required>
          <span class="sm-email-suffix">@{$domain|escape}</span>
        </div>
        <div style="font-size:11px;color:#aaa;margin-top:3px;">
          {$lang.add_redirect_source_help}
        </div>
      </div>
    </div>
  </div>

  {* ── Section : Destinations ──────────────────────────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header">
      <i class="fa fa-share"></i> {$lang.add_redirect_dest_title}
      <span style="font-weight:400;color:#e74c3c;margin-left:4px;">*</span>
    </div>
    <div class="sm-card-body">
      {*
       * Pills des adresses de destination — construites dynamiquement en JS.
       * Chaque adresse ajoutée génère un <input type="hidden" name="targets[]">
       * transmis au PHP pour validation et création.
       *}
      <div class="sm-pills-wrap" id="sm-target-pills">
        <span class="sm-pills-empty" id="sm-targets-empty">
          {$lang.add_redirect_dest_empty}
        </span>
      </div>

      <div class="sm-add-trigger">
        <button type="button" class="sm-btn-add" onclick="smOpen('sm-target-modal')">
          <i class="fa fa-plus"></i> {$lang.add_redirect_dest_add}
        </button>
      </div>

      {* Les inputs cachés targets[] sont générés dynamiquement par JS *}
      <div id="sm-targets-hidden"></div>
    </div>
  </div>

  {* ── Boutons d'action ────────────────────────────────────────────────── *}
  <div class="sm-actions">
    <a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-btn-cancel">
      {$lang.btn_cancel}
    </a>
    {*
     * Le bouton Créer est désactivé (gris) jusqu'à ce que :
     *   1. Un nom d'alias valide soit saisi
     *   2. Au moins une adresse de destination soit ajoutée
     * La classe .ready active la couleur verte via JS.
     *}
    <button type="submit" id="btn-create" class="sm-btn-create" onclick="return smValidate()">
      <i class="fa fa-check"></i> {$lang.add_redirect_btn_create}
    </button>
  </div>

</form>


{* ════ MODALE : Ajouter une adresse de destination ═══════════════════════ *}
{*
 * Simple modale avec un champ texte pour saisir une adresse courriel.
 * La validation s'effectue en JS (format courriel) avant l'ajout.
 * Un deuxième niveau de validation PHP s'effectue côté serveur.
 *}
<div class="sm-overlay" id="sm-target-modal" onclick="smBg(event,'sm-target-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead green">
      <h4><i class="fa fa-share"></i> {$lang.add_redirect_modal_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-target-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <label class="sm-mlabel">{$lang.add_redirect_modal_label}</label>
      <input type="text"
             class="sm-minput-full"
             id="sm-target-input"
             placeholder="{$lang.add_redirect_modal_ph}"
             autocomplete="off"
             data-errinvalid="{$lang.err_redirect_invalid_target_js|escape}"
             data-errdup="{$lang.err_redirect_dup_target|escape}">
      <div class="sm-merr" id="sm-target-err"></div>
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-target-modal')">
        {$lang.btn_cancel}
      </button>
      <button type="button" class="btn btn-success btn-sm" onclick="smAddTarget()">
        <i class="fa fa-plus"></i> {$lang.eu_btn_add}
      </button>
    </div>
  </div>
</div>


<script>
{* Variables localisées injectées avant le bloc literal *}
var SM_LANG_TARGETS_EMPTY = '{$lang.add_redirect_dest_empty|escape:"javascript"}';
{literal}

// ── État de l'application ─────────────────────────────────────────────
// smTargets : tableau des adresses de destination en cours de saisie.
// Chaque adresse est stockée en minuscules pour la déduplication.
var smTargets = [];

// ── Listeners DOMContentLoaded ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  // Touche Entrée dans le champ aliasname → passer au focus suivant
  var aliasInput = document.getElementById('field-aliasname');
  if (aliasInput) {
    aliasInput.addEventListener('input', smCheckReady);
    aliasInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); smOpen('sm-target-modal'); }
    });
  }

  // Touche Entrée dans la modale destination → ajouter
  var targetInput = document.getElementById('sm-target-input');
  if (targetInput) {
    targetInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); smAddTarget(); }
    });
  }
});

// ── Modales ────────────────────────────────────────────────────────────
function smOpen(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
  // Focus automatique sur le premier input de la modale
  var inp = document.querySelector('#' + id + ' input');
  if (inp) setTimeout(function () { inp.focus(); }, 80);
}

function smClose(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}

// Fermeture au clic sur le fond de la modale
function smBg(e, id) {
  if (e.target === document.getElementById(id)) smClose(id);
}

// Fermeture à la touche Échap
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' || e.keyCode === 27) {
    document.querySelectorAll('.sm-overlay.open').forEach(function (el) {
      smClose(el.id);
    });
  }
});

// ── Ajout d'une adresse de destination ────────────────────────────────
// Valide le format courriel côté JS et ajoute l'adresse à smTargets.
// La validation PHP (filter_var) s'effectue aussi côté serveur.
function smAddTarget() {
  var input  = document.getElementById('sm-target-input');
  var errEl  = document.getElementById('sm-target-err');
  var addr   = input.value.trim().toLowerCase();

  errEl.style.display = 'none';
  if (!addr) return;

  // Validation format courriel (regex basique — PHP valide aussi)
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addr)) {
    errEl.textContent = input.dataset.errinvalid || 'Adresse courriel invalide.';
    errEl.style.display = 'block';
    return;
  }

  // Déduplication (insensible à la casse)
  if (smTargets.indexOf(addr) !== -1) {
    errEl.textContent = input.dataset.errdup || 'Cette adresse est déjà dans la liste.';
    errEl.style.display = 'block';
    return;
  }

  smTargets.push(addr);
  smRenderTargets();
  input.value = '';
  smClose('sm-target-modal');
  smCheckReady();
}

// ── Retrait d'une adresse de destination ──────────────────────────────
function smRemoveTarget(addr) {
  smTargets = smTargets.filter(function (a) { return a !== addr; });
  smRenderTargets();
  smCheckReady();
}

// ── Rendu des pills et des inputs cachés ─────────────────────────────
// Construit les pills visuelles et les champs targets[] soumis en POST.
function smRenderTargets() {
  var pillsEl  = document.getElementById('sm-target-pills');
  var hiddenEl = document.getElementById('sm-targets-hidden');
  var emptyEl  = document.getElementById('sm-targets-empty');

  if (!smTargets.length) {
    // Aucune destination — afficher le texte vide
    if (emptyEl) emptyEl.style.display = '';
    if (hiddenEl) hiddenEl.innerHTML = '';
    // Vider les pills sauf le span vide
    var pills = pillsEl.querySelectorAll('.sm-pill');
    pills.forEach(function (p) { p.remove(); });
    return;
  }

  // Masquer le texte vide dès qu'une destination est ajoutée
  if (emptyEl) emptyEl.style.display = 'none';

  // Reconstruire toutes les pills
  var pills = pillsEl.querySelectorAll('.sm-pill');
  pills.forEach(function (p) { p.remove(); });

  smTargets.forEach(function (addr) {
    var pill = document.createElement('span');
    pill.className = 'sm-pill';
    pill.innerHTML = '<i class="fa fa-share" style="font-size:10px;"></i> '
      + escHtml(addr)
      + '<button type="button" class="sm-pill-x" onclick="smRemoveTarget(\'' + escAttr(addr) + '\')">&times;</button>';
    pillsEl.appendChild(pill);
  });

  // Champs cachés targets[] — un par adresse
  hiddenEl.innerHTML = smTargets.map(function (addr) {
    return '<input type="hidden" name="targets[]" value="' + escAttr(addr) + '">';
  }).join('');
}

// ── Activation du bouton Créer ─────────────────────────────────────────
// Le bouton est actif seulement si :
//   1. Un nom d'alias valide est saisi (alphanumérique + . - _)
//   2. Au moins une adresse de destination est présente
function smCheckReady() {
  var aliasVal = (document.getElementById('field-aliasname') || {}).value || '';
  var isAliasOk  = /^[a-zA-Z0-9][a-zA-Z0-9._\-]*$/.test(aliasVal.trim());
  var hasTargets = smTargets.length > 0;
  var btn = document.getElementById('btn-create');
  if (!btn) return;
  if (isAliasOk && hasTargets) {
    btn.classList.add('ready');
  } else {
    btn.classList.remove('ready');
  }
}

// ── Validation avant soumission ────────────────────────────────────────
// Copie la valeur aliasname dans le champ caché avant POST.
// Bloque la soumission si les conditions ne sont pas remplies.
function smValidate() {
  var aliasVal = (document.getElementById('field-aliasname') || {}).value || '';
  aliasVal = aliasVal.trim().toLowerCase();

  if (!aliasVal || !/^[a-zA-Z0-9][a-zA-Z0-9._\-]*$/.test(aliasVal)) {
    document.getElementById('field-aliasname').focus();
    return false;
  }

  if (!smTargets.length) {
    smOpen('sm-target-modal');
    return false;
  }

  // Copier dans le champ caché avant soumission
  document.getElementById('hid-aliasname').value = aliasVal;
  return true;
}

// ── Utilitaires XSS-safe ──────────────────────────────────────────────
function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
function escAttr(s) {
  return String(s).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

{/literal}
</script>
