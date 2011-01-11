<?php
	require_once('modelquery/ModelGenerator.class.php');

	if ($argc < 4)
		exit('Usage: '.$argv[0].' <model-dir> <server> <database> [username] [password]');

	$modeldir = $argv[1];
	$server = $argv[2];
	$database = $argv[3];
	$username = $argv[4];
	$password = $argv[5];

	$mg = new ModelGenerator('mysql');
	$mg->setDatabase($server, $database, $username, $password);
	$mg->generateModels($modeldir);
