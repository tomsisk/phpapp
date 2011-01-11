<?
$joinField = 'pk';
if (isset($field->options['joinField']))
	$joinField = $field->options['joinField'];
$availrelated = $modeladmin->getRelatedQuery($field, $object);
$availcount = count($availrelated);
if ($modeladmin->fieldOptions[$fieldRef]['style'] == 'radio') {
	if ($availcount > 10) { ?>
		<div class="relcheckboxCont">
	<? } ?>
	<span>
		<input type="radio"
			id="<?= $field->field ?>_id_none"
			<? if ($inlineField) { ?>
			name="_<?=$inlineField ?>_<?=$field->field ?>[]"
			<? } elseif ($fieldName) { ?>
			name="<?= $fieldName ?>"
			<? } else { ?>
			name="<?= $field->field ?>"
			<? } ?>
			value=""
			<? if (!$object[$field->field]) echo 'checked'; ?>
			/><label for="<?= $field->field ?>_id_none"><?= $modeladmin->fieldOptions[$fieldName]['nullLabel'] ? $modeladmin->fieldOptions[$fieldName]['nullLabel'] : 'None' ?></label><br />
		<?
		$idx = 0;
		if (!$availcount) { ?>
			None available
		<? } else {
			foreach ($availrelated->cursor() as $related) { ?>
				<input type="radio"
					id="<?= $field->field ?>_id_<?= $idx ?>"
					<? if ($inlineField) { ?>
					name="_<?= $inlineField ?>_<?= $field->field ?>[]"
					<? } elseif ($fieldName) { ?>
					name="<?= $fieldName ?>"
					<? } else { ?>
					name="<?= $field->field ?>"
					<? } ?>
					value="<?= $related->$joinField ?>"
					<? if ($object->getPrimitiveFieldValue($field->field) == $related->$joinField) echo 'checked'; ?>
					/><label for="<?= $field->field ?>_id_<?= $idx ?>"><?= htmlentities($this->toString($related)) ?></label><br />
				<?
				$idx++;
			}
		} ?>
	</span>
	<? if ($availcount > 10) { ?>
		</div>
	<? }
} elseif ($modeladmin->fieldOptions[$fieldRef]['style'] == 'searchlist') { ?>
	<select
		id="relavailable_<?= $field->field ?>"
		<? if ($inlineField) { ?>
		name="_<?= $inlineField ?>_<?= $field->field ?>[]"
		<? } elseif ($fieldName) { ?>
		name="<?= $fieldName ?>"
		<? } else { ?>
		name="<?= $field->field ?>"
		<? } ?>
		size="5"
		onchange="fieldChanged(this)"
		class="relavailable<? if ($inputClass) echo ' '.$inputClass; ?>"
		<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
		style="margin-bottom: 5px;"
		>
		<option value="" <? if (!$object[$field->field]) echo 'selected'; ?>><?= $modeladmin->fieldOptions[$fieldName]['nullLabel'] ? $modeladmin->fieldOptions[$fieldName]['nullLabel'] : 'None' ?></option>
		<? foreach ($availrelated->cursor() as $related) { ?>
			<option <? if ($object[$field->field]->$joinField == $related->$joinField) echo 'selected'; ?> value="<?= $related->$joinField ?>"><?= htmlentities($this->toString($related)) ?></option>
		<? } ?>
	</select>
	<br />
	<input type="text" class="relavailsearch" id="relsearch_<?= $field->field ?>" />
<? } else { ?>
	<select
		<? if ($inlineField) { ?>
		name="_<?= $inlineField ?>_<?= $field->field ?>[]"
		<? } elseif ($fieldName) { ?>
		name="<?= $fieldName ?>"
		<? } else { ?>
		name="<?= $field->field ?>"
		<? } ?>
		size="1"
		onchange="fieldChanged(this)"
		<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
		<? if ($inputClass) { ?>class="<?= $inputClass ?>"<? } ?>
		>
		<option value=""><?= $modeladmin->fieldOptions[$fieldName]['nullLabel'] ? $modeladmin->fieldOptions[$fieldName]['nullLabel'] : 'None' ?></option>
		<? foreach ($availrelated->cursor() as $related) { ?>
			<option <? if ($object[$field->field]->$joinField == $related->$joinField) echo 'selected'; ?> value="<?= $related->$joinField ?>"><?= htmlentities($this->toString($related)) ?></option>
		<? } ?>
	</select>
<? }
if (!$inlineField) {
	echo $modeladmin->getDependencyHTML($field, $popup);
}
