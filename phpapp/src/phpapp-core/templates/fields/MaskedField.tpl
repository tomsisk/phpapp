<? if (isset($field->options['options'])) { ?>
<select
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	onchange="fieldChanged(this)"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inputClass) { ?>class="<?= $inputClass ?>"<? } ?>
	>
	<? if (!$field->options['required'] && !isset($field->options['options'][''])) { ?>
		<option value="">None</option>
	<? }
	foreach ($field->options['options'] as $option => $description) { ?>
		<option <? if ($object[$field->field] == $option) echo 'selected'; ?> value="<?= $option ?>"><?= htmlentities($description) ?></option>
	<? } ?>
</select>
<? } else { ?>
<input type="text"
	class="editInput<? if ($inputClass) echo ' '.$inputClass; ?>"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? }
	if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	value="<?= htmlentities($field->getMasked($object[$field->field])) ?>"
	maxlength="<?= $field->length ?>"
	onchange="fieldChanged(this)"
<? if ($field->length < 40) { ?>
	size="<?= $field->length ?>"
<? } else { ?>
	style="width:300px"
<? } ?>
	/>
<? }
