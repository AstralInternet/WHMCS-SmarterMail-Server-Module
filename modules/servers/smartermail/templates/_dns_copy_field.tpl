{*
 * _dns_copy_field.tpl — Champ DNS copiable (input ou textarea + bouton copier)
 *
 * Partiel réutilisé dans chaque onglet du guide DNS pour afficher une valeur
 * copiable (hôte DKIM, valeur TXT, mécanisme SPF, etc.).
 *
 * Variables attendues par le parent (via {include} avec paramètres) :
 *   $fieldId    : string — ID unique HTML de l'élément (ex: 'cp-dkim-host')
 *                 IMPORTANT : doit être unique dans la page pour que smCopy()
 *                 cible le bon élément. Chaque onglet a ses propres IDs.
 *   $fieldValue : string — Valeur à afficher et à copier (échappée par Smarty)
 *   $isTextarea : bool   — Si true, affiche un <textarea> (pour les longues valeurs)
 *                         Sinon, affiche un <input type="text"> (défaut: false)
 *
 * SÉCURITÉ : $fieldValue est toujours passé à travers le modificateur |escape
 *            de Smarty pour prévenir toute injection XSS, même si la valeur
 *            provient d'une source externe (API SmarterMail).
 *
 * Exemple d'utilisation :
 *   {include file='../smartermail/templates/_dns_copy_field.tpl'
 *            fieldId='cp-dkim-host'
 *            fieldValue=$dkimHost}
 *
 *   {include file='../smartermail/templates/_dns_copy_field.tpl'
 *            fieldId='cp-dkim-val'
 *            fieldValue=$dkimTxtValue
 *            isTextarea=true}
 *}

{* Wrapper du champ copiable — flex pour aligner le champ et le bouton copier *}
<div class="sm-dns-copy-wrap">

  {if $isTextarea}
    {*
     * Textarea pour les valeurs longues (ex: clé publique DKIM).
     * rows="3" donne une hauteur raisonnable sans trop agrandir le guide.
     * readonly empêche toute modification accidentelle.
    *}
    <textarea id="{$fieldId|escape}"
              rows="3"
              readonly
              aria-label="{$fieldId|escape}">{$fieldValue|escape}</textarea>
  {else}
    {*
     * Input texte pour les valeurs courtes (ex: hôte DKIM, mécanisme SPF).
     * readonly empêche toute modification.
    *}
    <input type="text"
           id="{$fieldId|escape}"
           readonly
           value="{$fieldValue|escape}"
           aria-label="{$fieldId|escape}">
  {/if}

  {*
   * Bouton copier — appelle smCopy() avec l'ID du champ cible.
   * L'icône passe temporairement en ✓ après la copie (via smCopy() JS).
   * type="button" est OBLIGATOIRE pour éviter la soumission d'un formulaire
   * parent si ce partiel est inclus à l'intérieur d'un <form>.
  *}
  <button type="button"
          class="sm-copy-btn"
          onclick="smCopy('{$fieldId|escape}', this)"
          title="{$lang.btn_copy|escape}">
    <i class="fa fa-copy"></i>
  </button>

</div>
