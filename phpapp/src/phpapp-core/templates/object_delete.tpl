<h1>Delete <?= htmlentities($modeladmin->name) ?></h1>

<p>Delete the <?= htmlentities(strtolower($modeladmin->name)) ?> <b><?= htmlentities($this->toString($object)) ?></b>?
All items that belong to this <?= htmlentities(strtolower($modeladmin->name)) ?> will also be deleted.</p>

<form action="<?= $sectionurl ?>/delete/<?= $object->pk ?>" method="post">
	<? foreach ($filters as $name => $value) { ?>
		<input type="hidden" name="<?= $name ?>" value="<?= $value ?>" />
	<? } ?>
	<input type="submit" value="Yes, delete <?= htmlentities(strtolower($modeladmin->name)) ?> permananently" />
	<input type="button" value="No, do not delete" onclick="window.location='<?= $sectionurl ?>/?<?= $filterstr ?>';" />
</form>
