<textarea class="editInput"
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inputClass) { ?>class="<?= $inputClass ?>"<? } ?>
	<? if ($inlineField) { ?>
		style="width:250px; height:75px;"
	<? } else { ?>
		<? if ($modeladmin->fieldOptions[$fieldRef]['style'] == 'large') { ?>
		style="width:650px; height:500px;"
		<? } else { ?>
		style="width:550px; height:150px;"
		<? } ?>
	<? } ?>
	onchange="fieldChanged(this)"
	><?= htmlentities($object[$field->field]) ?></textarea><br />
