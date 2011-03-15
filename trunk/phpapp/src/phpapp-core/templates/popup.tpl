<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title><?= htmlentities($this->defaultValue($title, $admin->appName)) ?></title>
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
	<body class="popup">
		<table width="100%" cellspacing="0" cellpadding="0">
			<tr>
				<td class="popupHeader">
					<div id="branding">
						<span id="apptitle"><?= htmlentities($admin->appName) ?></span>
					</div>
				</td>
				<td style="text-align:right;padding-right:10px;">
					<a href="javascript:window.close()"><img style="vertical-align: middle;"src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
					<a href="javascript:window.close()">Close</a>
				</td>
			</tr>
			<tr>
				<td valign="top" colspan="2">
					<hr />
					<div id="popup">
						<? $this->incl($_template); ?>
					</div>
				</td>
			</tr>
			<tr>
				<td align="center" colspan="2">
					<hr />
					<span style="color:#999999;">
						Copyright &copy;<?= date('Y') ?> <a target="_blank" href="http://www.barchart.com">Barchart.com, Inc.</a>
					</span>
				</td>
			</tr>
		</table>
	</body>
</html>
