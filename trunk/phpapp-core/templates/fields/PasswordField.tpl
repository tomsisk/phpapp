<input type="password"
	class="editInput<? if ($inputClass) echo ' '.$inputClass; ?>"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? }
	if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	maxlength="<?= $field->length ?>"
	onchange="fieldChanged(this)"
<? if ($field->options['maxlength'] && $field->options['maxlength'] < 40) { ?>
	size="<?= $field->options['maxlength'] ?>"
<? } else { ?>
	style="width:300px"
<? } ?>
	/>
