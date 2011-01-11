<?php
require_once('lib/common.inc.php');
?>
<html>
	<head>
		<title>PHPApp Example Site</title>
	</head>
	<body>
		<h1>PHPApp Example Site</h1>
		<p>Welcome to your new PHPApp site.</p>
		<p>You currently have <?= $app->query->User->count(); ?>
		registered users in the database.</p>

		<p><a href="<?= $app->config['admin_path'] ?>">Login to
		Site Administration</a></p>
	</body>
</html>
