<h1><?= ($pk ? 'Edit ' : 'New ').htmlentities($modeladmin->name) ?></h1>

<span id="errorMessage">
	<? if ($errors || $fieldErrors) { ?>
		<div class="error">
		<b>Unable to Save</b>
			<br />
			Please correct the errors shown below and submit again.
			<? if ($errors) { ?>
				<ul>
					<? foreach ($errors as $error) { ?>
						<li><?= htmlentities($error) ?></li>
					<? } ?>
				</ul>
			<? } ?>
		</div>
		<br />
	<? } ?>
</span>

<?
if ($modeladmin->instructions)
	eval('?>'.$modeladmin->instructions);

if ($modeladmin->previewUrl && $object->pk) { ?>
<div style="text-align: right; padding: 3px;">
	<a target="_blank" href="<?= eval('?>'.$modeladmin->previewUrl) ?>">Preview in a new window</a>
	<a target="_blank" href="<?= eval('?>'.$modeladmin->previewUrl) ?>"><img style="vertical-align: top;" src="<?= $admin->mediaRoot ?>/images/external-small.png"/></a>
</div>
<? } ?>

<form action="<?= $sectionurl ?>/<? if ($popup) echo 'savepopup'; else echo 'save'; ?>/<?= $pk ?>" method="post" class="focusonload validated" id="<?= $module->id?>_<?=$modeladmin->id?>_form" autocomplete="off">
	<input type="hidden" name="_filterstr" value="<?= $filterstr ?>"/>
	<?
	$hidden = array();
	$idx = 1;
	foreach ($modeladmin->getFieldGroups() as $group) {
		ob_start();
		?>
		<div id="_field_row__group<?= $idx ?>"
			<? if (!$modeladmin->checkDisplayCondition($parent ? $parent : $object, '_group'.$idx)) { ?>
				class="hidden"
			<? } ?>
			>
		<?
		if ($group['name']) {
			if ($group['collapse']) { ?>
				<div class="expander">
					<h3><?= htmlentities($group['name']) ?></h3>
					<div class="content">
			<? } else { ?>
				<h3><?= htmlentities($group['name']) ?></h3>
			<? }
		}
		if (isset($group['description'])) {
			echo '<p>'.$group['description'].'</p>';
		}
		?>
		<table cellspacing="0" cellpadding="0" border="0" class="editTable">
			<?
			$displayed = false;
			foreach ($group['fields'] as $fieldname) {
				if ($modeladmin->fieldOptions[$fieldname]
						&& $modeladmin->fieldOptions[$fieldname]['hidden'])
					$hidden[] = $fieldname;
				else {
					$row = $modeladmin->getFieldRowHTML($object, $fieldname, $fieldname);
					if ($row) {
						echo $row;
						$displayed = true;
					}
				}
			}
			?>
		</table>
		<? if ($group['name'] && $group['collapse']) { ?>
				</div>
			</div>
		<? } else { ?>
			<br />
		<? } ?>
		</div>
		<?
		echo $modeladmin->getDisplayJavascript('_group'.$idx);
		$idx++;
		if ($displayed)
			ob_end_flush();
		else
			ob_end_clean();
	}

	if ($group['name'] && $group['collapse']) { ?>
		<br />
	<? } 

	if ($extraForm) {
		echo $extraForm; ?>
		<br />
	<? }

	if (count($hidden)) {
		foreach($hidden as $fn) {
			list($tf, $to, $prefix) = $modeladmin->findFieldAndObject($fn, $object);
			?>
			<input type="hidden" name="<?= $fn ?>" value="<?= $to->getPrimitiveFieldValue($tf->field) ?>"/>
		<? }
	}

	if (count($modeladmin->inlineObjects)) {
		foreach ($modeladmin->inlineObjects as $name => $options) {
			if (!$modeladmin->isFieldDisplayed($name)) { ?>
				<div id="_field_row_<?= $name ?>"
				<? if (!$modeladmin->checkDisplayCondition($object, $name)) { ?>
					class="hidden"
				<? } ?>>
				<?
				$field = $object->_fields[$name];
				if ($field->options['editable']) { ?>
					<h3><?= htmlentities($field->name) ?></h3>
					<?= $modeladmin->getFieldInputHTML($object, $name) ?>
				<? } elseif ($field->options['readonly']) { ?>
					<h3><?= htmlentities($field->name) ?></h3>
					<?= $modeladmin->getFieldValueHTML($object, $name) ?>
				<? }
				echo '</div>';
				echo $modeladmin->getDisplayJavascript($name);
			}
		} ?>
		<br />
	<? } ?>

	<input type="submit" value="Save <?= htmlentities($modeladmin->name) ?>"/>
	<input type="submit" name="_savecontinue" value="Save and Continue Editing"/>
	<? if ($modeladmin->saveAndAdd) { ?>
	<input type="submit" name="_saveadd" value="Save and Add New <?= htmlentities($modeladmin->name) ?>"/>
	<? }
	if ($modeladmin->saveNew) { ?>
	<input type="submit" name="_savenew" value="Save as New <?= htmlentities($modeladmin->name) ?>"/>
	<? } ?>
</form>
<br />

<? if (!$modeladmin->inlineOnly) { ?>
<span class="prevpage"><a href="<?= $sectionurl ?>/?<?= $filterstr ?>">Back to <?= htmlentities($modeladmin->name) ?> List</a></span>
<? } ?>

<script language="javascript">
	// Prevent session timeout while user is editing an object to avoid losing changes
	function doHeartbeat() {
		var hb = new Element('img', {'src': baseUrl+'/account/heartbeat'});
		setTimeout(doHeartbeat, 15*60*1000);
	}
	doHeartbeat();
</script>
