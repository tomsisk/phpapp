<h1>Import <?= htmlentities($modeladmin->pluralName) ?></h1>

<? if ($errors || $fieldErrors) { ?>
	<div class="error">
		<b>Invalid Field Values</b>
		<br />
		Please correct the errors shown below and submit again.
		<? if ($errors) { ?>
			<ul>
				<? foreach ($errors as $error ) { ?>
					<li><?= htmlentities($error) ?></li>
				<? } ?>
			</ul>
		<? } ?>
	</div>
<? } ?>

<p>Please match the database fields with the correct column from the CSV file.
If your CSV file includes a header row, some fields may have been matched
for you automatically.</p>

<form action="<?= $sectionurl ?>/validateimport/" method="post" autocomplete="off">
	<input type="hidden" name="importfile" value="<?= $importfile ?>" />
	<input type="hidden" name="hasheader" value="<?= $hasheader ?>" />
	<table cellspacing="0" cellpadding="0" border="0" class="editTable">
		<? foreach ($fields as $name => $field) { ?>
			<tr>
				<td class="editLabel" id="fieldlabel_<?= $name ?>"><?= htmlentities($field['field']->name) ?></td>
				<td class="editField" id="fieldinput_<?= $name ?>" style="width: 125px; line-height: 2em;">
					<input type="radio" name="_fieldsource_<?= $name ?>" value="csv" <? if ($field['match'] > -1) echo 'checked'; ?>/>
					From CSV:
					<? if (!$field['field']->options['readonly'] || !$field['field']->options['default']) { ?>
					<br />
					<input type="radio" id="_fieldsource_<?= $name ?>" name="_fieldsource_<?= $name ?>" value="manual" <? if ($field['match'] == -1) echo 'checked'; ?>/>
					Manual value:
					<script language="javascript">
						addFieldNotifier('<?= $name ?>', function(name, value) {
								$('_fieldsource_<?= $name ?>').checked = true;
							});
					</script>
					<? } ?>
				</td>
				<td class="editField" id="fieldinput_<?= $name ?>" style="line-height: 2em;">
					<select name="_csv_<?= $name ?>" onchange="$(this.parentNode.parentNode).select('input[type=radio]')[0].checked = true;">
						<option value="-1">Select a field</option>
						<? foreach ($header as $idx => $hfield) { ?>
						<option value="<?= $idx ?>" <? if ($field['match'] == $idx) echo 'selected'; ?>><?= htmlentities($hfield) ?> (ex: "<?= htmlentities($this->truncate($sample[$idx], 40, '...', false)) ?>")</option>
						<? } ?>
					</select>&nbsp;
					<? if (!$field['field']->options['readonly'] || !$field['field']->options['default']) { ?>
					<br />
					<?= $modeladmin->getFieldInputHTML($prototype, $field['field']) ?>
					&nbsp;
					<? } ?>
					<? if (isset($fieldErrors[$name])) { ?>
						<span class="fieldError"><?= htmlentities($fieldErrors[$name]) ?></span>
					<? } elseif ($field['required']) { ?>
						<span class="fieldInfo">This field is required</span>
					<? } ?>
				</td>
			</tr>
		<? } ?>
	</table>
	<br />
	<input type="submit" value="Validate Records" />
</form>
