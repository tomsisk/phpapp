<h1>Import Validation Results</h1>

<form action="<?= $sectionurl ?>/import/" method="post" autocomplete="off">
	<input type="hidden" name="importfile" value="<?= $importfile ?>" />
	<input type="hidden" name="hasheader" value="<?= $hasheader ?>" />
	<? foreach ($fields as $name => $field) { ?>
		<input type="hidden" name="_fieldsource_<?= $name ?>" value="<? if ($field['match'] > -1) echo 'csv'; else echo 'manual'; ?>" />
		<? if ($field['match'] > -1) { ?>
		<input type="hidden" name="_csv_<?= $name ?>" value="<?= $field['match'] ?>" />
		<? } elseif ($prototype[$name] && $field['field']->options['editable'] && !($field['field']->options['readonly'] && $field['field']->options['default'])) { ?>
		<input type="hidden" name="<?= $name ?>" value="<?= $prototype->getPrimitiveFieldValue($name) ?>" />
		<? } ?>
	<? }
	foreach ($overrides as $oname => $oval) { ?>
		<input type="hidden" name="<?= $oname ?>" value="<?= $oval ?>" />
	<? }

	if (count($validationErrors)) { ?>
		<div class="error">
			<b>Validation Failed</b>
			<br /><br />

			One or more of the <?= htmlentities(strtolower($modeladmin->name)) ?> records failed validation.
			Please correct the errors below and click "Re-Validate Records" at the bottom
			of the page, or you may discard the records with errors and add the rest to
			the database.
			<br /><br />

			<input type="submit" value="Discard <?= $errorCount ?> Records Below, Add Remaining <?= $recordCount ?> Records to Database" />
		</div>
	<? } else { ?>
		<div class="confirm">
			<b>Validation Successful</b>
			<br /><br />
			<?= $recordCount ?> <?= htmlentities(strtolower($modeladmin->name)) ?> records were successfully validated.  Click the button below
			to add them to the database.
			<br /><br />
			<input type="submit" value="Add Records to Database" />
		</div>
	<? } ?>
</form>
<br />

<? if (count($validationErrors|@count)) { ?>
	<form action="<?= $sectionurl ?>/validateimport/" method="post" autocomplete="off" class="focusonload">
		<input type="hidden" name="importfile" value="<?= $importfile ?>" />
		<input type="hidden" name="hasheader" value="<?= $hasheader ?>" />
		<? foreach ($fields as $name => $field) { ?>
			<input type="hidden" name="_fieldsource_<?= $name ?>" value="<?= ($field['match'] > -1) ? 'csv' : 'manual' ?>" />
			<? if ($field['match'] > -1) { ?>
			<input type="hidden" name="_csv_<?= $name ?>" value="<?= $field['match'] ?>" />
			<? } elseif ($prototype[$name] && $field['field']->options['editable'] && !($field['field']->options['readonly'] && $field['field']->options['default'])) { ?>
			<input type="hidden" name="<?= $name ?>" value="<?= htmlentities($prototype[$name]) ?>" />
			<? }
		}
		foreach ($overrides as $oname => $oval) { ?>
			<input type="hidden" name="<?= $oname ?>" value="<?= $oval ?>" />
		<? } ?>

		<h2>Validation Errors</h2>
		<p><?= $errorCount ?> errors encountered<? if ($errorCount < count($validationErrors)) { ?>, <?= count($validationErrors) ?> shown<? } ?>.</p>
		<? foreach ($validationErrors as $line => $error) { ?>
			<div class="warning">
				Line <?= $line ?>:
				<i>"<?= implode('","', str_replace('"', '\"', $error['line'])) ?>"</i>
				<? if (count($error['errors']) || count($error['fieldErrors'])) { ?>
					<ul>
						<?
						if (count($error['errors']))
							foreach ($error['errors'] as $emsg) { ?>
								<li><?= $emsg ?></li>
							<? }
						if (count($error['fieldErrors']))
							foreach ($error['fieldErrors'] as $fname => $ferr) {
								$efield = $prototype->_fields[$fname];
								?>
								<li>
									<?= htmlentities($efield->name) ?>: <?= htmlentities($ferr[0]) ?><br />
									<i>Override field value:</i>
									<?
									$newname = '_override_'.$line.'_'.$fname;
									echo $modeladmin->getFieldInputHTML($error['object'], $efield, $newname);
									?>
								</li>
							<? } ?>
					</ul>
				<? } ?>
			</div>
		<? }
		if ($errorCount > count($validationErrors)) { ?>
			<p>There are too many errors to show details for all of them.  Please correct the
			errors above and re-validate the records.</p>
		<? } ?>
		<br />
		<input type="submit" value="Re-Validate Records" />
	</form>
<? }
