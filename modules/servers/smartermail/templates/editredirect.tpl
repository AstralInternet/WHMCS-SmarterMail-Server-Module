{* SmarterMail — Modifier une redirection autonome *}
{*
 * Formulaire de modification d'un alias de redirection existant.
 * Permet de modifier uniquement les adresses de destination.
 *
 * Le nom de l'alias (adresse source) est en lecture seule :
 * SmarterMail ne permet pas de renommer un alias directement — il faudrait
 * le supprimer et le recréer, ce qui n'est pas proposé ici pour éviter
 * une perte de données accidentelle.
 *
 * Variables injectées par smartermail_editredirectpage() :
 *   $aliasName — Nom de l'alias sans @domaine (ex: "bob")
 *   $targets   — Tableau des adresses de destination actuelles
 *   $domain    — Nom de domaine (ex: "mondomaine.com")
 *   $serviceid — ID du service WHMCS
 *   $lang      — Tableau de langue chargé par _sm_lang()
 *
 * SÉCURITÉ :
 *   - $aliasName validé par preg_match en PHP avant injection dans le template
 *   - $targets provient de l'API SmarterMail (source de confiance interne)
 *   - Toutes les valeurs passent par |escape avant affichage
 *   - Le formulaire POST pointe vers saveredirect (action whitlistée en PHP)
 *   - Suppression via formulaire POST séparé (pas un lien GET)
 *}

<style>
{literal}
/* ── Spécifique aux redirections ──────────────────────────────────
   Le reste (kit modale de base, pills, bouton [+ Ajouter], navigation
   retour, étiquettes de formulaire) est centralisé dans _sm_styles.css
   (injecté par le hook), en thème indigo unifié. ── */

/* En-tête — thème ardoise unifié (comme les pages de boîtes) */
.sm-header{background:linear-gradient(135deg,var(--sm-slate) 0%,var(--sm-slate-2) 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-title{font-size:16px;font-weight:700}
.sm-header-title i{margin-right:8px;opacity:.8}
.sm-header-sub{font-size:12px;opacity:.75;margin-top:4px}

/* Cartes */
.sm-card{background:#fff;border:1px solid var(--sm-border);border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:var(--sm-surface);border-bottom:1px solid var(--sm-border);padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}

/* Affichage de l'adresse source (lecture seule) */
.sm-email-display{display:flex;align-items:center;gap:0;max-width:420px}
.sm-email-display .sm-email-part{flex:1;padding:7px 10px;border:1px solid var(--sm-border);border-right:none;border-radius:4px 0 0 4px;font-size:13px;background:var(--sm-surface);color:#555;font-weight:500}
.sm-email-suffix{padding:7px 12px;background:var(--sm-surface);border:1px solid var(--sm-border);border-radius:0 4px 4px 0;font-size:13px;color:var(--sm-text-2);white-space:nowrap}
.sm-form-hint{color:#aaa;font-weight:400;font-size:11px;display:block;margin-top:3px}

/* Barre d'actions + boutons */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:var(--sm-surface);border:1px solid var(--sm-border);border-radius:6px}
.sm-btn-save{color:#fff;background:var(--sm-primary);border:none;padding:8px 20px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.sm-btn-save:hover{background:var(--sm-primary-dark)}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid var(--sm-border-input);padding:8px 16px;border-radius:4px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sm-btn-cancel:hover{background:#f5f5f5;color:var(--sm-text)}
.sm-btn-delete{background:none;border:1px solid var(--sm-danger);color:var(--sm-danger);padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;margin-left:auto}
.sm-btn-delete:hover{background:var(--sm-danger-tint)}

/* En-têtes de modale « bandeau plein » (spécifiques aux redirections),
   couleur unifiée : indigo (ajout/édition) et danger (suppression). Le kit
   modale de base vient de _sm_styles.css. */
.sm-mhead.green{background:var(--sm-primary);border-bottom:none}
.sm-mhead.green h4,.sm-mhead.green .sm-mclose{color:#fff}
.sm-mhead.red{background:var(--sm-danger-dark);border-bottom:none}
.sm-mhead.red h4,.sm-mhead.red .sm-mclose{color:#fff}
.sm-mhead.green .sm-mclose,.sm-mhead.red .sm-mclose{color:rgba(255,255,255,.7)}
.sm-mhead.green .sm-mclose:hover,.sm-mhead.red .sm-mclose:hover{color:#fff}
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
    <i class="fa fa-share"></i>{$lang.edit_redirect_title} &mdash; {$aliasName|escape}@{$domain|escape}
  </div>
  <div class="sm-header-sub">{$lang.edit_redirect_subtitle}</div>
</div>

{* ── Formulaire sauvegarde ───────────────────────────────────────────── *}
<form method="post" action="clientarea.php" id="form-editredirect">
  <input type="hidden" name="action"       value="productdetails">
  <input type="hidden" name="id"           value="{$serviceid}">
  <input type="hidden" name="customAction" value="saveredirect">
  {* Jeton CSRF — validé par _sm_checkCsrf() avant saveredirect. *}
  <input type="hidden" name="token"        value="{$csrfToken|escape}">
  {*
   * aliasname est en hidden non modifiable — le client ne peut pas
   * renommer la redirection depuis l'espace client (voir docblock).
   * SÉCURITÉ : validé par preg_match en PHP avant utilisation.
   *}
  <input type="hidden" name="aliasname"    value="{$aliasName|escape}">

  {* ── Section : Adresse source (lecture seule) ─────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header">
      <i class="fa fa-at"></i> {$lang.add_redirect_source_title}
    </div>
    <div class="sm-card-body">
      <label class="sm-form-label">{$lang.add_redirect_source_label}</label>
      {*
       * L'adresse source est affichée en lecture seule (pas d'input modifiable).
       * Le renommage n'est pas supporté car il nécessiterait delete + create,
       * avec risque de perte de données si l'API échoue entre les deux.
       *}
      <div class="sm-email-display">
        <span class="sm-email-part">{$aliasName|escape}</span>
        <span class="sm-email-suffix">@{$domain|escape}</span>
      </div>
      <span class="sm-form-hint">{$lang.edit_redirect_source_hint}</span>
    </div>
  </div>

  {* ── Section : Destinations ──────────────────────────────────────────── *}
  <div class="sm-card">
    <div class="sm-card-header">
      <i class="fa fa-share"></i> {$lang.add_redirect_dest_title}
      <span style="font-weight:400;color:#e74c3c;margin-left:4px;">*</span>
    </div>
    <div class="sm-card-body">
      <div class="sm-pills-wrap" id="sm-target-pills">
        <span class="sm-pills-empty" id="sm-targets-empty" style="display:none;">
          {$lang.add_redirect_dest_empty}
        </span>
      </div>

      <div class="sm-add-trigger">
        <button type="button" class="sm-btn-add" onclick="smOpen('sm-target-modal')">
          <i class="fa fa-plus"></i> {$lang.add_redirect_dest_add}
        </button>
      </div>

      <div id="sm-targets-hidden"></div>
    </div>
  </div>

  {* ── Boutons d'action ────────────────────────────────────────────────── *}
  <div class="sm-actions">
    <a href="clientarea.php?action=productdetails&id={$serviceid}" class="sm-btn-cancel">
      {$lang.btn_cancel}
    </a>
    <button type="submit" class="sm-btn-save">
      <i class="fa fa-check"></i> {$lang.btn_save}
    </button>
    {*
     * Bouton supprimer — ouvre une modale de confirmation avant de soumettre
     * le formulaire de suppression (formulaire POST distinct, non le formulaire
     * principal de modification). Séparation des intentions.
     *}
    <button type="button" class="sm-btn-delete" onclick="smOpen('sm-del-modal')">
      <i class="fa fa-trash-o"></i> {$lang.edit_redirect_btn_delete}
    </button>
  </div>

</form>


{* ── Formulaire de suppression (POST séparé) ─────────────────────────── *}
{*
 * Formulaire séparé pour la suppression — déclenché uniquement
 * après confirmation dans la modale. Utilise un POST (pas un GET link)
 * pour éviter la suppression accidentelle par pré-chargement du navigateur.
 *}
<form method="post" action="clientarea.php" id="form-delredirect" style="display:none;">
  <input type="hidden" name="action"       value="productdetails">
  <input type="hidden" name="id"           value="{$serviceid}">
  <input type="hidden" name="customAction" value="deleteredirect">
  {* Jeton CSRF — la suppression est irréversible. *}
  <input type="hidden" name="token"        value="{$csrfToken|escape}">
  <input type="hidden" name="aliasname"    value="{$aliasName|escape}">
</form>


{* ════ MODALES ═══════════════════════════════════════════════════════════ *}

{* ── Modale : Ajouter une destination ─────────────────────────────────── *}
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

{* ── Modale : Confirmer la suppression ─────────────────────────────────── *}
{*
 * Affiche l'adresse complète à supprimer pour que le client puisse
 * confirmer qu'il supprime bien la bonne redirection.
 *}
<div class="sm-overlay" id="sm-del-modal" onclick="smBg(event,'sm-del-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead red">
      <h4><i class="fa fa-trash-o"></i> {$lang.edit_redirect_del_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-del-modal')">&times;</button>
    </div>
    <div class="sm-mbody">
      <p style="font-size:13px;color:#555;margin:0 0 10px;">
        {$lang.edit_redirect_del_confirm}
      </p>
      <p style="font-size:14px;font-weight:700;color:#b71c1c;margin:0;">
        {$aliasName|escape}@{$domain|escape}
      </p>
      <p style="font-size:12px;color:#e74c3c;margin:10px 0 0;">
        <i class="fa fa-exclamation-triangle"></i>
        {$lang.edit_redirect_del_irreversible}
      </p>
    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-del-modal')">
        {$lang.btn_cancel}
      </button>
      <button type="button" class="btn btn-danger btn-sm" onclick="smConfirmDelete()">
        <i class="fa fa-trash-o"></i> {$lang.edit_redirect_del_confirm_btn}
      </button>
    </div>
  </div>
</div>


{* ── Données initiales injectées par PHP ─────────────────────────────── *}
{*
 * Les adresses existantes ($targets) sont sérialisées en JSON côté PHP avec
 * json_encode(..., JSON_HEX_TAG | JSON_UNESCAPED_UNICODE). JSON_HEX_TAG encode
 * les caractères < et > en \u003C / \u003E, ce qui empêche toute séquence
 * </script> dans les données de fermer prématurément ce bloc script.
 * La variable $targets est donc une STRING JSON prête à l'emploi — on utilise
 * {$targets nofilter} pour éviter un double-encodage Smarty qui briserait le JSON.
 *}
<script>
var SM_INITIAL_TARGETS = {$targets nofilter};
var SM_LANG_TARGETS_EMPTY = '{$lang.add_redirect_dest_empty|escape:"javascript"}';
</script>
<script>
{literal}

// ── État ──────────────────────────────────────────────────────────────
var smTargets = [];

// ── Initialisation — charger les destinations existantes ─────────────
document.addEventListener('DOMContentLoaded', function () {
  // Précharger les adresses existantes depuis PHP
  if (Array.isArray(SM_INITIAL_TARGETS)) {
    SM_INITIAL_TARGETS.forEach(function (addr) {
      if (addr && typeof addr === 'string') {
        smTargets.push(addr.toLowerCase().trim());
      }
    });
    smRenderTargets();
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
  var inp = document.querySelector('#' + id + ' input');
  if (inp) setTimeout(function () { inp.focus(); }, 80);
}

function smClose(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}

function smBg(e, id) {
  if (e.target === document.getElementById(id)) smClose(id);
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' || e.keyCode === 27) {
    document.querySelectorAll('.sm-overlay.open').forEach(function (el) {
      smClose(el.id);
    });
  }
});

// ── Ajout destination ─────────────────────────────────────────────────
function smAddTarget() {
  var input = document.getElementById('sm-target-input');
  var errEl = document.getElementById('sm-target-err');
  var addr  = input.value.trim().toLowerCase();

  errEl.style.display = 'none';
  if (!addr) return;

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addr)) {
    errEl.textContent = input.dataset.errinvalid || 'Adresse courriel invalide.';
    errEl.style.display = 'block';
    return;
  }

  if (smTargets.indexOf(addr) !== -1) {
    errEl.textContent = input.dataset.errdup || 'Cette adresse est déjà dans la liste.';
    errEl.style.display = 'block';
    return;
  }

  smTargets.push(addr);
  smRenderTargets();
  input.value = '';
  smClose('sm-target-modal');
}

// ── Retrait destination ───────────────────────────────────────────────
function smRemoveTarget(addr) {
  smTargets = smTargets.filter(function (a) { return a !== addr; });
  smRenderTargets();
}

// ── Rendu pills + inputs cachés ───────────────────────────────────────
function smRenderTargets() {
  var pillsEl  = document.getElementById('sm-target-pills');
  var hiddenEl = document.getElementById('sm-targets-hidden');
  var emptyEl  = document.getElementById('sm-targets-empty');

  // Nettoyer les pills existantes
  var existing = pillsEl.querySelectorAll('.sm-pill');
  existing.forEach(function (p) { p.remove(); });

  if (!smTargets.length) {
    if (emptyEl) emptyEl.style.display = '';
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

  hiddenEl.innerHTML = smTargets.map(function (addr) {
    return '<input type="hidden" name="targets[]" value="' + escAttr(addr) + '">';
  }).join('');
}

// ── Suppression ────────────────────────────────────────────────────────
// Soumettre le formulaire de suppression séparé après confirmation
function smConfirmDelete() {
  smClose('sm-del-modal');
  document.getElementById('form-delredirect').submit();
}

// ── Utilitaires ──────────────────────────────────────────────────────
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
