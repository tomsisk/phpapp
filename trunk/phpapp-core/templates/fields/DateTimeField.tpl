<input
	type="text"
	class="datetimepicker<? if ($inputClass) echo ' '.$inputClass; ?>"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	value="<?= $object[$field->field] ? htmlentities(strftime('%Y-%m-%d %H:%M', $object[$field->field])) : '' ?>"
	onchange="fieldChanged(this)"
	/>
