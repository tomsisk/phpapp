<h1>Could Not Delete <?= htmlentities($modeladmin->name) ?></h1>

<p>The <?= htmlentities(strtolower($modeladmin->name)) ?> <b><?= htmlentities($this->toString($object)) ?></b> could not be deleted.</p>

<p>Reason: <?= $error->getMessage(); ?></p>

<p><a href="<?= $sectionurl ?>?<?= $filterstr ?>">Return to <?= htmlentities(strtolower($modeladmin->name)) ?> administration</a></p>
