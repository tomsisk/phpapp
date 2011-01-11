<h1><?= htmlentities($modeladmin->name) ?> Detail</h1>

<? if (!$modeladmin->checkPermission('MODIFY')) { ?>
	<div class="info">
		<b>View Only</b>
		<br />
		This <?= htmlentities(strtolower($modeladmin->name)) ?> cannot be edited.
	</div>
	<br />
<? }

if ($modeladmin->instructions)
	echo eval('?>'.$modeladmin->instructions);

if ($modeladmin->previewUrl && $object->pk) { ?>
<div style="text-align: right; padding: 3px;">
	<a target="_blank" href="<?= eval('?>'.$modeladmin->previewUrl) ?>">Preview in a new window</a>
	<a target="_blank" href="<?= eval('?>'.$modeladmin->previewUrl) ?>"><img style="vertical-align: top;" src="<?= $admin->mediaRoot ?>/images/external-small.png"/></a>
</div>
<? }

foreach ($modeladmin->getFieldGroups() as $group) {
	if ($group['name']) {
		if ($group['collapse']) { ?>
			<div class="expander">
				<h3><?= htmlentities($group['name']) ?></h3>
				<div class="content">
		<? } else { ?>
			<h3><?= htmlentities($group['name']) ?></h3>
		<? }
	} ?>
	<table cellspacing="0" cellpadding="0" border="0" class="editTable">
		<?
		foreach ($group['fields'] as $fieldname) {
			if (!$modeladmin->isInlineObject($fieldname)) {
				$field = $modeladmin->findField($fieldname);
				if ($field instanceof EmbeddedModelField) {
					foreach ($object->$fieldname->_fields as $n => $f) { ?>
						<tr>
							<td class="editLabel" id="fieldlabel_<?= $n ?>">
								<?= htmlentities($f->name) ?>
							</td>
							<td class="editField" id="fieldinput_<?= $n ?>">
								<?= $modeladmin->getFieldValueHTML($object->$fieldname, $n, 'html', false, '&nbsp;') ?>
							</td>
						</tr>
					<? }
				} elseif ($field->options['editable'] || $field->options['readonly']) { ?> 
					<tr>
						<td class="editLabel" id="fieldlabel_<?= $fieldname ?>">
							<?= htmlentities($field->name) ?>
						</td>
						<td class="editField" id="fieldinput_<?= $fieldname ?>">
							<?= $modeladmin->getFieldValueHTML($object, $fieldname, 'html', false, '&nbsp;') ?>
						</td>
					</tr>
				<? }
			}
		} ?>
	</table>
	<? if ($group['name'] && $group['collapse']) { ?>
			</div>
		</div>
	<? } else { ?>
		<br />
	<? }
}

if (isset($extraDetail))
	echo $extraDetail;

if (count($modeladmin->inlineObjects)) {
	foreach ($modeladmin->inlineObjects as $name => $options) {
		$ifield = $modeladmin->findField($name);
		$related = $modeladmin->getRelatedQuery($ifield, $object);
		$rprototype = $ifield->getRelationModel();
		?>
		<h3><?= htmlentities($ifield->name) ?></h3>
		<table class="listTable">
			<tr>
				<? foreach ($rprototype->_fields as $rfield) {
					if ($rfield->field != $ifield->joinField && ($rfield->options['editable'] or $rfield->options['readonly'])) { ?>
						<th><?= htmlentities($rfield->name) ?></th>
					<? }
				} ?>
			</tr>
			<? foreach ($related->all() as $relobj) { ?>
			<tr>
				<? foreach ($relobj->_fields as $rfield) {
					$rvalue = $relobj[$rfield->field];
					if ($rfield->field != $ifield->joinField and ($rfield->options['editable'] or $rfield->options['readonly'])) { ?>
						<td><?= htmlentities($rfield->toHTML($rvalue)) ?></td>
					<? }
				} ?>
			</tr>
			<? } ?>
		</table>
		<br />
	<? }
} ?>

<span class="prevpage"><a href="<?= $sectionurl ?>/?<?= $filterstr ?>">Back to <?= htmlentities($modeladmin->name) ?> List</a></span>
