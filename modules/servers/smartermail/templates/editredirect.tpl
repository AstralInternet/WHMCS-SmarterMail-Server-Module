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
/* ── Layout (identique à addredirect.tpl) ─────────────────────── */
.sm-back{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#777;text-decoration:none;margin-bottom:14px}
.sm-back:hover{color:#3949ab}
.sm-header{background:linear-gradient(135deg,#1b5e20 0%,#2e7d32 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px}
.sm-header-title{font-size:16px;font-weight:700}
.sm-header-title i{margin-right:8px;opacity:.8}
.sm-header-sub{font-size:12px;opacity:.75;margin-top:4px}
.sm-card{background:#fff;border:1px solid #e0e0e0;border-radius:6px;margin-bottom:16px;overflow:hidden}
.sm-card-header{background:#f7f8fa;border-bottom:1px solid #e0e0e0;padding:9px 14px;font-weight:600;font-size:12px;color:#555;display:flex;align-items:center;gap:7px}
.sm-card-body{padding:14px}
/* ── Affichage adresse source (readonly) ────────────────────────── */
.sm-email-display{display:flex;align-items:center;gap:0;max-width:420px}
.sm-email-display .sm-email-part{flex:1;padding:7px 10px;border:1px solid #e0e0e0;border-right:none;border-radius:4px 0 0 4px;font-size:13px;background:#f7f8fa;color:#555;font-weight:500}
.sm-email-suffix{padding:7px 12px;background:#f7f8fa;border:1px solid #e0e0e0;border-radius:0 4px 4px 0;font-size:13px;color:#666;white-space:nowrap}
.sm-form-label{display:block;font-size:12px;color:#666;margin-bottom:4px;font-weight:600}
.sm-form-hint{color:#aaa;font-weight:400;font-size:11px;display:block;margin-top:3px}
/* ── Pills destinations ─────────────────────────────────────────── */
.sm-pills-wrap{display:flex;flex-wrap:wrap;gap:5px;min-height:28px;margin-bottom:10px}
.sm-pill{display:inline-flex;align-items:center;gap:5px;background:#e8f5e9;color:#2e7d32;border-radius:20px;padding:3px 10px 3px 12px;font-size:12px;font-weight:500}
.sm-pill-x{background:none;border:none;cursor:pointer;padding:0;line-height:1;font-size:14px;color:inherit;opacity:.6;display:flex;align-items:center}
.sm-pill-x:hover{opacity:1}
.sm-pills-empty{font-size:12px;color:#bbb;font-style:italic;padding:2px 0}
.sm-add-trigger{display:flex;justify-content:flex-end;margin-top:6px}
.sm-btn-add{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px dashed #2e7d32;color:#2e7d32;border-radius:4px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.sm-btn-add:hover{background:#2e7d32;color:#fff;border-style:solid}
/* ── Actions ────────────────────────────────────────────────────── */
.sm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px;background:#f7f8fa;border:1px solid #e0e0e0;border-radius:6px}
.sm-btn-save{color:#fff;background:#2e7d32;border:none;padding:8px 20px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.sm-btn-save:hover{background:#1b5e20}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid #ddd;padding:8px 16px;border-radius:4px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sm-btn-cancel:hover{background:#f5f5f5;color:#333}
/* Bouton supprimer — aligné à droite via margin-left:auto */
.sm-btn-delete{background:none;border:1px solid #e74c3c;color:#e74c3c;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;margin-left:auto}
.sm-btn-delete:hover{background:#fce4e4}
/* ── Modales ────────────────────────────────────────────────────── */
.sm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center}
.sm-overlay.open{display:flex}
.sm-mbox{background:#fff;border-radius:8px;width:100%;max-width:420px;margin:16px;box-shadow:0 8px 32px rgba(0,0,0,.2);animation:smFadeIn .18s ease}
@keyframes smFadeIn{from{transform:translateY(-12px);opacity:0}to{transform:translateY(0);opacity:1}}
.sm-mhead{padding:13px 16px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
.sm-mhead h4{margin:0;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.sm-mhead.green{background:#2e7d32;border-bottom:none}
.sm-mhead.green h4,.sm-mhead.green .sm-mclose{color:#fff}
.sm-mhead.red{background:#b71c1c;border-bottom:none}
.sm-mhead.red h4,.sm-mhead.red .sm-mclose{color:#fff}
.sm-mclose{background:none;border:none;font-size:20px;cursor:pointer;color:#999;line-height:1;padding:0}
.sm-mhead.green .sm-mclose,.sm-mhead.red .sm-mclose{color:rgba(255,255,255,.7)}
.sm-mclose:hover{color:#333}
.sm-mhead.green .sm-mclose:hover,.sm-mhead.red .sm-mclose:hover{color:#fff}
.sm-mbody{padding:18px}
.sm-mfoot{padding:12px 16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px}
.sm-mlabel{display:block;font-size:12px;color:#666;font-weight:600;margin-bottom:4px}
.sm-minput-full{width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box}
.sm-minput-full:focus{border-color:#2e7d32;outline:none}
.sm-merr{color:#e74c3c;font-size:12px;margin-top:6px;display:none}
{/literal}
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
