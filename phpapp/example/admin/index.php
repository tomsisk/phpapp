<?php
	// NOTE: Requests are routed to this controller via the .htaccess file in this directory

	/*
		Most of the complexity of this file comes from need to figure out
		if the user is making a media request, since we don't want to go
		through the full overhead of initializing the AdminController
		infrastructure to serve static media.  If you are not concerned
		about this performance optimization, you can replace this entire
		file with the following lines:

		<?php
		require_once('lib/common.inc.php');
		require_once('phpapp-core/AdminController.class.php');
		$admin = new AdminController($app, isset($app->config['admin_path'])
			? $app->config['admin_path'] : null);
		$admin->handleRequest();
	*/

	// Load config to determine media path
	$config = array();
	$root = dirname(dirname(__FILE__));
	if (file_exists($root.'/config/'.$_SERVER['HTTP_HOST'].'.inc.php'))
		include($root.'/config/'.$_SERVER['HTTP_HOST'].'.inc.php');
	else
		include($root.'/config/default.inc.php');
	if (file_exists($root.'/local.inc.php'))
		include($root.'/local.inc.php');

	$mediaPath = isset($config['admin_path']) ? $config['admin_path'].'/media' : '/media';

	if (strpos($_SERVER['REQUEST_URI'], $mediaPath) === 0) {
		// Less resource-intensive initialization since we don't need
		// full AdminController to serve static media
		require_once('phpapp-core/MediaHandler.php');
		handleMediaRequest($mediaPath, $config);
	} else {
		// Non-static request, initialize AdminController
		require_once('lib/common.inc.php');
		require_once('phpapp-core/AdminController.class.php');
		$admin = new AdminController($app, isset($app->config['admin_path'])
			? $app->config['admin_path'] : null);
		$admin->handleRequest();
	}
