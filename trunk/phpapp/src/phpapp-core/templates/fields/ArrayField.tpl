<?
$options = array();
if (isset($field->options['relation'])) {
	// Load relation set
	$rq = $modeladmin->getQuery($field->options['relation']);
	if ($rq)
		$options = $rq->map('pk');
} else
	$options = $field->options['options'];
if ($modeladmin->fieldOptions[$fieldRef]['style'] == 'checkbox') {

	$ordered = isset($modeladmin->fieldOptions[$fieldRef]['ordered'])
		&& $modeladmin->fieldOptions[$fieldRef]['ordered'];

	if ($ordered) { ?>
	<ul id="<?= $fieldName ?>_checkbox_list" class="sortable boxlines" style="width: 200px;">
	<? }	

	if (count($options) == 0) {
		if ($ordered) echo '<li>';
		?>
		None available
		<?
		if ($ordered) echo '</li>';
	} else {
		if (count($options) > 10) { ?>
			<div class="relcheckboxCont">
		<? }
		$idx = 0;
		if ($ordered) {
			if ($object[$field->field])
				foreach ($object[$field->field] as $value) {
					$desc = $options[$value];
					?>
					<li><input
						id="<?= $fieldName ?>_id_<?= $idx ?>"
						class="novalidate"
						type="checkbox"
						name="<?= $fieldName ?>[]"
						value="<?= $value ?>"
						<? if ($object[$field->field] && in_array($value, $object[$field->field])) echo 'checked'; ?>
						/><label for="<?= $fieldName ?>_id_<?= $idx ?>"><?= htmlentities($this->toString($desc)) ?></label><br />
					</li>
				<?
					$idx++;
				}
			foreach ($options as $value => $desc) {
				if ($object[$field->field] && in_array($value, $object[$field->field]))
					continue;
			?>
				<li><input
					id="<?= $fieldName ?>_id_<?= $idx ?>"
					class="novalidate"
					type="checkbox"
					name="<?= $fieldName ?>[]"
					value="<?= $value ?>"
					<? if ($object[$field->field] && in_array($value, $object[$field->field])) echo 'checked'; ?>
					/><label for="<?= $fieldName ?>_id_<?= $idx ?>"><?= htmlentities($this->toString($desc)) ?></label><br />
				</li>
			<?
				$idx++;
			}
		} else {
			foreach ($options as $value => $desc) { ?>
				<input
					id="<?= $fieldName ?>_id_<?= $idx ?>"
					class="novalidate"
					type="checkbox"
					name="<?= $fieldName ?>[]"
					value="<?= $value ?>"
					<? if ($object[$field->field] && in_array($value, $object[$field->field])) echo 'checked'; ?>
					/><label for="<?= $fieldName ?>_id_<?= $idx ?>"><?= htmlentities($this->toString($desc)) ?></label><br />
			<?
				$idx++;
			}
		}
	}
	if (count($options) > 10) { ?>
		</div>
	<? } ?>
<? } elseif ($modeladmin->fieldOptions[$fieldRef]['style'] == 'multilist') { ?>
	<span id="m2mfield_<?= $fieldName ?>">
		<? foreach ($options as $value => $desc) {
			if ($object[$field->field] && in_array($value, $object[$field->field])) { ?>
			<input type="hidden" name="<?= $fieldName ?>[]" value="<?= $value ?>" />
			<? }
		} ?>
	</span>
	<table cellspacing="0" cellpadding="0">
		<tr>
			<td>
				<select
					id="m2mselected_<?= $fieldName ?>"
					multiple="1"
					onchange="fieldChanged(this)"
					ondblclick="m2m_removeSelected('<?= $fieldName ?>')"
					class="m2mselected novalidate"
					>
					<? foreach ($options as $value => $desc) {
						if ($object[$field->field] && in_array($value, $object[$field->field])) { ?>
							<option value="<?= $value ?>"><?= htmlentities($this->toString($desc)) ?></option>
						<? }
					} ?>
				</select>
			</td>
			<td>
				<input type="button" class="m2mlistbutton novalidate" value="<<" onclick="m2m_addAll('<?= $fieldName ?>')"/><br />
				<input type="button" class="m2mlistbutton novalidate" value="<" onclick="m2m_addSelected('<?= $fieldName ?>')"/><br />
				<input type="button" class="m2mlistbutton novalidate" value=">" onclick="m2m_removeSelected('<?= $fieldName ?>')"/><br />
				<input type="button" class="m2mlistbutton novalidate" value=">>" onclick="m2m_removeAll('<?= $fieldName ?>')"/>
			</td>
			<td>
				<input type="text" class="relavailsearch m2mavailsearch" id="relsearch_<?= $fieldName ?>" /><br />
				<select
					id="relavailable_<?= $fieldName ?>"
					multiple="1"
					onchange="fieldChanged(this)"
					ondblclick="m2m_addSelected('<?= $fieldName ?>')"
					class="relavailable m2mavailable novalidate"
					>
					<? foreach ($options as $value => $desc) {
						if (!$object[$field->field] || !in_array($value, $object[$field->field])) { ?>
							<option value="<?= $value ?>"><?= htmlentities($this->toString($desc)) ?></option>
						<? }
					} ?>
				</select>
			</td>
		</tr>
	</table>
<? } elseif ($modeladmin->fieldOptions[$fieldRef]['style'] == 'combolist') { ?>
	<ul class="boxlines" id="<?= $fieldName ?>_list">
		<? foreach ($options as $value => $desc) {
			if ($object[$field->field] && in_array($value, $object[$field->field])) { ?>
				<li>
					<a href="#" onclick="removeComboListRow('<?= $fieldName ?>', $(this).up('li'));return false;"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
					<input type="hidden" name="<?= $fieldName ?>[]" value="<?= $value ?>" onchange="fieldChanged(this)" />
					<input type="hidden" name="<?= $fieldName ?>_desc[]" value="<?= htmlentities($this->toString($desc)) ?>" />
					<?= htmlentities($desc) ?>
				</li>
			<? }
		} ?>
	</ul>
	<select size="1" class="novalidate" id="<?= $fieldName ?>_selector">
		<option value="">Select an item</option>
		<? foreach ($options as $value => $desc) {
			if (!$object[$field->field] || !in_array($value, $object[$field->field])) { ?>
			<option value="<?= $value ?>"><?= htmlentities($this->toString($desc)) ?></option>
			<? }
		} ?>
	</select>
	<input type="button" value="Add" onclick="addComboListRow('<?= $fieldName ?>'); return false;"/>
<? } else { ?>
	<select
		name="<?= $fieldName ?>[]"
		size="7"
		multiple="1"
		onchange="fieldChanged(this)"
		>
		<?
		if (!count($options)) { ?>
			<option value="" disabled="1">None available</option>
		<? } else {
			foreach ($options as $value => $desc) { ?>
				<option value="<?= $value ?>" <? if ($object[$field->field] && in_array($value, $object[$field->field])) echo 'selected'; ?>><?= htmlentities($this->toString($desc)) ?></option>
			<?  }
		} ?>
	</select>
<? }
