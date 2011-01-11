<h1><?= htmlentities($modeladmin->pluralName) ?> Imported Successfully</h1>

<p><?= $recordCount ?> <?= htmlentities(strtolower($modeladmin->pluralName)) ?> have been successfully added to the database.</p>

<p><a href="<?= $sectionurl ?>/">Return to <?= htmlentities(strtolower($modeladmin->name)) ?> administration</a></p>
