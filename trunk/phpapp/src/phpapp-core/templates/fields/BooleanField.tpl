<? if ($field->options['options']) { ?>
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
<input type="checkbox"
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	value="1"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inputClass) { ?>class="<?= $inputClass ?>"<? } ?>
	onchange="fieldChanged(this)"
	<? if ($object[$field->field]) echo 'checked'; ?>
	/>
<? }
