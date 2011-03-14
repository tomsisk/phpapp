<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title><?= htmlentities($this->defaultValue(isset($title) ? $title : null, $admin->appName)) ?></title>
		<link rel="stylesheet" type="text/css" href="<?= $admin->mediaRoot ?>/css/admin.css"/>
		<link rel="stylesheet" type="text/css" href="<?= $admin->baseUrl ?>/css/admin.css"/>
<? if (isset($defStylesheets) && count($defStylesheets)) {
	foreach ($defStylesheets as $ss) { ?>
		<link rel="stylesheet" href="<?= $ss ?>"/>
	<? }
}
if (isset($stylesheets) && count($stylesheets)) {
	foreach ($stylesheets as $ss) { ?>
		<link rel="stylesheet" href="<?= $ss ?>"/>
	<? }
}
if (isset($stylesheet)) { ?>
		<link rel="stylesheet" href="<?= $stylesheet ?>"/>
<? } ?>
		<script language="javascript">
			var mediaRoot = '<?= $admin->mediaRoot ?>';
			var baseUrl = '<?= $admin->baseUrl ?>';
			var urlList = '<?= $_SESSION['fileManager']['urlList'] ?>';
			var userLocale = 'en_iso8601';
		</script>
		<script type="text/javascript" language="javascript" src="https://ajax.googleapis.com/ajax/libs/prototype/1.7.0.0/prototype.js"></script>
		<script type="text/javascript" language="javascript" src="https://ajax.googleapis.com/ajax/libs/scriptaculous/1.8.3/scriptaculous.js"></script>
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
<? if (isset($defScripts) && count($defScripts)) {
	foreach ($defScripts as $scr) { ?>
		<script type="text/javascript" language="javascript" src="<?= $scr ?>"></script>
	<? }
}
if (isset($scripts) && count($scripts)) {
	foreach ($scripts as $scr) { ?>
		<script type="text/javascript" language="javascript" src="<?= $scr ?>"></script>
	<? }
}
if (isset($script)) { ?>
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
											|| $filter->checkPermission('CREATE')
											|| ($admin->getFilter($filter->queryName)
												&& $filter->checkPermission('DELETE'))) { ?>
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
												<br />
											<? }
											if ($admin->getFilter($filter->queryName) && $filter->checkPermission('DELETE')) { ?>
												<a href="<?= $filter->relativeUrl('/deleteconfirm/'.$admin->getFilter($filter->queryName)) ?>"><img src="<?= $admin->mediaRoot ?>/images/delete.png"/></a>
												<a href="<?= $filter->relativeUrl('/deleteconfirm/'.$admin->getFilter($filter->queryName)) ?>">delete this <?= htmlentities(strtolower($filter->name)) ?></a>
												<br />
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
										if (!isset($moduleid) || $moduleid != $module_id) { ?>
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
															|| $filter->checkPermission('CREATE')
															|| ($admin->getFilter($filter->queryName)
																&& $filter->checkPermission('DELETE'))) { ?>
														<small>
															<? if ($filter->checkPermission('VIEW') && $filter->getQuery()->count() > 1) { ?>
															<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;"><img src="<?= $admin->mediaRoot ?>/images/undo.png"/></a>
															<a href="" onclick="popup('<?= $filter->relativeUrl('/filter/') ?>', 700, 700); return false;">select another <?= htmlentities(strtolower($filter->name)) ?></a>
															<br />
															<? }
															if ($filter->checkPermission('CREATE')) { ?>
																<a href="<?= $filter->relativeUrl('/add/') ?>"><img src="<?= $admin->mediaRoot ?>/images/add.png"/></a>
																<a href="<?= $filter->relativeUrl('/add/') ?>">add a new <?= htmlentities(strtolower($filter->name)) ?></a>
																<br />
															<? }
															if ($admin->getFilter($filter->queryName) && $filter->checkPermission('DELETE')) { ?>
																<a href="<?= $filter->relativeUrl('/deleteconfirm/'.$admin->getFilter($filter->queryName)) ?>"><img src="<?= $admin->mediaRoot ?>/images/delete.png"/></a>
																<a href="<?= $filter->relativeUrl('/deleteconfirm/'.$admin->getFilter($filter->queryName)) ?>">delete this <?= htmlentities(strtolower($filter->name)) ?></a>
																<br />
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
																<div class="<? if (isset($section) && $section == $ma->id) echo 'active'; ?>menuitem"><a href="<?= $admin->baseUrl ?>/modules/<?= $module_id ?>/<?= $ma->id ?>/"><?= htmlentities(ucwords($ma->name)) ?> Details</a></div>
															<? } else { ?>
																<div class="<? if (isset($section) && $section == $ma->id) echo 'active'; ?>menuitem"><a href="<?= $admin->baseUrl ?>/modules/<?= $module_id ?>/<?= $ma->id ?>/"><?= htmlentities(ucwords($ma->pluralName)) ?></a></div>
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
						try {
							ob_start();
							$this->incl($_template);
							ob_end_flush();
						} catch (Exception $e) {
							ob_end_clean();
							$this->incl('error.tpl', array(
								'title' => 'Error',
								'error' => $e->getMessage(),
								'exception' => $e));
						}
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
							<div class="debug">
							<hr />
							<p><b>Debug Log:</b></p>
							<?
								$timestamp = date('H:i:s', $app->startTime).substr($app->startTime - intval($app->startTime), 1, 4);
								print $timestamp.': Execution started<br />'."\n";
								$app->logDebug('Execution finished: '.$app->elapsedTime($app->startTime).'s');
								foreach ($app->logs['debug'] as $msg) {
									echo $msg[0]."<br />\n";
								}
							?>
							</div>
						<? } ?>
					</div>
				</td>
			</tr>
		</table>
	</body>
</html>
