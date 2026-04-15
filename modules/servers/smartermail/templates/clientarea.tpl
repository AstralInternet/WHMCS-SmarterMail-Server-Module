{* SmarterMail — Tableau de bord client *}

{if $domainNotReady}

<div style="max-width:560px;margin:30px auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div style="background:linear-gradient(135deg,#2c3e50 0%,#3d5166 100%);padding:18px 22px;display:flex;align-items:center;gap:12px;">
    <i class="fa fa-envelope-o fa-lg" style="color:rgba(255,255,255,.7);"></i>
    <span style="color:#fff;font-size:15px;font-weight:700;">{$lang.domain_not_ready_title}</span>
  </div>
  <div style="padding:24px 28px;">
    <p style="font-size:13px;color:#555;margin:0 0 14px;line-height:1.7;">
      {*
       * Pas de modificateur |escape ici — Smarty 3 n'échappe pas par défaut,
       * donc <strong> et <br> dans la valeur de langue s'affichent correctement.
       * |escape:'html':false encodait les balises (affichage littéral) et
       * |nofilter n'est pas disponible dans cette version de Smarty/WHMCS.
       *}
      {$lang.domain_not_ready_msg|replace:'{$domain}':$domain}
    </p>
    <div style="background:#e8eaf6;border-radius:6px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px;">
      <i class="fa fa-ticket fa-lg" style="color:#3949ab;margin-top:2px;flex-shrink:0;"></i>
      <div>
        <strong style="font-size:13px;color:#3949ab;">{$lang.domain_not_ready_cta}</strong><br>
        <span style="font-size:12px;color:#555;">{$lang.domain_not_ready_sub}</span>
      </div>
    </div>
  </div>
</div>

{elseif $error}
<div class="alert alert-danger">
  <i class="fa fa-exclamation-triangle"></i> <strong>{$lang.err_label}</strong> {$error|escape}
</div>
{else}

{*
 * ── Fonction Smarty réutilisable : smCopyField ───────────────────────────────
 *
 * Génère un champ DNS copiable (input texte ou textarea) avec un bouton "Copier".
 * Utilisé 16 fois dans le guide DNS — défini ici en une seule fois pour éviter
 * la duplication de code et les problèmes de chemin d'include Smarty.
 *
 * POURQUOI une {function} plutôt qu'un {include} ?
 *   {include file='...'} résout le chemin depuis le répertoire du thème WHMCS,
 *   pas depuis le module. Le chemin '../smartermail/templates/...' est invalide
 *   dans ce contexte et lève une erreur Smarty au rendu.
 *   Une {function} est définie et résolue dans le même fichier — aucun chemin
 *   externe n'est impliqué.
 *
 * Paramètres (passés via {call name="smCopyField" ...}) :
 *   fieldId    {string} — ID HTML unique de l'élément (ex: 'cp-dkim-host').
 *                         Doit être unique dans la page car smCopy() cible par ID.
 *                         Chaque onglet utilise son propre préfixe (cp/pl/cl/ge).
 *   fieldValue {string} — Valeur à afficher et à copier.
 *                         Smarty applique |escape automatiquement sur les attributs.
 *   isTextarea {bool}   — true → <textarea rows="3"> (pour les longues valeurs DKIM)
 *                         false (défaut) → <input type="text"> (host, mécanisme SPF)
 *
 * SÉCURITÉ : fieldValue est échappé via |escape dans chaque attribut value= et
 *            dans le contenu du textarea pour prévenir toute injection XSS,
 *            même si la valeur provient d'une source externe (API SmarterMail).
 *}
{function name="smCopyField" fieldId="" fieldValue="" isTextarea=false}
<div class="sm-dns-copy-wrap">
  {if $isTextarea}
    {* Textarea pour les valeurs longues — ex: clé publique DKIM (v=DKIM1; k=rsa; p=...) *}
    <textarea id="{$fieldId|escape}"
              rows="3"
              readonly
              aria-label="{$fieldId|escape}">{$fieldValue|escape}</textarea>
  {else}
    {* Input texte pour les valeurs courtes — ex: hôte DKIM, mécanisme SPF *}
    <input type="text"
           id="{$fieldId|escape}"
           readonly
           value="{$fieldValue|escape}"
           aria-label="{$fieldId|escape}">
  {/if}
  {*
   * Bouton copier — type="button" obligatoire pour ne pas soumettre le formulaire
   * parent (toggle DKIM) si ce champ se trouve à l'intérieur d'un <form>.
   * smCopy() change temporairement l'icône en ✓ après la copie.
  *}
  <button type="button"
          class="sm-copy-btn"
          onclick="smCopy('{$fieldId|escape}', this)"
          title="{$lang.btn_copy|escape}">
    <i class="fa fa-copy"></i>
  </button>
</div>
{/function}

<style>
{literal}
.sm-card{background:#fff;border:1px solid #e0e0e0;border-radius:6px;margin-bottom:18px;overflow:hidden}
.sm-card-header{background:#f7f8fa;border-bottom:1px solid #e0e0e0;padding:10px 16px;font-weight:600;font-size:13px;color:#444;display:flex;align-items:center;gap:8px;justify-content:space-between}
.sm-card-body{padding:14px 16px}
/* ── Bouton « Ouvrir le webmail » ─────────────────────────────────────
   Lien pleine largeur placé sous le bloc Statistiques. Stylé comme un
   bouton mais sémantiquement un <a> (navigation vers le webmail).
   Couleur cohérente avec le thème de l'en-tête domaine (#2c3e50). */
.sm-webmail-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px 16px;background:#0080c4;color:#fff !important;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;transition:background .15s,box-shadow .15s;margin-bottom:18px;box-sizing:border-box}
.sm-webmail-btn:hover{background:#0091de;box-shadow:0 2px 8px rgba(0,0,0,.15);text-decoration:none}
.sm-webmail-btn i{font-size:14px}
.sm-domain-header{background:linear-gradient(135deg,#2c3e50 0%,#3d5166 100%);color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.sm-domain-name{font-size:18px;font-weight:700;letter-spacing:.3px}
.sm-domain-name i{margin-right:8px;opacity:.8}
.sm-stat-row{display:flex;align-items:center;padding:6px 0;border-bottom:1px solid #f2f2f2;font-size:13px}
.sm-stat-row:last-child{border-bottom:none}
.sm-stat-label{color:#777;width:170px;flex-shrink:0;display:flex;align-items:center;gap:7px}
.sm-stat-label i{width:14px;text-align:center;color:#aaa}
.sm-stat-value{color:#222;font-weight:500;flex:1;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.sm-info-row{display:flex;align-items:flex-start;padding:5px 0;border-bottom:1px solid #f2f2f2;font-size:13px}
.sm-info-row:last-child{border-bottom:none}
.sm-info-label{color:#888;width:150px;flex-shrink:0;font-size:12px}
.sm-info-value{color:#222;font-weight:500;flex:1}
.sm-badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600}
.sm-badge.success{background:#e8f5e9;color:#2e7d32}.sm-badge.warning{background:#fff8e1;color:#f57f17}
.sm-badge.danger{background:#fce4ec;color:#c62828}.sm-badge.info{background:#e3f2fd;color:#1565c0}
.sm-badge.default{background:#f5f5f5;color:#555}

/* ── DNS status indicators ─────────────────────────────────────────── */
.sm-dns-ok{color:#2e7d32;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.sm-dns-err{color:#c62828;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.sm-dns-standby{color:#e65100;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.sm-dns-na{color:#aaa;display:inline-flex;align-items:center;gap:5px}
.sm-dns-btn{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border:1px solid #ccc;border-radius:3px;font-size:11px;font-weight:600;cursor:pointer;background:#fff;color:#555;transition:background .12s;margin-left:2px}
.sm-dns-btn:hover{background:#f5f5f5;color:#333}
.sm-dns-btn.ok{color:#2e7d32;border-color:#a5d6a7}.sm-dns-btn.ok:hover{background:#e8f5e9}
.sm-dns-btn.err{color:#c62828;border-color:#ef9a9a}.sm-dns-btn.err:hover{background:#fce4e4}
.sm-dns-btn.standby{color:#e65100;border-color:#ffcc80}.sm-dns-btn.standby:hover{background:#fff3e0}

/* ── Table liste comptes ────────────────────────────────────────────── */
.sm-table-wrap{border:1px solid #e0e0e0;border-radius:4px;overflow:hidden}
/* flex-wrap:nowrap + align-items:stretch : toutes les cellules ont la même hauteur */
.sm-list-header,.sm-user-row{display:flex;flex-wrap:nowrap;align-items:stretch;border-bottom:1px solid #f0f0f0}
.sm-list-header{background:#f7f8fa;border-bottom:2px solid #e0e0e0}
.sm-user-row:last-child{border-bottom:none}
.sm-user-row:hover{background:#fafafa}
/* Padding uniforme 8px sur toutes les cellules de données */
/* Colonne type : icône boîte courriel ou redirection — largeur fixe compacte */
.sm-col-type{flex:0 0 36px;padding:8px 8px;display:flex;align-items:center;justify-content:center}
.sm-col-email{flex:3;min-width:0;padding:8px 12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center}
.sm-col-alias{flex:2;min-width:0;padding:8px 12px;display:flex;align-items:center;flex-wrap:wrap;gap:3px}
.sm-col-fwd{flex:2;min-width:0;padding:8px 12px;display:flex;align-items:center;flex-wrap:wrap}
.sm-col-size{flex:0 0 80px;padding:8px 12px;display:flex;align-items:center;justify-content:flex-end;font-size:12px;color:#555;font-variant-numeric:tabular-nums}
.sm-col-proto{flex:0 0 110px;padding:8px 10px;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.sm-col-action{flex:0 0 88px;padding:8px 10px;display:flex;align-items:center;justify-content:flex-end}
/* En-tête : padding:0 sur la cellule, le bouton apporte son propre padding */
.sm-list-header .sm-col-email,
.sm-list-header .sm-col-alias,
.sm-list-header .sm-col-fwd,
.sm-list-header .sm-col-size,
.sm-list-header .sm-col-action{padding:0}
/* Proto header : texte statique, on garde le padding de la cellule */
.sm-list-header .sm-col-proto{font-size:11px;font-weight:600;color:#555}
.sm-proto-badge{display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:700;line-height:1.6}
.sm-proto-badge.eas{background:#e8f5e9;color:#2e7d32}
.sm-proto-badge.mapi{background:#e8eaf6;color:#3949ab}
/* Bouton de tri : prend toute la hauteur de la cellule header */
.sm-sort-btn{background:none;border:none;padding:8px 12px;cursor:pointer;font-size:12px;font-weight:600;color:#555;display:flex;align-items:center;gap:3px;width:100%;height:100%;text-align:left}
.sm-sort-btn:hover{color:#222;background:#eff0f1}
.sm-sort-icon{font-size:10px;color:#bbb}
.sm-sort-btn.active{color:#3949ab}
.sm-sort-btn.active .sm-sort-icon{color:#3949ab}
/* Bouton taille : aligné à droite */
#sort-size{justify-content:flex-end}
.sm-tag{display:inline-block;background:#e8eaf6;color:#3949ab;border-radius:3px;padding:1px 7px;font-size:11px;margin:1px 2px 1px 0}
.sm-tag-fwd{background:#e8f5e9;color:#2e7d32}
.sm-empty{color:#ddd}
.sm-no-results{text-align:center;padding:30px;color:#aaa}
.sm-toolbar{display:flex;gap:8px;margin-bottom:10px;align-items:center;justify-content:space-between}
.sm-toolbar-search{flex:0 0 auto;display:flex;align-items:center;gap:6px}
.sm-toolbar-search label{font-size:12px;color:#888;white-space:nowrap}
.sm-toolbar-search input{width:200px;padding:5px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px}
.sm-toolbar-right{flex:0 0 auto;display:flex;align-items:center;gap:6px}
.sm-toolbar-right label{font-size:12px;color:#888;white-space:nowrap}
.sm-toolbar-right select{padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px;background:#fff}
.sm-pagination{display:flex;gap:3px;align-items:center;justify-content:center;padding:10px 0 4px;flex-wrap:wrap}
.sm-page-btn{min-width:30px;height:26px;padding:0 7px;border:1px solid #ddd;border-radius:4px;background:#fff;font-size:12px;cursor:pointer;line-height:1}
.sm-page-btn:hover:not([disabled]):not(.active){background:#f5f5f5}
.sm-page-btn.active{background:#3949ab;color:#fff;border-color:#3949ab;font-weight:600}
.sm-page-btn[disabled]{opacity:.4;cursor:default}
.sm-page-ellipsis{font-size:12px;color:#aaa;padding:0 3px}

/* ── Boutons standardisés du module ──────────────────────────────────── */
/* Définis ici pour être disponibles dans les modales de clientarea.tpl.  */
/* Les mêmes classes sont utilisées dans adduser, edituser, etc.         */
.sm-btn-add{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px dashed #3949ab;color:#3949ab;border-radius:4px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.sm-btn-add:hover{background:#3949ab;color:#fff;border-style:solid}
.sm-btn-cancel{background:#fff;color:#555;border:1px solid #ddd;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.sm-btn-cancel:hover{background:#f5f5f5;color:#333}
.sm-btn-create{color:#fff;border:none;padding:8px 20px;border-radius:4px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;background:#aaa;transition:background .2s}
.sm-btn-create.ready{background:#27ae60}
.sm-btn-create.ready:hover{background:#219a52}
.sm-btn-del{background:#fff;color:#e74c3c;border:1px solid #e74c3c;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.sm-btn-del:hover{background:#fce4e4}

/* ── Overlay modale générique ───────────────────────────────────────── */
.sm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center}
.sm-overlay.open{display:flex}
.sm-mbox{background:#fff;border-radius:8px;width:100%;max-width:680px;margin:16px;box-shadow:0 8px 32px rgba(0,0,0,.2);animation:smFadeIn .18s ease;overflow:hidden}
.sm-mbox.sm-mbox-sm{max-width:460px}
@keyframes smFadeIn{from{transform:translateY(-12px);opacity:0}to{transform:translateY(0);opacity:1}}
.sm-mhead{padding:13px 16px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
.sm-mhead h4{margin:0;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.sm-mclose{background:none;border:none;font-size:20px;cursor:pointer;color:#999;line-height:1;padding:0}
.sm-mclose:hover{color:#333}
.sm-mbody{padding:18px}
.sm-mfoot{padding:12px 16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px}
.sm-mhead.dark{background:#2c3e50;border-bottom:none}
.sm-mhead.dark h4,.sm-mhead.dark .sm-mclose{color:#fff}
.sm-mhead.green{background:#1b5e20;border-bottom:none}
.sm-mhead.green h4,.sm-mhead.green .sm-mclose{color:#fff}
.sm-mhead.red{background:#b71c1c;border-bottom:none}
.sm-mhead.red h4,.sm-mhead.red .sm-mclose{color:#fff}
.sm-mhead.orange{background:#e65100;border-bottom:none}
.sm-mhead.orange h4,.sm-mhead.orange .sm-mclose{color:#fff}
.sm-mlabel{display:block;font-size:12px;color:#666;font-weight:600;margin-bottom:4px}
.sm-mfg{margin-bottom:14px}
.sm-mfg:last-child{margin-bottom:0}
.sm-record-wrap{display:flex;gap:0}
.sm-record-wrap textarea,.sm-record-wrap input[type="text"]{flex:1;font-family:monospace;font-size:12px;background:#f7f8fa;border:1px solid #ddd;border-right:none;border-radius:4px 0 0 4px;padding:8px 10px;resize:none}
.sm-record-wrap .sm-copy-btn{padding:0 12px;border:1px solid #ddd;border-radius:0 4px 4px 0;background:#f7f8fa;cursor:pointer;font-size:13px;color:#555;display:flex;align-items:center}
.sm-record-wrap .sm-copy-btn:hover{background:#eee}
.sm-info-alert{background:#e3f2fd;border:1px solid #90caf9;border-radius:4px;padding:8px 12px;font-size:12px;color:#1565c0;margin-top:12px}
.sm-warn-alert{background:#fff8e1;border:1px solid #ffe082;border-radius:4px;padding:8px 12px;font-size:12px;color:#f57f17;margin-top:12px}

@media(max-width:600px){
  .sm-list-header .sm-col-alias,.sm-list-header .sm-col-fwd,
  .sm-list-header .sm-col-size,.sm-list-header .sm-col-proto,.sm-list-header .sm-col-action{display:none}
  .sm-col-alias,.sm-col-fwd,.sm-col-size,.sm-col-proto{display:none}
  /* sm-col-type reste visible sur mobile — l'icône est utile même en petit écran */
  .sm-list-header .sm-col-email{flex:1}
}
@media(max-width:767px){
  .sm-domain-header{flex-direction:column;align-items:flex-start}
  .sm-stat-label,.sm-info-label{width:130px;font-size:12px}
}

/* ── Bouton bascule DKIM (toggle switch) ──────────────────────────────── */
/* Le toggle est un bouton stylisé en interrupteur on/off.                 */
/* Il contient un "knob" (pastille) qui se déplace selon l'état.           */
.sm-toggle-btn{
  display:inline-flex;align-items:center;position:relative;
  width:40px;height:22px;border-radius:11px;border:none;cursor:pointer;
  background:#ccc;transition:background .2s;padding:0;vertical-align:middle;
  flex-shrink:0;
}
/* État actif (DKIM activé) : fond vert */
.sm-toggle-btn.active{background:#2e7d32}
/* Pastille blanche du toggle */
.sm-toggle-knob{
  display:block;width:18px;height:18px;border-radius:50%;
  background:#fff;position:absolute;left:2px;top:2px;
  transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3);
}
/* Déplacement de la pastille quand actif */
.sm-toggle-btn.active .sm-toggle-knob{transform:translateX(18px)}

/* ── Guide DNS — Onglets ────────────────────────────────────────────────── */
/* Barre d'onglets horizontale au-dessus du contenu */
.sm-dns-tabs{
  display:flex;border-bottom:2px solid #e0e0e0;
  background:#f7f8fa;flex-wrap:wrap;
}
/* Bouton d'onglet individuel */
.sm-dns-tab-btn{
  background:none;border:none;border-bottom:2px solid transparent;
  padding:10px 16px;font-size:13px;font-weight:600;color:#888;
  cursor:pointer;margin-bottom:-2px;transition:color .15s,border-color .15s;
  display:flex;align-items:center;gap:6px;
}
.sm-dns-tab-btn:hover{color:#3949ab}
/* Onglet actif : soulignement bleu */
.sm-dns-tab-btn.active{color:#3949ab;border-bottom-color:#3949ab}

/* Panneau de contenu d'un onglet — caché par défaut */
.sm-dns-tab-pane{display:none}
/* Panneau actif — visible */
.sm-dns-tab-pane.active{display:block}

/* Corps du guide : marges internes confortables */
.sm-dns-guide-body{padding:20px 22px 10px}

/* Titre principal du guide (h3) */
.sm-dns-guide-title{
  font-size:14px;font-weight:700;color:#2c3e50;
  margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #f0f0f0;
}

/* Section DKIM ou SPF à l'intérieur d'un onglet */
.sm-dns-section{
  margin-top:16px;padding-top:14px;border-top:1px solid #f0f0f0;
}
/* Titre de section (h4) */
.sm-dns-section-title{
  font-size:13px;font-weight:700;color:#3949ab;margin:0 0 10px;
}

/* Listes numérotées / à puces du guide */
.sm-dns-steps{
  font-size:13px;color:#444;line-height:1.8;
  padding-left:22px;margin:6px 0 10px;
}
.sm-dns-steps li{margin-bottom:4px}

/* Bloc conditionnel (✅ existe / ❌ n'existe pas) */
.sm-dns-cond-block{
  border-radius:6px;padding:12px 14px;margin:10px 0;font-size:13px;
}
.sm-dns-cond-block.ok{background:#e8f5e9;border:1px solid #c8e6c9}
.sm-dns-cond-block.err{background:#fff8e1;border:1px solid #ffe082}
/* Libellé en gras du bloc conditionnel */
.sm-dns-cond-label{
  display:block;font-size:12px;font-weight:700;margin-bottom:8px;
}

/* Champ copiable (input ou textarea + bouton copier) */
.sm-dns-copy-wrap{
  display:flex;margin-top:6px;margin-bottom:4px;gap:0;
}
.sm-dns-copy-wrap input,.sm-dns-copy-wrap textarea{
  flex:1;font-family:monospace;font-size:12px;
  background:#f7f8fa;border:1px solid #ddd;
  border-right:none;border-radius:4px 0 0 4px;
  padding:6px 10px;resize:none;color:#333;
}
.sm-dns-copy-wrap .sm-copy-btn{
  padding:0 12px;border:1px solid #ddd;border-radius:0 4px 4px 0;
  background:#f7f8fa;cursor:pointer;font-size:13px;color:#555;
  display:flex;align-items:center;white-space:nowrap;
}
.sm-dns-copy-wrap .sm-copy-btn:hover{background:#eee}

/* Pied de page du guide — avertissement propagation DNS */
.sm-dns-guide-footer{
  background:#fff3e0;border-top:1px solid #ffe0b2;
  padding:10px 18px;font-size:12px;color:#e65100;
  flex-shrink:0;  /* reste toujours visible en bas de la modale */
}

/* ── Bouton-lien "Comment configurer vos DNS" ──────────────────────────── */
/* Aspect discret de lien, mais accessibilité d'un bouton (pas de navigation) */
.sm-dns-card-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 16px;cursor:pointer;user-select:none;
  background:#f7f8fa;border-bottom:1px solid #e0e0e0;
  border-radius:6px 6px 0 0;
}
.sm-dns-card-header:hover{background:#f0f1f3}
.sm-dns-card-left{display:flex;align-items:center;gap:8px}
.sm-dns-card-pills{display:flex;gap:5px}
.sm-dns-card-right{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;color:#444}
.sm-dns-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase}
.sm-dns-pill.ok{background:#e8f5e9;color:#2e7d32}
.sm-dns-pill.warn{background:#fff8e1;color:#f57f17}
.sm-dns-pill.err{background:#fce4e4;color:#c62828}
.sm-dns-pill.na{background:#f5f5f5;color:#9e9e9e}
.sm-dns-card-toggle{color:#aaa;font-size:14px;transition:transform .2s;flex-shrink:0}
.sm-dns-card-toggle.open{transform:rotate(180deg)}
.sm-dns-card-body{overflow:hidden;transition:max-height .3s ease}
.sm-dns-card-body.collapsed{max-height:0 !important}
.sm-billing-detail-btn{
  background:none;border:none;padding:0 4px;cursor:pointer;
  color:#3949ab;font-size:14px;vertical-align:middle;line-height:1;
}
.sm-billing-detail-btn:hover{color:#1a237e;}
.sm-billing-detail-line{display:flex;justify-content:space-between;align-items:baseline;padding:3px 0;font-size:13px}
.sm-billing-detail-line .sm-bd-email{color:#333;flex:1}
.sm-billing-detail-line .sm-bd-range{font-size:11px;color:#888;margin:0 8px;font-style:italic}
.sm-billing-detail-line .sm-bd-price{font-weight:600;color:#2c3e50;white-space:nowrap}
.sm-billing-detail-section{margin-top:14px}
.sm-billing-detail-section h5{font-size:12px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.5px;margin:0 0 6px;padding-bottom:4px;border-bottom:1px solid #f0f0f0}
.sm-bd-deleted{color:#aaa;font-style:italic;text-decoration:line-through}
.sm-bd-grace{color:#f39c12}
.sm-billing-detail-total{margin-top:14px;padding-top:10px;border-top:2px solid #e0e0e0;display:flex;justify-content:space-between;font-weight:700;font-size:14px}
.sm-billing-period{font-size:11px;color:#aaa;margin-bottom:14px}
/* ── Légende de la liste des adresses ─────────────────────────────────── */
.sm-list-legend{display:flex;gap:16px;padding:8px 14px;border-top:1px solid #f0f0f0;font-size:11px;color:#888;flex-wrap:wrap}
.sm-list-legend-item{display:inline-flex;align-items:center;gap:5px}
/* Icône boîte courriel — bleu discret */
.sm-icon-mailbox{color:#3949ab;font-size:13px}
/* Lien webmail : enveloppe l'icône fa-inbox dans un <a> cliquable.
   Hérite la couleur de l'icône, ajoute un effet hover subtil.
   Le curseur pointer signale l'interaction au client. */
.sm-webmail-link{color:inherit;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:opacity .15s}
.sm-webmail-link:hover{opacity:.7}
.sm-webmail-link:hover .sm-icon-mailbox{color:#1a237e}
/* Icône redirection autonome — vert discret */
.sm-icon-redirect{color:#2e7d32;font-size:13px}
.sm-dns-guide-link{
  background:none;border:none;padding:0;cursor:pointer;
  font-size:12px;color:#3949ab;display:inline-flex;align-items:center;gap:5px;
  text-underline-offset:2px;
}
.sm-dns-guide-link:hover{color:#1a237e;}

/* ── Modale guide DNS — hauteur max 80% écran ──────────────────────────── */
/* sm-mbox-guide surcharge sm-mbox pour un max-width plus large et la gestion */
/* du défilement interne. La modale est flex pour fixer header/footer.        */
.sm-mbox-guide{
  max-width:720px;
  display:flex;
  flex-direction:column;
  max-height:80vh;  /* jamais plus de 80% de la hauteur de l'écran */
}
/* Zone défilable entre l'en-tête et le pied de page */
.sm-dns-guide-scroll{
  overflow-y:auto;
  flex:1;          /* prend tout l'espace disponible entre header et footer */
  min-height:0;    /* nécessaire pour que flex+overflow fonctionne dans certains navigateurs */
}
/* Barre d'onglets sticky dans la zone défilable — reste visible en scrollant */
.sm-dns-guide-scroll .sm-dns-tabs{
  position:sticky;
  top:0;
  z-index:10;
  background:#f7f8fa;
  border-bottom:2px solid #e0e0e0;
}

@media(max-width:600px){
  .sm-dns-tab-btn{padding:8px 10px;font-size:12px}
  .sm-dns-guide-body{padding:14px 14px 8px}
  .sm-mbox-guide{max-height:90vh}  /* plus d'espace sur mobile */
}

/* ── Alias de domaine — Styles spécifiques ───────────────────────────── */
/* La carte utilise .sm-card / .sm-card-header / .sm-card-body existants. */
/* Seuls les éléments propres aux alias de domaine ont des styles dédiés. */

/* Conteneur des pills + bouton ajout */
.sm-da-pills{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
/* Pill individuel — accent indigo #3949ab cohérent avec le module */
.sm-da-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#e8eaf6;color:#3949ab;transition:background .12s}
.sm-da-pill:hover{background:#c5cae9}
/* Bouton × dans le pill */
.sm-da-pill-x{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:transparent;border:none;color:#c62828;font-size:13px;font-weight:700;cursor:pointer;line-height:1;padding:0;transition:background .12s}
.sm-da-pill-x:hover{background:rgba(198,40,40,.12)}
/* Message état vide */
.sm-da-empty{color:#aaa;font-size:12px;font-style:italic;margin:0}
/* Nom de l'alias mis en évidence dans la modale de suppression */
.sm-da-del-name{font-weight:700;color:#c62828;font-size:14px;margin:8px 0}
/* Avertissement de suppression */
.sm-da-del-warn{font-size:12px;color:#e65100;margin-top:6px;display:flex;align-items:center;gap:5px}

/* ── Tooltip cliquable [i] — réutilisable ───────────────────────────── */
.sm-tooltip-wrap{position:relative;display:inline-flex;align-items:center}
.sm-tooltip-bubble{display:none;position:absolute;top:calc(100% + 6px);left:50%;transform:translateX(-50%);width:280px;padding:10px 12px;background:#263238;color:#eceff1;font-size:11px;font-weight:400;line-height:1.5;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.25);z-index:100;pointer-events:auto}
.sm-tooltip-bubble::before{content:'';position:absolute;top:-5px;left:50%;transform:translateX(-50%);border-left:5px solid transparent;border-right:5px solid transparent;border-bottom:5px solid #263238}
.sm-tooltip-bubble.visible{display:block}
.sm-tooltip-trigger{color:#90a4ae;cursor:pointer;font-size:12px;transition:color .12s}
.sm-tooltip-trigger:hover{color:#546e7a}
{/literal}
</style>

{* ── En-tête domaine ─────────────────────────────────────────────────── *}
<div class="sm-domain-header">
  <div class="sm-domain-name"><i class="fa fa-envelope"></i>{$domain|escape}</div>
  <span class="sm-badge {$svcStatusClass|escape}">{$svcStatusLabel|escape}</span>
</div>

{* ── Section 1 : Stats + Infos service ──────────────────────────────── *}
<div class="row">

  {* ── Statistiques ──────────────────────────────────────────────────── *}
  <div class="col-md-7 col-sm-12">
    <div class="sm-card">
      <div class="sm-card-header"><i class="fa fa-hdd-o"></i> {$lang.dash_stats_title}</div>
      <div class="sm-card-body">

        {* Usage stockage *}
        <div style="margin-bottom:12px;">
          <span style="font-size:22px;font-weight:700;color:#2c3e50;">{$usageGB|number_format:3}</span>
          <span style="font-size:13px;color:#999;margin-left:4px;">{$lang.dash_storage_go} {$lang.dash_storage_used}</span>
          {if $basePrice > 0}
          <span style="display:inline-block;margin-left:12px;font-size:13px;color:#555;">
            <strong style="color:#222;">${$estimatedPrice|number_format:2}</strong>
            <span style="color:#aaa;font-size:11px;"> ({$tiers} {$lang.dash_storage_tiers}{if $tiers > 1}s{/if} {$lang.dash_storage_of} {$gbPerTier} {$lang.dash_storage_go} × ${$basePrice|number_format:2})</span>
          </span>
          {/if}
        </div>

        <div class="sm-stat-row">
          <div class="sm-stat-label"><i class="fa fa-inbox"></i> {$lang.stat_email_accounts}</div>
          <div class="sm-stat-value">{$userCount}</div>
        </div>
        <div class="sm-stat-row">
          <div class="sm-stat-label"><i class="fa fa-at"></i> {$lang.stat_aliases}</div>
          <div class="sm-stat-value">{$aliasCount}</div>
        </div>

        {* EAS seul *}
        {if $easEnabled && $easOnlyCount > 0}
        <div class="sm-stat-row">
          <div class="sm-stat-label"><i class="fa fa-mobile"></i> {$lang.stat_eas}</div>
          <div class="sm-stat-value">
            {$easOnlyCount}
            {if $easPrice > 0}
              <small style="color:#aaa;font-weight:400;">
                &mdash; ${$easOnlyCost|number_format:2}{$lang.per_month}
                <span style="color:#ccc;">(${$easPrice|number_format:2} × {$easOnlyCount})</span>
              </small>
            {/if}
          </div>
        </div>
        {/if}

        {* MAPI seul *}
        {if $mapiEnabled && $mapiOnlyCount > 0}
        <div class="sm-stat-row">
          <div class="sm-stat-label"><i class="fa fa-windows"></i> {$lang.stat_mapi}</div>
          <div class="sm-stat-value">
            {$mapiOnlyCount}
            {if $mapiPrice > 0}
              <small style="color:#aaa;font-weight:400;">
                &mdash; ${$mapiOnlyCost|number_format:2}{$lang.per_month}
                <span style="color:#ccc;">(${$mapiPrice|number_format:2} × {$mapiOnlyCount})</span>
              </small>
            {/if}
          </div>
        </div>
        {/if}

        {* EAS + MAPI combiné *}
        {if $easEnabled && $mapiEnabled && $combinedCount > 0}
        <div class="sm-stat-row">
          <div class="sm-stat-label"><i class="fa fa-link"></i> {$lang.stat_combined}</div>
          <div class="sm-stat-value">
            {$combinedCount}
            {if $effectiveBundlePrice > 0}
              <small style="color:#aaa;font-weight:400;">
                &mdash; ${$combinedCost|number_format:2}{$lang.per_month}
                <span style="color:#ccc;">(${$effectiveBundlePrice|number_format:2} × {$combinedCount})</span>
              </small>
            {/if}
          </div>
        </div>
        {/if}

        {* Total protocoles si > 0 *}
        {if $totalProtoCost > 0}
        <div class="sm-stat-row" style="border-top:1px solid #eee;margin-top:4px;padding-top:4px;">
          <div class="sm-stat-label" style="color:#555;font-weight:600;"><i class="fa fa-calculator"></i> {$lang.stat_total_proto}</div>
          <div class="sm-stat-value" style="font-weight:600;">${$totalProtoCost|number_format:2}{$lang.per_month}</div>
        </div>
        {/if}

      </div>
    </div>

    {*
     * ── Bouton « Ouvrir le webmail » ──────────────────────────────────────
     *
     * Lien direct vers l'interface webmail SmarterMail du serveur.
     * Placé juste sous le bloc Statistiques, dans la même colonne (col-md-7).
     *
     * SÉCURITÉ :
     *   - $webmailUrl est construit côté PHP à partir de tblservers.hostname
     *     (champ accessible uniquement aux administrateurs WHMCS).
     *   - |escape neutralise toute injection XSS dans l'attribut href.
     *   - target="_blank" avec rel="noopener noreferrer" empêche l'accès
     *     à window.opener depuis la page ouverte (prévention tabnabbing).
     *
     * ACCESSIBILITÉ :
     *   - Le bouton est un <a> stylé en bouton pour conserver la sémantique
     *     de navigation (pas un <button> qui exigerait du JS).
     *   - Le title reprend le libellé traduit pour les lecteurs d'écran.
     *}
    <a href="{$webmailUrl|escape}"
       target="_blank"
       rel="noopener noreferrer"
       class="sm-webmail-btn"
       title="{$lang.btn_open_webmail|escape}">
      <i class="fa fa-external-link"></i> {$lang.btn_open_webmail}
    </a>

  </div>

  {* ── Informations du service ────────────────────────────────────────── *}
  <div class="col-md-5 col-sm-12">
    <div class="sm-card">
      <div class="sm-card-header"><i class="fa fa-info-circle"></i> {$lang.dash_service_title}</div>
      <div class="sm-card-body">
        <div class="sm-info-row">
          <div class="sm-info-label">{$lang.svc_status}</div>
          <div class="sm-info-value"><span class="sm-badge {$svcStatusClass|escape}">{$svcStatusLabel|escape}</span></div>
        </div>
        <div class="sm-info-row">
          <div class="sm-info-label">{$lang.svc_reg_date}</div>
          <div class="sm-info-value">{$regDate|default:$lang.na}</div>
        </div>
        <div class="sm-info-row">
          <div class="sm-info-label">{$lang.svc_amount}</div>
          <div class="sm-info-value">
            {if $totalEstimated > 0}
              <strong>${$totalEstimated|number_format:2}{$lang.per_month}</strong>
              {* Bouton (i) — ouvre le popup de détail de facturation *}
              <button type="button"
                      class="sm-billing-detail-btn"
                      onclick="smOpen('sm-billing-detail-modal')"
                      title="{$lang.proto_billing_detail_title}">
                <i class="fa fa-info-circle"></i>
              </button>
              {if $totalProtoCost > 0}
              <div style="font-size:11px;color:#aaa;margin-top:2px;line-height:1.5;">
                Stockage : ${$estimatedPrice|number_format:2}
                + Protocoles : ${$totalProtoCost|number_format:2}
              </div>
              {/if}
            {elseif $svcAmount > 0}
              ${$svcAmount|number_format:2}{$lang.per_month}
            {else}
              {$lang.na}
            {/if}
          </div>
        </div>
        <div class="sm-info-row">
          <div class="sm-info-label">{$lang.svc_cycle}</div>
          <div class="sm-info-value">{$billingCycle|default:$lang.na}</div>
        </div>
        <div class="sm-info-row">
          <div class="sm-info-label">{$lang.svc_due_date}</div>
          <div class="sm-info-value">{if $nextDueDate}{$nextDueDate}{else}{$lang.na}{/if}</div>
        </div>
        <div class="sm-info-row">
          <div class="sm-info-label">{$lang.svc_payment}</div>
          <div class="sm-info-value">{$paymentMethod|default:$lang.na}</div>
        </div>
      </div>
    </div>
  </div>

</div>

{* ══ Enregistrements DNS ═════════════════════════════════════════════════════ *}
{*
  Le bloc "Enregistrements DNS" est toujours affiché dès qu'un mécanisme SPF
  est configuré OU que SmarterMail peut gérer le DKIM du domaine.
  — La section SPF s'affiche seulement si $spfMechanism est défini.
  — La section DKIM est TOUJOURS présente (même sans clé générée) : le client
    peut activer la génération DKIM via le toggle même si aucune clé n'existe.
  — Un lien discret en pied de rangée ouvre le guide DNS dans une modale.
*}
{if $spfMechanism || true}{* DKIM toujours présent, SPF conditionnel *}
{*
  ── Carte DNS rétractable ──────────────────────────────────────────────────
  Ouverte automatiquement si SPF ou DKIM a un problème.
  Fermée automatiquement si les deux sont valides (tout est bon, pas besoin
  d'attirer l'attention).
  Les pills SPF / DKIM dans la barre de titre donnent l'état au premier coup
  d'œil même quand la carte est repliée.
*}
{* Calcul de l'état global pour décider si la carte doit être ouverte ─── *}
{assign var="spfOk"   value=$spfValid}
{assign var="dkimOk"  value=($dkim.status eq 'active')}
{assign var="dnsAllOk" value=($spfOk && $dkimOk)}
<div class="sm-card">
  <div class="sm-dns-card-header" onclick="smToggleDnsCard()" id="sm-dns-card-hdr">

    {* ── Gauche : fa-globe + pills SPF/DKIM ───────────── *}
    <div class="sm-dns-card-left">
      <i class="fa fa-globe" style="color:#666;"></i>
      <div class="sm-dns-card-pills">
        {* Pill SPF *}
        {if $spfMechanism}
          {if $spfValid}
            <span class="sm-dns-pill ok"><i class="fa fa-check"></i> SPF</span>
          {else}
            <span class="sm-dns-pill err"><i class="fa fa-times"></i> SPF</span>
          {/if}
        {/if}
        {* Pill DKIM *}
        {if $dkim.status eq 'active'}
          <span class="sm-dns-pill ok"><i class="fa fa-check"></i> DKIM</span>
        {elseif $dkim.status eq 'standby'}
          <span class="sm-dns-pill warn"><i class="fa fa-clock-o"></i> DKIM</span>
        {elseif $dkim}
          <span class="sm-dns-pill err"><i class="fa fa-times"></i> DKIM</span>
        {else}
          <span class="sm-dns-pill na"><i class="fa fa-minus"></i> DKIM</span>
        {/if}
      </div>
    </div>

    {* ── Droite : titre + icône toggle ─────────────────── *}
    <div class="sm-dns-card-right">
      {$lang.dash_dns_title}
      <i class="fa fa-chevron-down sm-dns-card-toggle{if !$dnsAllOk} open{/if}" id="sm-dns-toggle-icon"></i>
    </div>

  </div>
  <div class="sm-dns-card-body{if $dnsAllOk} collapsed{/if}" id="sm-dns-card-body" style="max-height:{if $dnsAllOk}0{else}9999px{/if};">

    {* ── Rangée SPF + DKIM ─────────────────────────────────────────────── *}
    <div style="display:flex;flex-wrap:wrap;">

      {* ── SPF (gauche) — conditionnel : affiché seulement si configuré ── *}
      {if $spfMechanism}
      <div style="flex:1;min-width:220px;padding:14px 16px;border-right:1px solid #f0f0f0;">
        <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
          <i class="fa fa-shield"></i> SPF
        </div>
        {* Badge coloré selon le statut DNS — bouton en couleur neutre *}
        {if $spfValid}
          <span class="sm-dns-ok"><i class="fa fa-check-circle"></i> {$lang.dash_spf_ok}</span>
          <button type="button" class="sm-dns-btn" onclick="smOpen('sm-spf-modal')" style="margin-left:8px;">
            <i class="fa fa-eye"></i> {$lang.dash_spf_view}
          </button>
        {else}
          <span class="sm-dns-err"><i class="fa fa-times-circle"></i> {$lang.dash_spf_err}</span>
          <button type="button" class="sm-dns-btn" onclick="smOpen('sm-spf-modal')" style="margin-left:8px;">
            <i class="fa fa-plus-circle"></i> {$lang.dash_spf_add}
          </button>
        {/if}
        <div style="font-size:11px;color:#bbb;margin-top:6px;">
          {$lang.spf_mechanism_label} : <code style="font-size:11px;">{$spfMechanism|escape}</code>
        </div>
      </div>
      {/if}

      {* ── DKIM (droite) — TOUJOURS présent ──────────────────────────────── *}
              {*
          Quatre états du DKIM, déterminés côté PHP via enableDkimSigningDomainAdmin
          et domainKeysSettings.isActive :
            A) $dkim vide         → aucune clé générée     → toggle "Activer"
            B) status='disabled'  → clé présente, signage éteint → toggle "Activer"
            C) status='standby'   → signage activé, DNS en attente → badge orange + records
            D) status='active'    → DNS validé, pleinement actif → badge vert
        *}
        <div style="flex:1;min-width:220px;padding:14px 16px;">

          {* Titre de la section *}
          <div style="font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
            <i class="fa fa-key"></i> DKIM
          </div>

          {if $dkim}

            {* ── État D : actif + DNS validé par SmarterMail ────────────── *}
            {* Badge coloré seulement — bouton "Voir DNS" en couleur neutre  *}
            {if $dkim.status eq 'active'}
              <span class="sm-dns-ok"><i class="fa fa-check-circle"></i> {$lang.dkim_status_active}</span>
              <button type="button" class="sm-dns-btn" onclick="smOpen('sm-dkim-modal')" style="margin-left:8px;">
                <i class="fa fa-eye"></i> {$lang.dash_dkim_view}
              </button>

            {* ── État C : standby — signage activé, DNS pas encore validé par SM *}
            {* Badge orange seulement — description et bouton en couleur normale  *}
            {elseif $dkim.status eq 'standby'}
              <span class="sm-dns-standby"><i class="fa fa-clock-o"></i> {$lang.dkim_status_standby}</span>
              <button type="button" class="sm-dns-btn" onclick="smOpen('sm-dkim-modal')" style="margin-left:8px;">
                <i class="fa fa-eye"></i> {$lang.dash_dkim_view}
              </button>
              {* Texte d'explication en couleur régulière — seul le badge est orange *}
              <div style="font-size:11px;color:#555;margin-top:6px;line-height:1.5;">
                {$lang.dkim_status_standby_desc}
              </div>

            {* ── État B : désactivé — clé présente mais signature éteinte ── *}
            {* Bouton "Voir DNS" reste visible pour que le client voie sa clé *}
            {else}
              <span class="sm-dns-err"><i class="fa fa-ban"></i> {$lang.dkim_status_disabled}</span>
              <button type="button" class="sm-dns-btn" onclick="smOpen('sm-dkim-modal')" style="margin-left:8px;">
                <i class="fa fa-eye"></i> {$lang.dash_dkim_view}
              </button>
            {/if}

            {* Sélecteur — info complémentaire, couleur subtile *}
            <div style="font-size:11px;color:#bbb;margin-top:6px;">
              {$lang.dkim_selector_label} : <code style="font-size:11px;">{$dkim.selector|escape}</code>
            </div>

          {else}
            {* ── État A : aucune clé générée ─────────────────────────────── *}
            <span class="sm-dns-na" style="font-size:12px;">
              <i class="fa fa-minus-circle"></i> {$lang.dkim_toggle_disabled_badge}
            </span>
          {/if}

          {* ── Toggle DKIM enable / disable — présent dans tous les états ─── *}
          {*
            SÉCURITÉ :
              - Formulaire POST — non déclenchable par simple lien cliquable
              - dkim_action validé en PHP ('enable'/'disable' uniquement)
              - serviceid lié à la session WHMCS du client authentifié
              - Confirmation JS avant soumission (évite les clics accidentels)
            Le toggle est VERT si $dkim.enabled = true (états C et D).
            Il est GRIS dans les états A et B.
          *}
          <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
            <form method="post"
                  action="clientarea.php?action=productdetails&id={$serviceid|intval}&customAction=toggledkim"
                  style="display:inline-flex;align-items:center;gap:8px;"
                  onsubmit="return smConfirmDkim(this)">

              <input type="hidden" name="dkim_action"
                     value="{if $dkim && $dkim.enabled}disable{else}enable{/if}">

              <button type="submit"
                      class="sm-toggle-btn{if $dkim && $dkim.enabled} active{/if}"
                      data-confirm="{if $dkim && $dkim.enabled}{$lang.dkim_toggle_confirm_disable|escape}{else}{$lang.dkim_toggle_confirm_enable|escape}{/if}"
                      title="{if $dkim && $dkim.enabled}{$lang.dkim_toggle_disable|escape}{else}{$lang.dkim_toggle_enable|escape}{/if}">
                <span class="sm-toggle-knob"></span>
              </button>

              <span style="font-size:12px;color:#666;">
                {if $dkim && $dkim.enabled}{$lang.dkim_toggle_disable}{else}{$lang.dkim_toggle_enable}{/if}
              </span>

            </form>
          </div>

        </div>{* /DKIM *}

    </div>{* /rangée SPF+DKIM *}

    {* ── Lien vers le guide de configuration DNS ────────────────────────── *}
    {*
      Un lien discret en pied de la carte, qui ouvre le guide à onglets
      dans une modale (popup) avec hauteur maximale 80% de l'écran.
      Pas de carte séparée — l'interface reste compacte.
    *}
    <div style="padding:8px 16px 10px;border-top:1px solid #f5f5f5;">
      <button type="button"
              class="sm-dns-guide-link"
              onclick="smOpen('sm-dns-guide-modal')">
        <i class="fa fa-book"></i>
        {$lang.dns_guide_card_title}
        <i class="fa fa-angle-right" style="margin-left:4px;font-size:11px;"></i>
      </button>
    </div>

  </div>{* /sm-dns-card-body *}
</div>
{/if}

{* ══ Section : Alias de domaine ═════════════════════════════════════════════ *}
{*
  Affichée UNIQUEMENT si configoption17 > 0 (fonctionnalité activée).
  Positionnée sous le bloc des enregistrements DNS.

  CLASSES RÉUTILISÉES DU MODULE :
    - .sm-card / .sm-card-header / .sm-card-body  → structure carte
    - .sm-mbox / .sm-mhead.dark / .sm-mbody / .sm-mfoot → structure modale
    - .sm-mlabel → label de champ
    - .sm-btn-cancel → bouton annuler
    - .sm-btn-add → bouton d'ajout (style dashed indigo)
    - .sm-btn-del → bouton de suppression (style outline rouge)

  SÉCURITÉ :
    - Les noms d'alias sont échappés via |escape pour prévenir les XSS.
    - Les formulaires utilisent POST avec le serviceid lié à la session.
    - La limite est aussi vérifiée côté PHP (pas seulement en JS/template).
    - Les modales de confirmation empêchent les clics accidentels.
*}
{if $domainAliasMax > 0}
<div class="sm-card" style="margin-top:14px;">

  {* ── En-tête : titre + [i] tooltip + compteur ────────────────────────── *}
  <div class="sm-card-header">
    <span style="display:flex;align-items:center;gap:8px;">
      <i class="fa fa-globe"></i>
      {$lang.domain_alias_title}
      {* ── Icône [i] avec tooltip cliquable ─────────────────────────────── *}
      {*
        SÉCURITÉ : Le texte du tooltip est échappé par |escape dans le template.
        Le contenu est injecté statiquement (pas de JS innerHTML dynamique).
      *}
      <span class="sm-tooltip-wrap" id="sm-da-tooltip-wrap">
        <i class="fa fa-info-circle sm-tooltip-trigger"
           onclick="smDaToggleTooltip(event)"
           aria-label="{$lang.domain_alias_title|escape}"></i>
        <span class="sm-tooltip-bubble" id="sm-da-tooltip-bubble">
          {$lang.domain_alias_tooltip}
        </span>
      </span>
    </span>
    {* Compteur : nombre actuel / limite maximale *}
    <span style="font-size:11px;color:#888;">
      {$domainAliases|@count} / {$domainAliasMax}
    </span>
  </div>

  {* ── Corps : pills des alias + bouton d'ajout ───────────────────────── *}
  <div class="sm-card-body">

    {if $domainAliases|@count > 0}
      {* ── Liste des alias sous forme de pills ──────────────────────────── *}
      {*
        SÉCURITÉ : |escape appliqué sur le nom pour prévenir les XSS.
        Le onclick utilise des guillemets échappés correctement.
      *}
      <div class="sm-da-pills">
        {foreach from=$domainAliases item=da}
          <span class="sm-da-pill">
            {$da.name|escape}
            <button type="button"
                    class="sm-da-pill-x"
                    title="{$lang.btn_delete|escape}"
                    onclick="smDaConfirmDelete('{$da.name|escape:'javascript'}')">&times;</button>
          </span>
        {/foreach}

        {* ── Bouton + Alias de domaine — style .sm-btn-add (dashed indigo) ── *}
        {*
          SÉCURITÉ : la limite est vérifiée côté PHP à la soumission du formulaire.
        *}
        {if $domainAliases|@count >= $domainAliasMax}
          <button type="button"
                  class="sm-btn-add"
                  style="opacity:.5;cursor:not-allowed;"
                  title="{$lang.domain_alias_limit_reached|escape}"
                  disabled><i class="fa fa-plus"></i> {$lang.domain_alias_add_btn}</button>
        {else}
          <button type="button"
                  class="sm-btn-add"
                  onclick="smOpen('sm-da-add-modal')"><i class="fa fa-plus"></i> {$lang.domain_alias_add_btn}</button>
        {/if}
      </div>

    {else}
      {* ── État vide : aucun alias configuré ─────────────────────────────── *}
      <p class="sm-da-empty">{$lang.domain_alias_empty}</p>

      {* Bouton d'ajout même quand la liste est vide *}
      <div style="margin-top:8px;">
        <button type="button"
                class="sm-btn-add"
                onclick="smOpen('sm-da-add-modal')"><i class="fa fa-plus"></i> {$lang.domain_alias_add_btn}</button>
      </div>
    {/if}

  </div>
</div>

{* ── Modale : Ajouter un alias de domaine ──────────────────────────────── *}
{*
  SÉCURITÉ :
    - L'action POST est dans la whitelist $allowedActions
    - Le serviceid est celui de la session WHMCS authentifiée
    - La validation côté JS est un confort UX (la vraie validation est côté PHP)
*}
<div class="sm-overlay" id="sm-da-add-modal" onclick="smBg(event,'sm-da-add-modal')">
  <div class="sm-mbox sm-mbox-sm">
    {* En-tête — .sm-mhead.dark cohérent avec le guide DNS et la modale SPF *}
    <div class="sm-mhead dark">
      <h4><i class="fa fa-plus-circle"></i> {$lang.domain_alias_add_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-da-add-modal')">&times;</button>
    </div>

    {* Formulaire d'ajout *}
    <form method="post"
          action="clientarea.php?action=productdetails&id={$serviceid|intval}&customAction=adddomainalias"
          onsubmit="return smDaValidateAdd(this)"
          id="sm-da-add-form">

      <div class="sm-mbody">
        <div class="sm-mfg">
          {* Label du champ — réutilise .sm-mlabel *}
          <label class="sm-mlabel" for="sm-da-add-input">{$lang.domain_alias_add_label}</label>

          {* Champ de saisie — style cohérent avec les inputs du module *}
          <input type="text"
                 name="domain_alias_name"
                 id="sm-da-add-input"
                 style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;box-sizing:border-box;"
                 placeholder="{$lang.domain_alias_add_placeholder|escape}"
                 maxlength="253"
                 autocomplete="off"
                 required>

          {* Texte d'aide sous le champ *}
          <div style="font-size:11px;color:#888;margin-top:4px;">{$lang.domain_alias_add_help}</div>
        </div>
      </div>

      {* Pied de modale — réutilise .sm-mfoot et .sm-btn-cancel *}
      <div class="sm-mfoot">
        <button type="button" class="sm-btn-cancel" onclick="smClose('sm-da-add-modal')">{$lang.btn_cancel}</button>
        <button type="submit" class="sm-btn-create ready">{$lang.domain_alias_add_submit}</button>
      </div>
    </form>
  </div>
</div>

{* ── Modale : Confirmation de suppression d'un alias de domaine ────────── *}
{*
  SÉCURITÉ :
    - Le nom est échappé en JS avant injection dans le DOM (textContent)
    - Le champ hidden est défini via .value (pas innerHTML)
    - L'action POST est validée côté PHP (existence + appartenance)
*}
<div class="sm-overlay" id="sm-da-del-modal" onclick="smBg(event,'sm-da-del-modal')">
  <div class="sm-mbox sm-mbox-sm">
    {* En-tête — .sm-mhead.red cohérent avec les modales de suppression *}
    <div class="sm-mhead red">
      <h4><i class="fa fa-trash"></i> {$lang.domain_alias_del_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-da-del-modal')">&times;</button>
    </div>

    {* Corps de la modale *}
    <div class="sm-mbody">
      <p style="margin:0 0 6px;font-size:13px;">{$lang.domain_alias_del_confirm}</p>

      {* Nom de l'alias mis en évidence — rempli dynamiquement par JS *}
      <div class="sm-da-del-name" id="sm-da-del-name-display"></div>

      {* Avertissement : conséquence de la suppression *}
      <div class="sm-da-del-warn">
        <i class="fa fa-exclamation-triangle"></i>
        {$lang.domain_alias_del_warning}
      </div>
    </div>

    {* Formulaire de suppression *}
    <form method="post"
          action="clientarea.php?action=productdetails&id={$serviceid|intval}&customAction=deletedomainalias"
          id="sm-da-del-form">

      <input type="hidden" name="domain_alias_name" id="sm-da-del-input" value="">

      {* Pied de modale — réutilise .sm-mfoot, .sm-btn-cancel, .sm-btn-del *}
      <div class="sm-mfoot">
        <button type="button" class="sm-btn-cancel" onclick="smClose('sm-da-del-modal')">{$lang.btn_cancel}</button>
        <button type="submit" class="sm-btn-del"><i class="fa fa-trash"></i> {$lang.domain_alias_del_submit}</button>
      </div>
    </form>
  </div>
</div>
{/if}

{* ══ Modale : Guide de configuration DNS ════════════════════════════════════ *}
{*
  Ouverte par le lien "Comment configurer vos enregistrements DNS".
  Hauteur max : 80% de la hauteur de l'écran — le contenu défile à l'intérieur.
  La barre d'onglets reste fixe en haut, le pied de page reste fixe en bas.
  Fermeture : bouton ×, clic sur l'overlay, ou touche Échap (géré en JS).
*}
{if $dkim || $spfMechanism}
<div class="sm-overlay" id="sm-dns-guide-modal" onclick="smBg(event,'sm-dns-guide-modal')">
  <div class="sm-mbox sm-mbox-guide">

    {* En-tête fixe de la modale *}
    <div class="sm-mhead dark">
      <h4><i class="fa fa-book"></i> {$lang.dns_guide_card_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-dns-guide-modal')">&times;</button>
    </div>

    {* Zone scrollable : onglets + contenu *}
    <div class="sm-dns-guide-scroll">

      {* ── Barre d'onglets — fixe (sticky) dans le scroll ──────────────── *}
      {*
        L'onglet actif par défaut est déterminé côté PHP ($domainNsDefault)
        en interrogeant les NS du domaine. Valeurs possibles :
          cpanel | plesk | clientspace | generic
      *}
      <div class="sm-dns-tabs" role="tablist">
        <button type="button"
                class="sm-dns-tab-btn{if $domainNsDefault eq 'cpanel'} active{/if}"
                id="sm-tab-btn-cpanel"
                onclick="smDnsTab('cpanel')"
                role="tab"
                aria-selected="{if $domainNsDefault eq 'cpanel'}true{else}false{/if}"
                aria-controls="sm-tab-cpanel">
          <i class="fa fa-server"></i> {$lang.dns_guide_tab_cpanel}
        </button>
        <button type="button"
                class="sm-dns-tab-btn{if $domainNsDefault eq 'plesk'} active{/if}"
                id="sm-tab-btn-plesk"
                onclick="smDnsTab('plesk')"
                role="tab"
                aria-selected="{if $domainNsDefault eq 'plesk'}true{else}false{/if}"
                aria-controls="sm-tab-plesk">
          <i class="fa fa-server"></i> {$lang.dns_guide_tab_plesk}
        </button>
        <button type="button"
                class="sm-dns-tab-btn{if $domainNsDefault eq 'clientspace'} active{/if}"
                id="sm-tab-btn-clientspace"
                onclick="smDnsTab('clientspace')"
                role="tab"
                aria-selected="{if $domainNsDefault eq 'clientspace'}true{else}false{/if}"
                aria-controls="sm-tab-clientspace">
          <i class="fa fa-user"></i> {$lang.dns_guide_tab_client}
        </button>
        <button type="button"
                class="sm-dns-tab-btn{if $domainNsDefault eq 'generic'} active{/if}"
                id="sm-tab-btn-generic"
                onclick="smDnsTab('generic')"
                role="tab"
                aria-selected="{if $domainNsDefault eq 'generic'}true{else}false{/if}"
                aria-controls="sm-tab-generic">
          <i class="fa fa-globe"></i> {$lang.dns_guide_tab_generic}
        </button>
      </div>

      {* ══ ONGLET cPanel ═══════════════════════════════════════════════ *}
      <div class="sm-dns-tab-pane{if $domainNsDefault eq 'cpanel'} active{/if}"
           id="sm-tab-cpanel" role="tabpanel" aria-labelledby="sm-tab-btn-cpanel">
        <div class="sm-dns-guide-body">
          <h3 class="sm-dns-guide-title">{$lang.dns_guide_main_title}</h3>
          <ol class="sm-dns-steps">
            <li>{$lang.dns_cpanel_step1}</li>
            <li>{$lang.dns_cpanel_step2|replace:'{$domain}':$domain}</li>
          </ol>
          {if $dkim && $dkimHost}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-key"></i> {$lang.dns_guide_dkim_section}</h4>
            <ol class="sm-dns-steps" start="3">
              <li>{$lang.dns_cpanel_dkim3}</li>
              <li>{$lang.dns_cpanel_dkim4}</li>
              <li>{$lang.dns_cpanel_dkim5} {smCopyField fieldId='cp-dkim-host' fieldValue=$dkimHost}</li>
              <li>{$lang.dns_cpanel_dkim6} {smCopyField fieldId='cp-dkim-val' fieldValue=$dkimTxtValue isTextarea=true}</li>
              <li>{$lang.dns_cpanel_dkim7}</li>
            </ol>
          </div>
          {/if}
          {if $spfMechanism}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-shield"></i> {$lang.dns_guide_spf_section}</h4>
            <ol class="sm-dns-steps" start="{if $dkim && $dkimHost}8{else}3{/if}">
              <li>{$lang.dns_cpanel_spf8}</li>
            </ol>
            <div class="sm-dns-cond-block ok">
              <strong class="sm-dns-cond-label"><i class="fa fa-check-circle" style="color:#2e7d32;"></i> {$lang.dns_guide_spf_exists}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_cpanel_spf_e1}</li>
                <li>{$lang.dns_guide_spf_add_before} {smCopyField fieldId='cp-spf-mech' fieldValue=$spfMechanism}</li>
                <li>{$lang.dns_cpanel_spf_e3}</li>
              </ul>
            </div>
            <div class="sm-dns-cond-block err">
              <strong class="sm-dns-cond-label"><i class="fa fa-times-circle" style="color:#c62828;"></i> {$lang.dns_guide_spf_missing}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_cpanel_spf_n1}</li>
                <li>{$lang.dns_cpanel_spf_n2}</li>
                <li>{$lang.dns_cpanel_spf_n3|replace:'{$domain}':$domain}</li>
                <li>{$lang.dns_cpanel_spf_n4} {smCopyField fieldId='cp-spf-rec' fieldValue=$spfRecommended}</li>
                <li>{$lang.dns_cpanel_spf_n5}</li>
              </ul>
            </div>
          </div>
          {/if}
        </div>
      </div>

      {* ══ ONGLET Plesk ════════════════════════════════════════════════ *}
      <div class="sm-dns-tab-pane{if $domainNsDefault eq 'plesk'} active{/if}"
           id="sm-tab-plesk" role="tabpanel" aria-labelledby="sm-tab-btn-plesk">
        <div class="sm-dns-guide-body">
          <h3 class="sm-dns-guide-title">{$lang.dns_guide_main_title}</h3>
          <ol class="sm-dns-steps">
            <li>{$lang.dns_plesk_step1}</li>
            <li>{$lang.dns_plesk_step2|replace:'{$domain}':$domain}</li>
            <li>{$lang.dns_plesk_step3}</li>
          </ol>
          {if $dkim && $dkimHost}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-key"></i> {$lang.dns_guide_dkim_section}</h4>
            <ol class="sm-dns-steps" start="4">
              <li>{$lang.dns_plesk_dkim4}</li>
              <li>{$lang.dns_plesk_dkim5}</li>
              <li>{$lang.dns_plesk_dkim6} {smCopyField fieldId='pl-dkim-host' fieldValue=$dkimHost}</li>
              <li>{$lang.dns_plesk_dkim7} {smCopyField fieldId='pl-dkim-val' fieldValue=$dkimTxtValue isTextarea=true}</li>
              <li>{$lang.dns_plesk_dkim8}</li>
            </ol>
          </div>
          {/if}
          {if $spfMechanism}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-shield"></i> {$lang.dns_guide_spf_section}</h4>
            <ol class="sm-dns-steps" start="{if $dkim && $dkimHost}9{else}4{/if}">
              <li>{$lang.dns_plesk_spf9}</li>
            </ol>
            <div class="sm-dns-cond-block ok">
              <strong class="sm-dns-cond-label"><i class="fa fa-check-circle" style="color:#2e7d32;"></i> {$lang.dns_guide_spf_exists}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_plesk_spf_e1}</li>
                <li>{$lang.dns_guide_spf_add_before} {smCopyField fieldId='pl-spf-mech' fieldValue=$spfMechanism}</li>
                <li>{$lang.dns_plesk_spf_e3}</li>
              </ul>
            </div>
            <div class="sm-dns-cond-block err">
              <strong class="sm-dns-cond-label"><i class="fa fa-times-circle" style="color:#c62828;"></i> {$lang.dns_guide_spf_missing}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_plesk_spf_n1}</li>
                <li>{$lang.dns_plesk_spf_n2}</li>
                <li>{$lang.dns_plesk_spf_n3}</li>
                <li>{$lang.dns_plesk_spf_n4} {smCopyField fieldId='pl-spf-rec' fieldValue=$spfRecommended}</li>
                <li>{$lang.dns_plesk_spf_n5}</li>
              </ul>
            </div>
          </div>
          {/if}
        </div>
      </div>

      {* ══ ONGLET Espace client ════════════════════════════════════════ *}
      <div class="sm-dns-tab-pane{if $domainNsDefault eq 'clientspace'} active{/if}"
           id="sm-tab-clientspace" role="tabpanel" aria-labelledby="sm-tab-btn-clientspace">
        <div class="sm-dns-guide-body">
          <h3 class="sm-dns-guide-title">{$lang.dns_guide_main_title}</h3>
          <ol class="sm-dns-steps">
            <li>{$lang.dns_client_step1}</li>
            <li>{$lang.dns_client_step2|replace:'{$domain}':$domain}</li>
            <li>{$lang.dns_client_step3}</li>
          </ol>
          {if $dkim && $dkimHost}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-key"></i> {$lang.dns_guide_dkim_section}</h4>
            <ol class="sm-dns-steps" start="4">
              <li>{$lang.dns_client_dkim4}</li>
              <li>{$lang.dns_client_dkim5}</li>
              <li>{$lang.dns_client_dkim6} {smCopyField fieldId='cl-dkim-host' fieldValue=$dkimHost}</li>
              <li>{$lang.dns_client_dkim7} {smCopyField fieldId='cl-dkim-val' fieldValue=$dkimTxtValue isTextarea=true}</li>
              <li>{$lang.dns_client_dkim8}</li>
            </ol>
          </div>
          {/if}
          {if $spfMechanism}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-shield"></i> {$lang.dns_guide_spf_section}</h4>
            <ol class="sm-dns-steps" start="{if $dkim && $dkimHost}9{else}4{/if}">
              <li>{$lang.dns_client_spf9}</li>
            </ol>
            <div class="sm-dns-cond-block ok">
              <strong class="sm-dns-cond-label"><i class="fa fa-check-circle" style="color:#2e7d32;"></i> {$lang.dns_guide_spf_exists}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_client_spf_e1}</li>
                <li>{$lang.dns_guide_spf_add_before} {smCopyField fieldId='cl-spf-mech' fieldValue=$spfMechanism}</li>
                <li>{$lang.dns_client_spf_e3}</li>
              </ul>
            </div>
            <div class="sm-dns-cond-block err">
              <strong class="sm-dns-cond-label"><i class="fa fa-times-circle" style="color:#c62828;"></i> {$lang.dns_guide_spf_missing}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_client_spf_n1}</li>
                <li>{$lang.dns_client_spf_n2}</li>
                <li>{$lang.dns_client_spf_n3|replace:'{$domain}':$domain}</li>
                <li>{$lang.dns_client_spf_n4} {smCopyField fieldId='cl-spf-rec' fieldValue=$spfRecommended}</li>
                <li>{$lang.dns_client_spf_n5}</li>
              </ul>
            </div>
          </div>
          {/if}
        </div>
      </div>

      {* ══ ONGLET Générique ════════════════════════════════════════════ *}
      <div class="sm-dns-tab-pane{if $domainNsDefault eq 'generic'} active{/if}"
           id="sm-tab-generic" role="tabpanel" aria-labelledby="sm-tab-btn-generic">
        <div class="sm-dns-guide-body">
          <h3 class="sm-dns-guide-title">{$lang.dns_guide_main_title}</h3>
          <ol class="sm-dns-steps">
            <li>{$lang.dns_generic_step1}</li>
            <li>{$lang.dns_generic_step2|replace:'{$domain}':$domain}</li>
          </ol>
          {if $dkim && $dkimHost}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-key"></i> {$lang.dns_guide_dkim_section}</h4>
            <ol class="sm-dns-steps" start="3">
              <li>
                {$lang.dns_generic_dkim3}
                <ul style="margin-top:6px;">
                  <li>{$lang.dns_generic_dkim_host} {smCopyField fieldId='ge-dkim-host' fieldValue=$dkimHost}</li>
                  <li>{$lang.dns_generic_dkim_val} {smCopyField fieldId='ge-dkim-val' fieldValue=$dkimTxtValue isTextarea=true}</li>
                  <li>{$lang.dns_generic_dkim_ttl}</li>
                </ul>
              </li>
              <li>{$lang.dns_generic_dkim4}</li>
            </ol>
          </div>
          {/if}
          {if $spfMechanism}
          <div class="sm-dns-section">
            <h4 class="sm-dns-section-title"><i class="fa fa-shield"></i> {$lang.dns_guide_spf_section}</h4>
            <ol class="sm-dns-steps" start="{if $dkim && $dkimHost}5{else}3{/if}">
              <li>{$lang.dns_generic_spf5}</li>
            </ol>
            <div class="sm-dns-cond-block ok">
              <strong class="sm-dns-cond-label"><i class="fa fa-check-circle" style="color:#2e7d32;"></i> {$lang.dns_guide_spf_exists}</strong>
              <ul class="sm-dns-steps">
                <li>{$lang.dns_generic_spf_e1}</li>
                <li>{$lang.dns_guide_spf_add_before} {smCopyField fieldId='ge-spf-mech' fieldValue=$spfMechanism}</li>
                <li>{$lang.dns_guide_spf_no_dup}</li>
                <li>{$lang.dns_generic_spf_e3}</li>
              </ul>
            </div>
            <div class="sm-dns-cond-block err">
              <strong class="sm-dns-cond-label"><i class="fa fa-times-circle" style="color:#c62828;"></i> {$lang.dns_guide_spf_missing}</strong>
              <ul class="sm-dns-steps">
                <li>
                  {$lang.dns_generic_spf_n1}
                  <ul style="margin-top:6px;">
                    <li>{$lang.dns_generic_spf_n_name|replace:'{$domain}':$domain}</li>
                    <li>{$lang.dns_generic_spf_n_val} {smCopyField fieldId='ge-spf-rec' fieldValue=$spfRecommended}</li>
                    <li>{$lang.dns_generic_spf_n_ttl}</li>
                  </ul>
                </li>
                <li>{$lang.dns_generic_spf_n4}</li>
              </ul>
            </div>
          </div>
          {/if}
          <p style="font-size:12px;color:#888;margin-top:14px;font-style:italic;">{$lang.dns_guide_spf_help}</p>
        </div>
      </div>

    </div>{* /sm-dns-guide-scroll *}

    {* Pied de page fixe — avertissement propagation DNS *}
    <div class="sm-dns-guide-footer">
      {$lang.dns_guide_footer}
    </div>

  </div>{* /sm-mbox *}
</div>{* /sm-overlay #sm-dns-guide-modal *}
{/if}

{* ── Section 2 : Table comptes courriel ─────────────────────────────── *}
<div class="sm-card">
  <div class="sm-card-header">
    <span><i class="fa fa-users"></i> {$lang.list_title} ({$userCount})</span>
    {*
     * Deux boutons d'ajout distincts pour éviter la confusion :
     *   + Courriel    → adduserpage  (boîte courriel complète avec mot de passe)
     *   + Redirection → addredirectpage (alias seul, sans boîte ni mot de passe)
     *
     * SÉCURITÉ : les deux liens utilisent des customAction validés côté PHP
     * dans le tableau $allowedActions — aucune injection possible.
     *}
    <div style="display:flex;gap:6px;">
      <a href="clientarea.php?action=productdetails&id={$serviceid}&customAction=adduserpage"
         class="btn btn-xs btn-success"><i class="fa fa-plus"></i> {$lang.list_add_email_btn}</a>
      <a href="clientarea.php?action=productdetails&id={$serviceid}&customAction=addredirectpage"
         class="btn btn-xs btn-primary" style="background:#2e7d32;border-color:#2e7d32;">
        <i class="fa fa-share"></i> {$lang.list_add_redirect_btn}
      </a>
    </div>
  </div>
  <div class="sm-card-body" style="padding-bottom:6px;">

    {if $users|@count > 0}

    <div class="sm-toolbar">
      <div class="sm-toolbar-search">
        <label for="sm-search">{$lang.list_search_ph}</label>
        <input type="text" id="sm-search" placeholder="…"
               oninput="smSearch(this.value)" autocomplete="off">
      </div>
      <div class="sm-toolbar-right">
        <label for="sm-perpage">{$lang.list_per_page}</label>
        <select id="sm-perpage" onchange="smSetPerPage(+this.value)">
          <option value="10">10</option>
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>

    <div class="sm-table-wrap">
      <div class="sm-list-header">
        {*
         * Colonne type : icône muette indiquant si la ligne est une boîte
         * courriel (fa-inbox) ou une redirection autonome (fa-share).
         * Sans texte — la légende en bas de liste explique les icônes.
         *}
        <div class="sm-col-type" title="{$lang.list_col_type|escape}"></div>
        <div class="sm-col-email">
          <button class="sm-sort-btn" id="sort-email" onclick="smSortBy('email')">
            {$lang.list_col_email} <span class="sm-sort-icon">&#x21C5;</span>
          </button>
        </div>
        <div class="sm-col-alias">
          <button class="sm-sort-btn" id="sort-alias" onclick="smSortBy('alias')">
            {$lang.list_col_alias} <span class="sm-sort-icon">&#x21C5;</span>
          </button>
        </div>
        <div class="sm-col-fwd">
          <button class="sm-sort-btn" id="sort-fwd" onclick="smSortBy('fwd')">
            {$lang.list_col_fwd} <span class="sm-sort-icon">&#x21C5;</span>
          </button>
        </div>
        <div class="sm-col-size">
          <button class="sm-sort-btn" id="sort-size" onclick="smSortBy('size')">
            {$lang.list_col_size} <span class="sm-sort-icon">&#x21C5;</span>
          </button>
        </div>
        <div class="sm-col-proto">
          {$lang.list_col_proto}
        </div>
        <div class="sm-col-action"></div>
      </div>

      <div id="sm-rows">
        {*
         * Boucle sur tous les utilisateurs + redirections autonomes
         * ($users contient les deux types depuis smartermail.php).
         *
         * _isRedirectOnly = true  → ligne de redirection autonome
         *   - icône fa-share (vert) dans sm-col-type
         *   - pas de colonne alias (n/a pour un alias sans boîte)
         *   - colonne fwd affiche les destinations
         *   - colonne taille = — (pas de stockage)
         *   - colonne proto  = — (pas de protocoles EAS/MAPI)
         *   - bouton Gérer → editredirectpage
         *
         * _isRedirectOnly = false (boîte courriel normale)
         *   - icône fa-inbox (bleu) dans sm-col-type
         *   - affichage habituel alias / fwd / taille / proto
         *   - bouton Gérer → edituserpage
         *
         * SÉCURITÉ : toutes les valeurs affichées passent par |escape
         * pour prévenir toute injection XSS, même si la source est l'API.
         *}
        {foreach $users as $user}
        <div class="sm-user-row"
             data-email="{$user.email|escape}"
             data-alias="{$user._aliasStr|escape}"
             data-fwd="{$user._forwardingStr|escape}"
             data-size="{$user._sizeGB}"
             data-search="{$user._searchStr|escape}"
             data-type="{if $user._isRedirectOnly}redirect{else}mailbox{/if}">

          {*
           * Colonne type : icône visuelle distinguant boîte et redirection.
           * - Boîte courriel : l'icône fa-inbox est un LIEN vers le webmail
           *   SmarterMail ($webmailUrl). Ouvre dans un nouvel onglet (target=_blank)
           *   avec rel="noopener noreferrer" pour la sécurité (empêche window.opener).
           *   Le title affiche un libellé traduit invitant à se connecter au webmail.
           * - Redirection : icône fa-share simple (pas de lien — pas de boîte).
           *
           * SÉCURITÉ : $webmailUrl est construit côté PHP à partir de
           * tblservers.hostname (admin-only). |escape dans l'attribut href
           * neutralise toute valeur inattendue (XSS via hostname corrompu).
           *}
          <div class="sm-col-type">
            {if $user._isRedirectOnly}
              <i class="fa fa-share sm-icon-redirect"
                 title="{$lang.list_type_redirect|escape}"></i>
            {else}
              {* Lien webmail : l'icône de boîte courriel pointe vers le webmail *}
              <a href="{$webmailUrl|escape}"
                 target="_blank"
                 rel="noopener noreferrer"
                 class="sm-webmail-link"
                 title="{$lang.list_webmail_link|escape}">
                <i class="fa fa-inbox sm-icon-mailbox"></i>
              </a>
            {/if}
          </div>

          <div class="sm-col-email">
            {$user.email|escape}
            {if $user.fullName && $user.fullName != $user.userName}
              <br><small style="color:#aaa;font-size:11px;">{$user.fullName|escape}</small>
            {/if}
          </div>

          {*
           * Colonne alias : vide pour les redirections autonomes
           * (un alias autonome EST lui-même un alias — afficher ses
           * propres alias serait récursif et sans signification).
           *}
          <div class="sm-col-alias">
            {if $user._isRedirectOnly}
              <span class="sm-empty">—</span>
            {elseif $user._aliases|@count > 0}
              {foreach $user._aliases as $aName}<span class="sm-tag">{$aName|escape}</span>{/foreach}
            {else}<span class="sm-empty">—</span>{/if}
          </div>

          {* Colonne redirection : destinations pour les deux types de lignes *}
          <div class="sm-col-fwd">
            {if $user._forwarding|@count > 0}
              {foreach $user._forwarding as $fwdAddr}
                <span class="sm-tag sm-tag-fwd"><i class="fa fa-share" style="font-size:10px;"></i> {$fwdAddr|escape}</span>
              {/foreach}
            {else}<span class="sm-empty">—</span>{/if}
          </div>

          {* Colonne taille : — pour les redirections (pas de stockage) *}
          <div class="sm-col-size" data-size="{$user._sizeGB}">
            {if $user._isRedirectOnly}
              <span class="sm-empty">—</span>
            {elseif $user._sizeGB >= 1}
              {$user._sizeGB|number_format:2} <span style="color:#bbb;font-size:10px;">Go</span>
            {elseif $user._sizeMo > 0}
              {$user._sizeMo|intval} <span style="color:#bbb;font-size:10px;">Mo</span>
            {else}
              {$user._sizeKo|intval} <span style="color:#bbb;font-size:10px;">Ko</span>
            {/if}
          </div>

          {* Colonne protocoles : — pour les redirections (pas d'EAS/MAPI) *}
          <div class="sm-col-proto">
            {if $user._isRedirectOnly}
              <span class="sm-empty">—</span>
            {else}
              {if $user._hasEas}<span class="sm-proto-badge eas"><i class="fa fa-mobile"></i> EAS</span>{/if}
              {if $user._hasMapi}<span class="sm-proto-badge mapi"><i class="fa fa-windows"></i> {$lang.stat_mapi}</span>{/if}
              {if !$user._hasEas && !$user._hasMapi}<span class="sm-empty">—</span>{/if}
            {/if}
          </div>

          {*
           * Bouton action : pointe vers editredirectpage pour les redirections,
           * vers edituserpage pour les boîtes courriel.
           * SÉCURITÉ : les paramètres GET sont encodés via |escape:'url'
           *}
          <div class="sm-col-action">
            {if $user._isRedirectOnly}
              <a href="clientarea.php?action=productdetails&id={$serviceid}&customAction=editredirectpage&aliasname={$user.userName|escape:'url'}"
                 class="btn btn-xs btn-default">
                <i class="fa fa-pencil"></i> {$lang.btn_manage}
              </a>
            {else}
              <a href="clientarea.php?action=productdetails&id={$serviceid}&customAction=edituserpage&username={$user.userName|escape:'url'}"
                 class="btn btn-xs btn-default">
                <i class="fa fa-pencil"></i> {$lang.btn_manage}
              </a>
            {/if}
          </div>

        </div>
        {/foreach}
      </div>

      <div id="sm-no-results" class="sm-no-results" style="display:none;">
        <i class="fa fa-search" style="font-size:20px;margin-bottom:8px;display:block;"></i>
        {$lang.list_no_results}
      </div>
    </div>

    <div id="sm-pagination" class="sm-pagination"></div>

    {*
     * Légende de la liste : explique la signification des deux icônes de type.
     * Affichée seulement si la liste contient au moins un élément.
     * Positionnée en bas de liste pour ne pas surcharger visuellement le haut.
     *}
    <div class="sm-list-legend">
      <span class="sm-list-legend-item">
        <i class="fa fa-inbox sm-icon-mailbox"></i> {$lang.list_type_mailbox}
      </span>
      <span class="sm-list-legend-item">
        <i class="fa fa-share sm-icon-redirect"></i> {$lang.list_type_redirect}
      </span>
    </div>

    {else}
    <div style="text-align:center;padding:30px;color:#aaa;">
      <i class="fa fa-inbox fa-2x" style="margin-bottom:10px;display:block;"></i>
      {$lang.list_empty}<br>
      <a href="clientarea.php?action=productdetails&id={$serviceid}&customAction=adduserpage"
         class="btn btn-sm btn-primary" style="margin-top:12px;">
        <i class="fa fa-plus"></i> {$lang.list_create_first}
      </a>
    </div>
    {/if}

  </div>
</div>




{* ════════ MODALE DÉTAIL DE FACTURATION ══════════════════════════════ *}
{*
  Ouverte par le bouton (i) à côté du montant dans "Information du service".
  Affiche le détail de facturation de la période courante :
    - Stockage (tranches × Go × prix)
    - EAS + MAPI combiné : liste par adresse
    - MAPI seul : liste par adresse
    - EAS seul  : liste par adresse
  Les adresses supprimées mais facturables montrent la plage d'utilisation.
*}
<div class="sm-overlay" id="sm-billing-detail-modal" onclick="smBg(event,'sm-billing-detail-modal')">
  <div class="sm-mbox" style="max-width:540px;">
    <div class="sm-mhead dark">
      <h4><i class="fa fa-calculator"></i> {$lang.proto_billing_detail_title}</h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-billing-detail-modal')">&times;</button>
    </div>
    <div class="sm-mbody">

      {* Période de facturation *}
      {if $billingPeriod.start && $billingPeriod.end}
      <div class="sm-billing-period">
        <i class="fa fa-calendar"></i>
        {$lang.proto_billing_period}
        {$billingPeriod.start|date_format:'%d %b %Y'} → {$billingPeriod.end|date_format:'%d %b %Y'}
      </div>
      {/if}

      {* ── Ligne stockage ────────────────────────────────────────────── *}
      {if $basePrice > 0}
      <div class="sm-billing-detail-section">
        <h5>{$lang.proto_billing_storage}</h5>
        <div class="sm-billing-detail-line">
          <span class="sm-bd-email">
            {$tiers} {$lang.dash_storage_tiers}{if $tiers > 1}s{/if}
            {$lang.dash_storage_of} {$gbPerTier} {$lang.dash_storage_go}
            × ${$basePrice|number_format:2}
          </span>
          <span class="sm-bd-price">${$estimatedPrice|number_format:2}{$lang.per_month}</span>
        </div>
      </div>
      {/if}

      {*
       * ── Lignes protocoles — pré-groupées en PHP ──────────────────────────────
       *
       * CORRECTION BUG : Smarty 3 ne supporte pas la syntaxe {$array[] = $value}
       * pour ajouter des éléments à un tableau dans un template. La tentative de
       * groupement ici causait un bug silencieux : les lignes protocoles n'étaient
       * jamais ajoutées aux groupes → la section restait vide même avec des données.
       *
       * Solution : le groupement est maintenant effectué en PHP (smartermail.php)
       * et les variables pré-groupées sont injectées directement dans le template :
       *   $billingCombinedLines — adresses avec EAS + MAPI tous les deux
       *   $billingMapiLines     — adresses avec MAPI seulement
       *   $billingEasLines      — adresses avec EAS seulement
       *}
      {if ($billingCombinedLines|@count > 0) || ($billingMapiLines|@count > 0) || ($billingEasLines|@count > 0)}

        {* EAS + MAPI combiné *}
        {if $billingCombinedLines|@count > 0}
        <div class="sm-billing-detail-section">
          <h5>{$lang.stat_combined}</h5>
          {* Chaque ligne : adresse | statut grace/deleted | prix *}
        {foreach $billingCombinedLines as $line}
          <div class="sm-billing-detail-line">
            <span class="sm-bd-email{if $line.status eq 'deleted'} sm-bd-deleted{/if}">
              {$line.email|escape}
            </span>
            {if $line.status eq 'grace'}
              <span class="sm-bd-range sm-bd-grace">
                <i class="fa fa-clock-o"></i> {$lang.proto_status_grace}
              </span>
            {elseif $line.status eq 'deleted' && $line.deleted_at}
              <span class="sm-bd-range">
                {$lang.proto_billing_deleted_on}
                {$line.deleted_at|date_format:'%d %b %y'}
              </span>
            {/if}
            <span class="sm-bd-price">${$line.price|number_format:2}</span>
          </div>
          {/foreach}
        </div>
        {/if}

        {* MAPI seul *}
        {if $billingMapiLines|@count > 0}
        <div class="sm-billing-detail-section">
          <h5>{$lang.stat_mapi}</h5>
          {foreach $billingMapiLines as $line}
          <div class="sm-billing-detail-line">
            <span class="sm-bd-email{if $line.status eq 'deleted'} sm-bd-deleted{/if}">
              {$line.email|escape}
            </span>
            {if $line.status eq 'grace'}
              <span class="sm-bd-range sm-bd-grace">
                <i class="fa fa-clock-o"></i> {$lang.proto_status_grace}
              </span>
            {elseif $line.status eq 'deleted' && $line.deleted_at}
              <span class="sm-bd-range">
                {$lang.proto_billing_deleted_on}
                {$line.deleted_at|date_format:'%d %b %y'}
              </span>
            {/if}
            <span class="sm-bd-price">${$line.price|number_format:2}</span>
          </div>
          {/foreach}
        </div>
        {/if}

        {* EAS seul *}
        {if $billingEasLines|@count > 0}
        <div class="sm-billing-detail-section">
          <h5>{$lang.stat_eas}</h5>
          {foreach $billingEasLines as $line}
          <div class="sm-billing-detail-line">
            <span class="sm-bd-email{if $line.status eq 'deleted'} sm-bd-deleted{/if}">
              {$line.email|escape}
            </span>
            {if $line.status eq 'grace'}
              <span class="sm-bd-range sm-bd-grace">
                <i class="fa fa-clock-o"></i> {$lang.proto_status_grace}
              </span>
            {elseif $line.status eq 'deleted' && $line.deleted_at}
              <span class="sm-bd-range">
                {$lang.proto_billing_deleted_on}
                {$line.deleted_at|date_format:'%d %b %y'}
              </span>
            {/if}
            <span class="sm-bd-price">${$line.price|number_format:2}</span>
          </div>
          {/foreach}
        </div>
        {/if}

      {elseif $easEnabled || $mapiEnabled}
        {* Protocoles actifs mais suivi pas encore en place (période initiale) *}
        <p style="font-size:12px;color:#aaa;margin-top:12px;font-style:italic;">
          {$lang.proto_billing_live_note}
        </p>
      {/if}

      {* ── Total ──────────────────────────────────────────────────────── *}
      {if $totalEstimated > 0}
      <div class="sm-billing-detail-total">
        <span>{$lang.proto_billing_total}</span>
        <span>${$totalEstimated|number_format:2}{$lang.per_month}</span>
      </div>
      {/if}

      {* Note seuil de facturation *}
      {if $lockDays >= 1}
      <p style="font-size:11px;color:#bbb;margin-top:12px;margin-bottom:0;line-height:1.5;">
        <i class="fa fa-info-circle"></i>
        {$lang.proto_billing_threshold_note|replace:'{days}':$lockDays}
      </p>
      {/if}

    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-billing-detail-modal')">{$lang.btn_close}</button>
    </div>
  </div>
</div>

{* ════════ MODALES DNS ════════════════════════════════════════════════ *}

{* ── Modal DKIM ─────────────────────────────────────────────────────── *}
{* Accessible depuis les états B (désactivé), C (standby) et D (actif). *}
{* L'en-tête change de couleur selon l'état DKIM :                       *}
{*   active   → vert  | standby  → orange | disabled/autre → sombre      *}
{if $dkim}
<div class="sm-overlay" id="sm-dkim-modal" onclick="smBg(event,'sm-dkim-modal')">
  <div class="sm-mbox">
    <div class="sm-mhead dark">
      <h4>
        <i class="fa fa-key"></i>
        {$lang.dkim_modal_title} — {$domain}
      </h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-dkim-modal')">&times;</button>
    </div>
    <div class="sm-mbody">

      {* ── Message de statut — seulement si pertinent (pas en état actif) *}
      {* L'état actif est déjà communiqué par l'en-tête vert — pas besoin  *}
      {* d'un bandeau supplémentaire. L'état standby mérite une explication *}
      {* car le client doit agir (ajouter le record DNS).                   *}
      {if $dkim.status eq 'standby'}
      <p style="font-size:13px;color:#555;margin-bottom:14px;">
        {$lang.dkim_status_standby_desc}
      </p>
      {elseif $dkim.status eq 'disabled'}
      <p style="font-size:13px;color:#555;margin-bottom:14px;">
        {$lang.dkim_modal_desc_add}
      </p>
      {/if}

      {* ── Enregistrement hôte (Host / Name) ──────────────────────────── *}
      {if $dkim.selector}
      <div class="sm-mfg">
        <label class="sm-mlabel">{$lang.dkim_lbl_host}</label>
        <div class="sm-record-wrap">
          <input type="text" id="dkim-host" readonly
                 value="{$dkim.selector|escape}._domainkey.{$domain|escape}">
          <button type="button" class="sm-copy-btn" onclick="smCopy('dkim-host',this)" title="{$lang.btn_copy}">
            <i class="fa fa-copy"></i>
          </button>
        </div>
      </div>
      {/if}

      {* ── Valeur TXT (clé publique DKIM) ─────────────────────────────── *}
      <div class="sm-mfg">
        <label class="sm-mlabel">{$lang.dkim_lbl_value}</label>
        <div class="sm-record-wrap">
          <textarea id="dkim-value" readonly rows="4">{if $dkim.txtRecord}{$dkim.txtRecord|escape}{elseif $dkim.publicKey}v=DKIM1; k=rsa; p={$dkim.publicKey|escape}{/if}</textarea>
          <button type="button" class="sm-copy-btn" onclick="smCopy('dkim-value',this)" title="{$lang.btn_copy}">
            <i class="fa fa-copy"></i>
          </button>
        </div>
      </div>

      {* ── Note d'information (Type TXT, TTL, propagation) ────────────── *}
      {* Affichée seulement quand la clé est en place — inutile si désactivé *}
      {if $dkim.status eq 'active' || $dkim.status eq 'standby'}
      <div class="sm-info-alert">
        <i class="fa fa-info-circle"></i>
        {$lang.dkim_info}
      </div>
      {/if}

    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-dkim-modal')">{$lang.btn_close}</button>
    </div>
  </div>
</div>
{/if}

{* ── Modal SPF ──────────────────────────────────────────────────────── *}
{* Même structure que la modale DKIM :                                   *}
{*   En-tête coloré (vert=ok, rouge=manquant) — corps en couleur neutre  *}
{*   Enregistrements copiables + note de propagation en bas              *}
{if $spfMechanism}
<div class="sm-overlay" id="sm-spf-modal" onclick="smBg(event,'sm-spf-modal')">
  <div class="sm-mbox sm-mbox-sm">
    <div class="sm-mhead dark">
      <h4>
        <i class="fa fa-shield"></i>
        {$lang.spf_modal_title} — {$domain}
      </h4>
      <button type="button" class="sm-mclose" onclick="smClose('sm-spf-modal')">&times;</button>
    </div>
    <div class="sm-mbody">

      {if $spfValid}
        {* ── SPF configuré : afficher le record actuel ───────────────── *}
        <p style="font-size:13px;color:#555;margin-bottom:14px;">
          {$lang.spf_valid_desc}
        </p>
        <div class="sm-mfg">
          <label class="sm-mlabel">{$lang.spf_current_label}</label>
          <div class="sm-record-wrap">
            <input type="text" id="spf-found" readonly value="{$spfFound|escape}">
            <button type="button" class="sm-copy-btn" onclick="smCopy('spf-found',this)" title="{$lang.btn_copy}">
              <i class="fa fa-copy"></i>
            </button>
          </div>
        </div>

      {else}
        {* ── SPF manquant ou incomplet : afficher le record à ajouter ── *}
        <p style="font-size:13px;color:#555;margin-bottom:14px;">
          {$lang.spf_add_desc}
        </p>
        <div class="sm-mfg">
          <label class="sm-mlabel">{$lang.spf_txt_label}</label>
          <div class="sm-record-wrap">
            <input type="text" id="spf-recommended" readonly value="{$spfRecommended|escape}">
            <button type="button" class="sm-copy-btn" onclick="smCopy('spf-recommended',this)" title="{$lang.btn_copy}">
              <i class="fa fa-copy"></i>
            </button>
          </div>
        </div>
        {* Avertissement : afficher le record existant seulement si présent *}
        {if $spfFound}
        <div class="sm-warn-alert">
          <i class="fa fa-exclamation-triangle"></i>
          {$lang.spf_existing_warn}
          <br><code style="font-size:11px;display:block;margin-top:4px;">{$spfFound|escape}</code>
        </div>
        {/if}
      {/if}

      {* ── Note de propagation DNS (toujours visible) ──────────────────── *}
      <div class="sm-info-alert" style="margin-top:12px;">
        <i class="fa fa-info-circle"></i>
        {$lang.spf_propagation}
      </div>

    </div>
    <div class="sm-mfoot">
      <button type="button" class="btn btn-default btn-sm" onclick="smClose('sm-spf-modal')">{$lang.btn_close}</button>
    </div>
  </div>
</div>
{/if}


<script>
{literal}
// ── Liste / pagination ────────────────────────────────────────────────
var smAllRows=[], smPage=1, smPerPage=25, smSortField='email', smSortDir=1, smQuery='';

function smInit(){
  smAllRows=Array.from(document.querySelectorAll('#sm-rows .sm-user-row'));
  smUpdate();
}

function smSearch(q){ smQuery=q.toLowerCase().trim(); smPage=1; smUpdate(); }
function smSetPerPage(n){ smPerPage=n; smPage=1; smUpdate(); }
function smSortBy(f){ if(smSortField===f){smSortDir*=-1;}else{smSortField=f;smSortDir=1;} smUpdate(); }
function smGoPage(p){ smPage=p; smUpdate(); }

function smUpdate(){
  var container=document.getElementById('sm-rows');
  if(!container)return;

  var filtered=smAllRows.filter(function(r){
    return !smQuery||(r.dataset.search||'').indexOf(smQuery)!==-1;
  });

  filtered.sort(function(a,b){
    // Tri numérique pour la colonne taille (data-size est en Go)
    if (smSortField === 'size') {
      var na = parseFloat(a.dataset.size || '0');
      var nb = parseFloat(b.dataset.size || '0');
      return (na - nb) * smSortDir;
    }
    var va=(a.dataset[smSortField]||'').toLowerCase();
    var vb=(b.dataset[smSortField]||'').toLowerCase();
    return va<vb?-smSortDir:va>vb?smSortDir:0;
  });

  filtered.forEach(function(r){container.appendChild(r);});

  var total=filtered.length;
  var pages=Math.max(1,Math.ceil(total/smPerPage));
  smPage=Math.min(Math.max(1,smPage),pages);
  var start=(smPage-1)*smPerPage, end=start+smPerPage;

  smAllRows.forEach(function(r){r.style.display='none';});
  filtered.forEach(function(r,i){r.style.display=(i>=start&&i<end)?'':'none';});

  var nr=document.getElementById('sm-no-results');
  if(nr)nr.style.display=total===0?'block':'none';

  ['email','alias','fwd','size'].forEach(function(f){
    var btn=document.getElementById('sort-'+f);
    if(!btn)return;
    var icon=btn.querySelector('.sm-sort-icon');
    btn.classList.toggle('active',f===smSortField);
    icon.innerHTML=f===smSortField?(smSortDir===1?'&#x25B2;':'&#x25BC;'):'&#x21C5;';
  });

  var pagEl=document.getElementById('sm-pagination');
  if(!pagEl)return;
  if(pages<=1){pagEl.innerHTML='';return;}
  var h='';
  h+='<button class="sm-page-btn" onclick="smGoPage('+(smPage-1)+')"'+(smPage===1?' disabled':'')+'>&#x2039;</button>';
  for(var i=1;i<=pages;i++){
    if(i===1||i===pages||Math.abs(i-smPage)<=2){
      h+='<button class="sm-page-btn'+(i===smPage?' active':'')+'\" onclick="smGoPage('+i+')">'+i+'</button>';
    }else if(Math.abs(i-smPage)===3){
      h+='<span class="sm-page-ellipsis">&#x2026;</span>';
    }
  }
  h+='<button class="sm-page-btn" onclick="smGoPage('+(smPage+1)+')"'+(smPage===pages?' disabled':'')+'>&#x203A;</button>';
  pagEl.innerHTML=h;
}

document.addEventListener('DOMContentLoaded', smInit);

// ── Carte DNS rétractable ──────────────────────────────────────────────
function smToggleDnsCard(){
  var body = document.getElementById('sm-dns-card-body');
  var icon = document.getElementById('sm-dns-toggle-icon');
  if (!body) return;
  var isOpen = !body.classList.contains('collapsed');
  if (isOpen) {
    body.classList.add('collapsed');
    body.style.maxHeight = '0';
    if (icon) icon.classList.remove('open');
  } else {
    body.classList.remove('collapsed');
    body.style.maxHeight = '9999px';
    if (icon) icon.classList.add('open');
  }
}

// ── Modales ───────────────────────────────────────────────────────────
function smOpen(id){
  document.getElementById(id).classList.add('open');
  document.body.style.overflow='hidden';
}
function smClose(id){
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow='';
}
function smBg(e,id){
  if(e.target===document.getElementById(id)) smClose(id);
}
// Fermeture à la touche Échap — fonctionne pour toutes les modales (DKIM, SPF, guide DNS)
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'||e.keyCode===27){
    document.querySelectorAll('.sm-overlay.open').forEach(function(el){
      smClose(el.id);
    });
  }
});

// ── Copie ─────────────────────────────────────────────────────────────
function smCopy(id,btn){
  var el=document.getElementById(id);
  el.select(); el.setSelectionRange(0,99999);
  try{
    document.execCommand('copy');
    var o=btn.innerHTML;
    btn.innerHTML='<i class="fa fa-check"></i>';
    btn.style.background='#e8f5e9';
    setTimeout(function(){btn.innerHTML=o;btn.style.background='';},1500);
  }catch(e){}
}

// ── Guide DNS — Changement d'onglet ───────────────────────────────────
// Active l'onglet sélectionné et cache les autres.
// Appelé par onclick="smDnsTab('cpanel')" etc.
function smDnsTab(name){
  // Retirer la classe active de tous les boutons et panneaux
  var allBtns  = document.querySelectorAll('.sm-dns-tab-btn');
  var allPanes = document.querySelectorAll('.sm-dns-tab-pane');
  allBtns.forEach(function(b){
    b.classList.remove('active');
    b.setAttribute('aria-selected','false');
  });
  allPanes.forEach(function(p){p.classList.remove('active');});

  // Activer le bouton et le panneau cibles
  var btn  = document.getElementById('sm-tab-btn-'  + name);
  var pane = document.getElementById('sm-tab-' + name);
  if(btn)  { btn.classList.add('active');  btn.setAttribute('aria-selected','true'); }
  if(pane) { pane.classList.add('active'); }
}

// ── DKIM Toggle — Confirmation avant soumission ───────────────────────
// Évite les clics accidentels en demandant une confirmation.
// Le texte de confirmation est porté par data-confirm sur le bouton.
function smConfirmDkim(form){
  var btn = form.querySelector('button[data-confirm]');
  var msg = btn ? btn.getAttribute('data-confirm') : '';
  // Si le navigateur ne supporte pas confirm() (mode test), on laisse passer
  if(!msg) return true;
  return window.confirm(msg);
}

// ═══════════════════════════════════════════════════════════════════════════
//  ALIAS DE DOMAINE — Fonctions JavaScript
// ═══════════════════════════════════════════════════════════════════════════
//
//  smDaValidateAdd(form) :
//    Validation côté client du formulaire d'ajout d'un alias de domaine.
//    Vérifie le format du nom de domaine (regex basique) avant soumission.
//    NOTE : Cette validation est un confort UX — la vraie validation
//    (regex stricte, limite, doublon, identique au primaire) est côté PHP.
//
//  smDaConfirmDelete(name) :
//    Ouvre la modale de confirmation de suppression en remplissant
//    dynamiquement le nom de l'alias dans le texte et le champ hidden.
//    Utilise textContent (pas innerHTML) pour prévenir les injections XSS.
//
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Valide le formulaire d'ajout d'un alias de domaine avant soumission.
 *
 * SÉCURITÉ : Cette validation est purement côté client (confort UX).
 * La vraie validation (regex stricte, limite, doublon, etc.) se fait
 * côté PHP dans smartermail_adddomainalias(). Un utilisateur malveillant
 * peut contourner cette validation JS — le serveur la bloquera.
 *
 * @param {HTMLFormElement} form Le formulaire à valider
 * @return {boolean} true pour soumettre, false pour bloquer
 */
function smDaValidateAdd(form){
  // Récupérer la valeur du champ, nettoyer et normaliser en minuscules
  var input = form.querySelector('input[name="domain_alias_name"]');
  var val   = (input.value || '').trim().toLowerCase();

  // Réécrire la valeur nettoyée dans le champ (le serveur recevra la version propre)
  input.value = val;

  // Regex de validation basique côté client :
  //   - Au moins un point (domaine.tld)
  //   - Lettres, chiffres, tirets et points uniquement
  //   - Pas de double point
  //   - Longueur minimale de 4 caractères (a.bc)
  // Note : La regex côté PHP est plus stricte (vérifie aussi les labels individuels)
  if(val.length < 4 || val.length > 253 || !/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/.test(val)){
    // Mettre le focus sur le champ et marquer visuellement l'erreur
    input.style.borderColor = '#c62828';
    input.focus();
    return false;
  }

  // Réinitialiser la bordure si la validation passe
  input.style.borderColor = '';
  return true;
}

/**
 * Ouvre la modale de confirmation de suppression d'un alias de domaine.
 *
 * Remplit dynamiquement :
 *   - Le nom affiché dans la modale (#sm-da-del-name-display)
 *   - Le champ hidden du formulaire (#sm-da-del-input)
 *
 * SÉCURITÉ :
 *   - textContent est utilisé (pas innerHTML) pour empêcher l'injection XSS.
 *   - .value est utilisé pour le champ hidden (pas d'injection DOM).
 *   - Le nom est déjà échappé par Smarty dans le onclick du pill,
 *     mais on utilise quand même textContent par défense en profondeur.
 *
 * @param {string} name Nom de l'alias de domaine à supprimer
 */
function smDaConfirmDelete(name){
  // Remplir le nom dans le texte de confirmation — textContent = XSS-safe
  var display = document.getElementById('sm-da-del-name-display');
  if(display) display.textContent = name;

  // Remplir le champ hidden du formulaire de suppression
  var input = document.getElementById('sm-da-del-input');
  if(input) input.value = name;

  // Ouvrir la modale de confirmation
  smOpen('sm-da-del-modal');
}

/**
 * Bascule l'affichage du tooltip [i] des alias de domaine.
 *
 * Appelé au clic sur l'icône fa-info-circle. Le tooltip s'affiche
 * au premier clic et se masque au deuxième ou au clic ailleurs.
 *
 * SÉCURITÉ : Aucune donnée utilisateur n'est injectée — le contenu
 * du tooltip est statique (rendu par Smarty côté serveur).
 *
 * @param {Event} e Événement clic — stopPropagation empêche la
 *                   fermeture immédiate par le handler document.click
 */
function smDaToggleTooltip(e){
  // Empêcher le clic de se propager au document (sinon le handler
  // de fermeture ci-dessous le fermerait immédiatement)
  e.stopPropagation();

  var bubble = document.getElementById('sm-da-tooltip-bubble');
  if(!bubble) return;

  // Basculer la visibilité
  bubble.classList.toggle('visible');
}

// ── Fermeture du tooltip au clic ailleurs sur la page ─────────────────
// Quand l'utilisateur clique n'importe où en dehors du tooltip ou de
// l'icône, on masque la bulle. Cela évite que le tooltip reste ouvert
// indéfiniment après un clic sur mobile.
document.addEventListener('click', function(e){
  var bubble = document.getElementById('sm-da-tooltip-bubble');
  var wrap   = document.getElementById('sm-da-tooltip-wrap');
  if(!bubble || !wrap) return;

  // Si le clic est à l'extérieur du conteneur tooltip, fermer
  if(!wrap.contains(e.target)){
    bubble.classList.remove('visible');
  }
});
{/literal}
</script>

{/if}
