{* SmarterMail — Erreur *}
<div class="alert alert-danger">
  <i class="fa fa-exclamation-triangle"></i>
  <strong>{$lang.err_title} :</strong> {$error|escape|default:$lang.err_default}
</div>
<a href="clientarea.php?action=productdetails&id={$serviceid}" class="btn btn-default btn-sm">
  <i class="fa fa-arrow-left"></i> {$lang.back}
</a>
