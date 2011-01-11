<?php
	require_once('phpapp-core/Application.class.php');

	$app = new Application('PHPApp Example',
		dirname(dirname(__FILE__)),
		dirname(dirname(dirname(__FILE__))).'/config',
		dirname(dirname(dirname(__FILE__))).'/local.inc.php');

	$app->start();
