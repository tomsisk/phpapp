<h1><?= htmlentities($admin->appName) ?></h1>

<? foreach ($modules as $module) {
	if ($module->checkAvailable()) { ?>
		<h3><a href="<?= $admin->baseUrl ?>/modules/<?= $module->id ?>"><?= htmlentities($module->name) ?></a></h3>
		<?
		$filter = $module->master;
		if ($filter) {
			if (!$admin->getFilter($filter->queryName)) { ?>
				<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;">Select a <?= htmlentities(strtolower($filter->name)) ?></a> to manage<? if ($filter->checkPermission('CREATE')) { ?>, or <a href="<?= $filter->relativeUrl('/add/') ?>">create a new <?= htmlentities(strtolower($filter->name)) ?></a><? } ?>
				<br />
			<? } else { ?>
				<img src="<?= $filter->icon ?>" width="24" height="24"/>
				<b><?= $this->toString($admin->getFilterObject($filter)) ?></b>
				<hr />
			<? } 
		}
		if (!$filter || $admin->getFilter($filter->queryName)) { ?>
			<table cellpadding="0" cellspacing="20" border="0">
			<?
				$rowct = 0;
				foreach ($module->modelAdmins as $modeladmin) {
					if ($modeladmin->checkAccess() && !$modeladmin->inlineOnly) {
						if ($rowct % 3 == 0)
							echo '<tr>';
						?>
						<td valign="top" width="50">
							<? if ($modeladmin->icon) { ?>
							<a href="<?= $admin->baseUrl ?>/modules/<?= $module->id ?>/<?= $modeladmin->id ?>/"><img src="<?= $modeladmin->icon ?>"/></a>
							<? } ?>
						</td>
						<td valign="top" width="150">
							<a href="<?= $admin->baseUrl ?>/modules/<?= $module->id ?>/<?= $modeladmin->id ?>/"><b><? if ($modeladmin->isMaster()) { echo htmlentities($modeladmin->name).' Details'; } else { echo htmlentities($modeladmin->pluralName); } ?></b></a>
							<br />
							<small><?= htmlentities($modeladmin->description) ?></small>
						</td>
						<?
						if ($rowct % 3 == 2)
							echo '</tr>';
						$rowct++;
					}
				}
				$leftover = 2- (($rowct-1) % 3);
				if ($leftover > 0) {
					for ($i = 0; $i < $leftover; ++$i) { ?>
							<td width="50">&nbsp;</td>
							<td width="150">&nbsp;</td>
					<? } ?>
					</tr>
				<? } ?>
			</table>
		<? } ?>
		<br />
	<? }
}
