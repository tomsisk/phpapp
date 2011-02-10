<?
if (!isset($fieldName))
	$fieldName = $field->field;
if ($inlineField)
	$fieldName = '_'.$inlineField.'_'.$fieldName.'[]';
?>
<? if (isset($object[$field->field]) && $object[$field->field]) { ?>
	<div style="margin-bottom: 5px;">
	<b><?= $object[$field->field] ?></b>
	</div>
	<div style="width: 150px; border: 1px solid #999; background-color: #FFC; padding: 2px; margin-bottom: 10px;">
		<input type="checkbox" name="_remove_<?= $fieldName ?>" value="1"/>
		<small>check to remove file</small>
	</div>
<? } ?>
<input type="file"
	class="editInput<? if ($inputClass) echo ' '.$inputClass; ?>"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	name="<?= $fieldName ?>"
	value="<?= htmlentities($object[$field->field]) ?>"
	maxlength="<?= $field->length ?>"
	onchange="fieldChanged(this); document.getElementById('_imagefield_<?= $fieldName ?>img').src = this.value ? '<?= $admin->baseUrl ?>/thumb/'+this.value : '<?= $admin->mediaRoot ?>/images/spacer.gif';"
<? if ($field->length < 40) { ?>
	size="<?= $field->length ?>"
<? } else { ?>
	style="width:300px"
<? } ?>
	id="_imagefield_<?= $fieldName ?>"
	/>
