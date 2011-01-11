<?php
// Database configuration
$config['db_host'] = 'localhost';
$config['db_name'] = 'phpapp_example';
$config['db_user'] = 'phpapp';
$config['db_pass'] = 'password';

// Relative URL to serve admin site from
// NOTE: If you change this, be sure to update your apache
// configuration accordingly (in apache/phpapp-example.conf)
$config['admin_path'] = '/admin';

// Mailer - required if using sendMailTemplate / authMail
$config['mail_host'] = 'localhost';
$config['mail_from'] = 'nodody@example.com';
$config['mail_replyto'] = 'nodody@example.com';
$config['mail_bounces'] = 'nodody@example.com';

$config['modules'] = array(
	// Should change this to a more unique path, like:
	// 'users' => 'myappname/modules/users',
	'users' => 'modules/users',
	);

// Additional models that need to be in the path
// (the shared "users" module usually needs to be
// here since you need access to the base models when
// extending it)
$config['models'] = array(
	'phpapp-modules/users/models'
	);
