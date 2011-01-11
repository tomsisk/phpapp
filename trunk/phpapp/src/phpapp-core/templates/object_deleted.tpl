<h1><?= htmlentities($modeladmin->name) ?> Deleted</h1>

<p>The <?= htmlentities(strtolower($modeladmin->name)) ?> <b><?= htmlentities($this->toString($object)) ?></b> has been deleted.</p>

<p><a href="<?= $sectionurl ?>?<?= $filterstr ?>">Return to <?= htmlentities(strtolower($modeladmin->name)) ?> administration</a></p>
