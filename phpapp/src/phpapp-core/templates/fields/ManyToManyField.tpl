HEY
<? if (!$inlineField) {

	$groupField = null;
	$groupHeadings = null;

	if (isset($modeladmin->fieldOptions[$fieldName])) {
		$groupField = null;
		if (isset($modeladmin->fieldOptions[$fieldName]['groupby']))
			$groupField = $modeladmin->fieldOptions[$fieldName]['groupby'];
		$groupHeadings = null;
		if (isset($modeladmin->fieldOptions[$fieldName]['groupheadings']))
			$groupHeadings = $modeladmin->fieldOptions[$fieldName]['groupheadings'];
	}

	$availrelated = $modeladmin->getManyToManyList($field, $object);
	if ($modeladmin->fieldOptions[$fieldRef]['style'] == 'checkbox') {
		if (count($availrelated) == 0) { ?>
			None available
		<? } else {
			if (count($availrelated) > 10) { ?>
				<div class="relcheckboxCont">
			<? }
			$idx = 0;
			$lastGroup = null;
			foreach ($availrelated as $related) {
				if ($groupField) {
					if ($lastGroup !== null && $related['object']->$groupField != $lastGroup) {
						echo '<hr />';
						if ($groupHeadings && isset($groupHeadings[$related['object']->$groupField]))
							echo '<b>'.$groupHeadings[$related['object']->$groupField].'</b><hr />';
					}
					$lastGroup = $related['object']->$groupField;
				}
				?>
				<input
					id="<?= $field->field ?>_id_<?= $idx ?>"
					class="novalidate"
					type="checkbox"
					name="<?= $field->field ?>[]"
					value="<?= $related['object']->pk ?>"
					<? if ($related['selected']) echo 'checked'; ?>
					/><label for="<?= $field->field ?>_id_<?= $idx ?>"><?= htmlentities($this->toString($related['object'])) ?></label><br />
			<?
				$idx++;
			}
		}
		if (count($availrelated) > 10) { ?>
			</div>
		<? } ?>
	<? } elseif ($modeladmin->fieldOptions[$fieldRef]['style'] == 'multilist') { ?>
		<span id="m2mfield_<?= $field->field ?>">
			<? foreach ($availrelated as $related) {
				if ($related['selected']) { ?>
				<input type="hidden" name="<?= $field->field ?>[]" value="<?= $related['object']->pk ?>" />
				<? }
			} ?>
		</span>
		<table cellspacing="0" cellpadding="0">
			<tr>
				<td>
					<select
						id="m2mselected_<?= $field->field ?>"
						multiple="1"
						onchange="fieldChanged(this)"
						ondblclick="m2m_removeSelected('<?= $field->field ?>')"
						class="m2mselected novalidate"
						>
						<? foreach ($availrelated as $related) {
							if ($related['selected']) { ?>
								<option value="<?= $related['object']->pk ?>"><?= htmlentities($this->toString($related['object'])) ?></option>
							<? }
						} ?>
					</select>
				</td>
				<td>
					<input type="button" class="m2mlistbutton novalidate" value="<<" onclick="m2m_addAll('<?= $field->field ?>')"/><br />
					<input type="button" class="m2mlistbutton novalidate" value="<" onclick="m2m_addSelected('<?= $field->field ?>')"/><br />
					<input type="button" class="m2mlistbutton novalidate" value=">" onclick="m2m_removeSelected('<?= $field->field ?>')"/><br />
					<input type="button" class="m2mlistbutton novalidate" value=">>" onclick="m2m_removeAll('<?= $field->field ?>')"/>
				</td>
				<td>
					<input type="text" class="relavailsearch m2mavailsearch" id="relsearch_<?= $field->field ?>" /><br />
					<select
						id="relavailable_<?= $field->field ?>"
						multiple="1"
						onchange="fieldChanged(this)"
						ondblclick="m2m_addSelected('<?= $field->field ?>')"
						class="relavailable m2mavailable novalidate"
						>
						<? foreach ($availrelated as $related) {
							if (!$related['selected']) { ?>
								<option value="<?= $related['object']->pk ?>"><?= htmlentities($this->toString($related['object'])) ?></option>
							<? }
						} ?>
					</select>
				</td>
			</tr>
		</table>
	<? } elseif ($modeladmin->fieldOptions[$fieldRef]['style'] == 'combolist') { ?>
		<ul class="boxlines" id="<?= $field->field ?>_list">
			<? foreach ($availrelated as $related) {
				if ($related['selected']) { ?>
					<li>
						<a href="#" onclick="removeComboListRow('<?= $field->field ?>', $(this).up('li'));return false;"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
						<input type="hidden" name="<?= $field->field ?>[]" value="<?= $related['object']->pk ?>" onchange="fieldChanged(this)" />
						<input type="hidden" name="<?= $field->field ?>_desc[]" value="<?= htmlentities($this->toString($related['object'])) ?>" />
						<?= htmlentities($this->toString($related['object'])) ?>
					</li>
				<? }
			} ?>
		</ul>
		<select size="1" class="novalidate" id="<?= $field->field ?>_selector">
			<option value="">Select an item</option>
			<? foreach ($availrelated as $related) {
				if (!$related['selected']) { ?>
				<option value="<?= $related['object']->pk ?>"><?= htmlentities($this->toString($related['object'])) ?></option>
				<? }
			} ?>
		</select>
		<input type="button" value="Add" onclick="addComboListRow('<?= $field->field ?>'); return false;"/>
	<? } else { ?>
		<select
			name="<?= $field->field ?>[]"
			size="7"
			multiple="1"
			onchange="fieldChanged(this)"
			class="novalidate"
			>
			<?
			if (!count($availrelated)) { ?>
				<option value="" disabled="1">None available</option>
			<? } else {
				foreach ($availrelated as $related) { ?>
					<option value="<?= $related['object']->pk ?>" <? if ($related['selected']) echo 'selected'; ?>><?= htmlentities($this->toString($related['object'])) ?></option>
				<?  }
			} ?>
		</select>
	<? }
}
