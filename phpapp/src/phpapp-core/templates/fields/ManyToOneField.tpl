<?
$joinField = 'pk';
if (isset($field->options['joinField']))
	$joinField = $field->options['joinField'];
$availrelated = $modeladmin->getRelatedQuery($field, $object);
$availcount = count($availrelated);
$style = isset($modeladmin->fieldOptions[$fieldRef]['style'])
	? $modeladmin->fieldOptions[$fieldRef]['style']
	: null;
if ($style == 'radio') {
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
			/><label for="<?= $field->field ?>_id_none"><?= $modeladmin->fieldOptions[$fieldRef]['nullLabel'] ? $modeladmin->fieldOptions[$fieldRef]['nullLabel'] : 'None' ?></label><br />
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
} elseif ($style == 'searchlist') { ?>
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
		<option value="" <? if (!$object[$field->field]) echo 'selected'; ?>><?= $modeladmin->fieldOptions[$fieldRef]['nullLabel'] ? $modeladmin->fieldOptions[$fieldRef]['nullLabel'] : 'None' ?></option>
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
		<option value=""><?= isset($modeladmin->fieldOptions[$fieldRef]['nullLabel']) ? $modeladmin->fieldOptions[$fieldRef]['nullLabel'] : 'None' ?></option>
		<? foreach ($availrelated->cursor() as $related) { ?>
			<option <? if ($object[$field->field] && $object[$field->field]->$joinField == $related->$joinField) echo 'selected'; ?> value="<?= $related->$joinField ?>"><?= htmlentities($this->toString($related)) ?></option>
		<? } ?>
	</select>
<? }
if (!$inlineField) {
	$targetadmin = $modeladmin->getAdmin()->findModelAdmin($field->relationName);
	if ($targetadmin) {
		if ($targetadmin->checkPermission('CREATE') && !$popup && $modeladmin->showEditLinks) {
			$url = $targetadmin->relativeUrl('/addpopup/');
			echo '<a title="Add new '.strtolower($field->name).'" href="" onclick="popupField=\''.($fieldName ? $fieldName : $field->field).'\';popup(\''.$url.'\', 700, 700); return false;"><img src="'.$modeladmin->getAdmin()->getMediaRoot().'/images/blue_add.gif"/></a>';
		}
		if ($targetadmin->checkPermission('MODIFY') && !$popup && $modeladmin->showEditLinks) {
			$url = $targetadmin->relativeUrl('/editpopup/');
			echo '<br />';
			echo '<a title="Edit selected '.strtolower($field->name).'" href="" onclick="var val = $(this).up(\'form\').serialize(true)[\''.$fieldName.'\'];popupField=\''.($fieldName ? $fieldName : $field->field).'\';popup(\''.$url.'\'+val, 700, 700); return false;">Edit selected '.strtolower($field->name).'</a>';
		}
		echo $modeladmin->getRelatedJavascript($field, $targetadmin);
	}
}
