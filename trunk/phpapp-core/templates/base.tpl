<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title><?= htmlentities($this->defaultValue($title, $admin->appName)) ?></title>
		<link rel="stylesheet" type="text/css" href="<?= $admin->mediaRoot ?>/css/admin.css"/>
		<link rel="stylesheet" type="text/css" href="<?= $admin->baseUrl ?>/css/admin.css"/>
<? if (count($defStylesheets)) {
	foreach ($defStylesheets as $ss) { ?>
		<link rel="stylesheet" href="<?= $ss ?>"/>
	<? }
}
if (count($stylesheets)) {
	foreach ($stylesheets as $ss) { ?>
		<link rel="stylesheet" href="<?= $ss ?>"/>
	<? }
}
if ($stylesheet) { ?>
		<link rel="stylesheet" href="<?= $stylesheet ?>"/>
<? } ?>
		<script language="javascript">
			var mediaRoot = '<?= $admin->mediaRoot ?>';
			var baseUrl = '<?= $admin->baseUrl ?>';
			var urlList = '<?= $_SESSION['fileManager']['urlList'] ?>';
			var userLocale = 'en_iso8601';
		</script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/prototype-1.6.0.2.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/scriptaculous/scriptaculous.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/prototype-base-extensions.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/prototype-date-extensions.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/prototype-form-extensions.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/behaviour.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/controls.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/datepicker.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/colorpicker.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/treelist.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/tiny_mce/tiny_mce.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/admin.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->mediaRoot ?>/js/admin-behaviors.js"></script>
		<script type="text/javascript" language="javascript" src="<?= $admin->baseUrl ?>/js/admin.js"></script>
<? if (count($defScripts)) {
	foreach ($defScripts as $scr) { ?>
		<script type="text/javascript" language="javascript" src="<?= $scr ?>"></script>
	<? }
}
if (count($scripts)) {
	foreach ($scripts as $scr) { ?>
		<script type="text/javascript" language="javascript" src="<?= $scr ?>"></script>
	<? }
}
if ($script) { ?>
		<script type="text/javascript" language="javascript" src="<?= $script ?>"></script>
<? } ?>
	</head>
	<body>
		<table class="page_width" cellspacing="0" cellpadding="0">
			<tr>
				<td>
					<div class="logoCell">
						<div id="branding">
							<a href="<?= $admin->baseUrl ?>/"><span id="apptitle"><?= htmlentities($admin->appName) ?></span></a>
						</div>
					</div>
				</td>
				<td align="right" valign="bottom">
					<div class="headerCell">
						<? if ($user) { ?>
							Logged in as <?= $user->username ?> [<a href="<?= $admin->baseUrl ?>/account/logout">logout</a>] -
							<a href="<?= $admin->baseUrl ?>/account/">My Account</a>
						<? } ?>
					</div>
				</td>
			</tr>
		</table>
		<table class="page_width" cellspacing="0" cellpadding="0">
			<tr>
				<td valign="top" class="sidebarCell">
					<div id="sidebar">
						<? if ($user) { ?>
							<? if ($admin->baseFilter) {
								$filter = $admin->baseFilter;
								if (!$admin->getFilter($filter->queryName)) { ?>
									<div class="menuhighlight"><a href=""
										onclick="popup('<?= $filter->relativeUrl('/filter/') ?>',
											700, 700); return false;">Select a <?= htmlentities(strtolower($filter->name)) ?></a>
										to manage<? if ($filter->checkPermission('CREATE')) { ?>, or
										<a href="<?= $filter->relativeUrl('/add/') ?>">create a new
										<?= htmlentities(strtolower($filter->name)) ?></a><? } ?></div>
									<br />
								<? } else { ?>
									<div style="float:left; padding-bottom: 3px;"><img src="<?= $filter->icon ?>" width="24" height="24"/></div>
									<div style="margin-left: 30px;">
										<b><?= $this->toString($admin->getFilterObject($filter)) ?></b>
									</div>
									<?  $query = $filter->getQuery(); ?>
									<? if (($filter->checkPermission('VIEW') && $query->count() > 1)
											|| $filter->checkPermission('CREATE')) { ?>
										<hr style="clear:left;" />
										<small>
											<? if ($filter->checkPermission('VIEW') && $query->count() > 1) { ?>
											<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;"><img src="<?= $admin->mediaRoot ?>/images/undo.png"/></a>
											<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;">select another <?= htmlentities(strtolower($filter->name)) ?></a>
											<br />
											<? }
											if ($filter->checkPermission('CREATE')) { ?>
												<a href="<?= $filter->relativeUrl('/add/') ?>"><img src="<?= $admin->mediaRoot ?>/images/add.png"/></a>
												<a href="<?= $filter->relativeUrl('/add/') ?>">add a new <?= htmlentities(strtolower($filter->name)) ?></a>
											<? } ?>
										</small>
									<? } ?>
									<hr style="clear:left;" />
								<? } ?>
							<? }
							foreach ($modules as $module_id => $mod) {
								if ($mod->checkAvailable()) { ?>
									<div class="menumodule">
										<?
										$filter = $mod->master;
										if ($moduleid != $module_id) { ?>
											<div class="menumoduletitle">
												<a href="<?= $admin->baseUrl ?>/modules/<?= $module_id ?>/"><?= htmlentities($mod->name) ?></a>
											</div>
										<? } else { ?>
											<div class="menumoduletitle">
												<a href="<?= $admin->baseUrl ?>/modules/<?= $module_id ?>/"><?= htmlentities($mod->name) ?></a>
											</div>
											<div class="menumoduleitems">
												<? if ($filter && $filter != $admin->baseFilter) {
													if ($admin->getFilter($filter->queryName)) { ?>
														<div style="float:left; padding-bottom: 3px;"><img src="<?= $filter->icon ?>" width="24" height="24"/></div>
														<div style="margin-left: 30px;">
															<b><?= $this->toString($admin->getFilterObject($filter)) ?></b>
														</div>
														<hr style="clear:left;" />
													<? }
													if (($filter->checkPermission('VIEW')
																&& $filter->getQuery()->count() > 1)
															|| $filter->checkPermission('CREATE')) { ?>
														<small>
															<? if ($filter->checkPermission('VIEW') && $filter->getQuery()->count() > 1) { ?>
															<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;"><img src="<?= $admin->mediaRoot ?>/images/undo.png"/></a>
															<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;">select another <?= htmlentities(strtolower($filter->name)) ?></a>
															<br />
															<? }
															if ($filter->checkPermission('CREATE')) { ?>
																<a href="<?= $filter->relativeUrl('/add/') ?>"><img src="<?= $admin->mediaRoot ?>/images/add.png"/></a>
																<a href="<?= $filter->relativeUrl('/add/') ?>">add a new <?= htmlentities(strtolower($filter->name)) ?></a>
															<? } ?>
														</small>
														<? if ($admin->getFilter($filter->queryName)) { ?>
														<hr />
														<? }
													}
												} ?>
												<? if (!$filter || $admin->getFilter($filter->queryName)) {
													foreach ($mod->modelAdmins as $ma) {
														if ($ma->checkAccess() && !$ma->inlineOnly) {
															if ($ma->isMaster()) { ?>
																<div class="<? if ($section == $ma->id) echo 'active'; ?>menuitem"><a href="<?= $admin->baseUrl ?>/modules/<?= $module_id ?>/<?= $ma->id ?>/"><?= htmlentities(ucwords($ma->name)) ?> Details</a></div>
															<? } else { ?>
																<div class="<? if ($section == $ma->id) echo 'active'; ?>menuitem"><a href="<?= $admin->baseUrl ?>/modules/<?= $module_id ?>/<?= $ma->id ?>/"><?= htmlentities(ucwords($ma->pluralName)) ?></a></div>
															<? }
														}
													}
												} ?>
											</div>
										<? } ?>
									</div>
								<? }
							}
						} ?>
					</div>
				</td>
				<td valign="top">
					<div id="page">
						<?
						if ($phperror)
							$this->incl('phperror.tpl');
						$this->incl($_template);
						?>
					</div>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<div id="footer">
						<?
						$first = true;
						foreach ($modules as $mod) {
							if ($mod->checkAvailable()) {
								if (!$first) echo ' | ';
								$first = false;
								?>
								<a href="<?= $admin->baseUrl ?>/modules/<?= $mod->id ?>"><?= htmlentities($mod->name) ?></a>
							<?  }
						} ?>
						<br />
						<br />
						Copyright &copy;<?= date('Y') ?> <a target="_blank" href="http://www.barchart.com">Barchart.com, Inc.</a>
						<? if ($debug) { ?>
							<div class="debug">TODO: DEBUG LOGS</div>
						<? } ?>
					</div>
				</td>
			</tr>
		</table>
	</body>
</html>
