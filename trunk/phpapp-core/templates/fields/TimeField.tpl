<input
	type="text"
	class="timepicker<? if ($inputClass) echo ' '.$inputClass; ?>"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	value="<?= $object[$field->field] ? htmlentities(strftime('%H:%M:%S', $object[$field->field])) : '' ?>"
	onchange="fieldChanged(this)"
	/>
