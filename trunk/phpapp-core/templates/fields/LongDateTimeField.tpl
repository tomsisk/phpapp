<? $phptime = $object[$field->field] ? $object[$field->field]/1000 : null; ?>
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
	value="<?= $phptime ? htmlentities(strftime('%Y-%m-%d %H:%M', $phptime)) : '' ?>"
	onchange="fieldChanged(this)"
	/>
