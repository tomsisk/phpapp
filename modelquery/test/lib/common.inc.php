<?php
require_once('adodb/adodb.inc.php');
require_once(dirname(dirname(dirname(dirname(__FILE__))))
	.'/src/modelquery/QueryFactory.class.php');

// 'mysqlt' is strongly recommended for transactional support
$conn = ADONewConnection('mysqlt');
$conn->setFetchMode(ADODB_FETCH_ASSOC);
$conn->autoCommit = false;
$conn->Connect('localhost', 'films', 'films', 'films');

// You can pass a list of model source directories
$qf = new QueryFactory($conn, array(dirname(__FILE__).'/models'));
