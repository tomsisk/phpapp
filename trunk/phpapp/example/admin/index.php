<?php
	// NOTE: Requests are routed to this controller via the .htaccess file in this directory
	require_once('lib/common.inc.php');
	require_once('phpapp-core/AdminController.class.php');

	$admin = new AdminController($app, isset($app->config['admin_path'])
		? $app->config['admin_path'] : null);
	$admin->handleRequest();
