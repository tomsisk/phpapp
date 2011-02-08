<? if ($field->options['editable'] || $field->options['readonly']) {
	if ($field instanceof EmbeddedModelField) {
		$submodel = $object[$field->field];
		foreach ($submodel->_fields as $sf)
			echo $modeladmin->getFieldRowHtml($submodel, $sf, $fieldName.'.'.$sf->field, $object);
	} else { ?>
		<tr id="_field_row_<?= $fieldName ?>"
			<? if (!$modeladmin->checkDisplayCondition($parent, $fieldName)) { ?>
				class="hidden"
			<? } ?>
			>
			<td class="editLabel" id="fieldlabel_<?= $fieldName ?>">
				<?= htmlentities($field->name) ?>
			</td>
			<td class="editField" id="fieldinput_<?= $fieldName ?>">
				<?
				if (($object->pk || !isset($field->options['required']) || !$field->options['required'] || isset($field->options['default']))
						&& ((isset($field->options['readonly']) && $field->options['readonly'])
							|| (isset($modeladmin->fieldOptions[$fieldName]['readonly']) && 
								$modeladmin->fieldOptions[$fieldName]['readonly']))) {
					echo $modeladmin->getFieldValueHTML($object, $field, 'html', false, 'None');
				} elseif ($object[$field->field]
						&& isset($modeladmin->fieldOptions[$fieldName])
						&& isset($modeladmin->fieldOptions[$fieldName]['passedValue'])
						&& $modeladmin->fieldOptions[$fieldName]['passedValue']) {
					echo $modeladmin->getFieldValueHTML($object, $field, 'html', false, 'None'); ?>
					<input type="hidden" name="<?= $fieldName ?>" value="<?= $object->getPrimitiveFieldValue($field->field) ?>"/>
				<? } else {
					$inputClass = isset($fieldErrors[$fieldName]) ? 'inputError' : null;
					echo $modeladmin->getFieldInputHTML($object, $field, $fieldName, $inputClass);
					?>
					<span id="fielderror_<?= $fieldName ?>">
						<? if (isset($fieldErrors[$fieldName])) { ?>
							<span class="fieldError">
								<?= htmlentities($fieldErrors[$fieldName][0]) ?>
							</span>
						<? } ?>
					</span>
					<? if (isset($modeladmin->fieldOptions[$fieldName])
							&& isset($modeladmin->fieldOptions[$fieldName]['help'])
							&& $modeladmin->fieldOptions[$fieldName]['help']) { ?>
						<div class="fieldHelp"><?= $modeladmin->fieldOptions[$fieldName]['help'] ?></div>
					<? }
				}
				echo $modeladmin->getDisplayJavascript($fieldName);
				?>
			</td>
		</tr>
	<? }
}
