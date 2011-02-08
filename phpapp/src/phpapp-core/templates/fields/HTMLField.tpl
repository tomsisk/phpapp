<textarea class="htmlrichtext"
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inputClass) { ?>class="<?= $inputClass ?>"<? } ?>
	<? if (isset($modeladmin->fieldOptions[$fieldRef]['style'])
		&& $modeladmin->fieldOptions[$fieldRef]['style'] == 'large') { ?>
	style="width:675px; height:700px;"
	<? } else { ?>
	style="width:550px; height:150px;"
	<? } ?>
	onchange="fieldChanged(this)"
	><?= htmlentities($object[$field->field]) ?></textarea>
