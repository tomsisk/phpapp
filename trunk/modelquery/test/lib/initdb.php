<?php
	require_once('adodb/adodb.inc.php');
	require_once(dirname(dirname(dirname(__FILE__))).'/src/modelquery/QueryFactory.class.php');
	@include_once(dirname(dirname(__FILE__)).'/local.inc.php');

	if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
	if (!defined('DB_USER')) define('DB_USER', 'modelquery');
	if (!defined('DB_PASS')) define('DB_PASS', 'password');
	if (!defined('DB_NAME')) define('DB_NAME', 'modelquery');

	function init_modelquery_test_db() {

		$conn = mysql_connect(DB_HOST, DB_USER, DB_PASS, true);
		mysql_select_db(DB_NAME, $conn);

		mysql_query('DROP TABLE IF EXISTS film_actors', $conn);
		mysql_query('DROP TABLE IF EXISTS films', $conn);
		mysql_query('DROP TABLE IF EXISTS directors', $conn);
		mysql_query('DROP TABLE IF EXISTS actors', $conn);

		mysql_query('CREATE TABLE actors (
			id int8 PRIMARY KEY AUTO_INCREMENT,
			name varchar(255)
		) ENGINE=InnoDB CHARSET=utf8', $conn);

		mysql_query('CREATE TABLE directors (
			id int8 PRIMARY KEY AUTO_INCREMENT,
			name varchar(255) NOT NULL
		) ENGINE=InnoDB CHARSET=utf8', $conn);

		mysql_query('CREATE TABLE films (
			id int8 PRIMARY KEY AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			director int8 NOT NULL,
			released datetime NOT NULL,
			nominated boolean NOT NULL DEFAULT FALSE,
			FOREIGN KEY (director) REFERENCES directors(id) ON UPDATE CASCADE ON DELETE RESTRICT
		) ENGINE=InnoDB CHARSET=utf8', $conn);

		mysql_query('CREATE TABLE film_actors (
			id int8 PRIMARY KEY AUTO_INCREMENT,
			film int8 NOT NULL,
			actor int8 NOT NULL,
			FOREIGN KEY (film) REFERENCES films(id) ON UPDATE CASCADE ON DELETE RESTRICT,
			FOREIGN KEY (actor) REFERENCES actors(id) ON UPDATE CASCADE ON DELETE RESTRICT
		) ENGINE=InnoDB CHARSET=utf8', $conn);

		mysql_close($conn);

		// 'mysqlt' is strongly recommended for transactional support
		$conn = ADONewConnection('mysqlt');
		$conn->setFetchMode(ADODB_FETCH_ASSOC);
		$conn->autoCommit = false;
		$conn->Connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

		// You can pass a list of model source directories
		return new QueryFactory($conn, array(dirname(__FILE__).'/models'));

	}

	function load_modelquery_test_data($qf) {

		$qf->FilmActor->all()->delete();
		$qf->Film->all()->delete();
		$qf->Director->all()->delete();
		$qf->Actor->all()->delete();

		// Create some actors
		$aq = $qf->Actor;
		$actorClooney = $aq->create(array('name' => 'George Clooney'));
		$actorClooney->save();
		$actorPitt = $aq->create(array('name' => 'Brad Pitt'));
		$actorPitt->save();
		$actorRoberts = $aq->create(array('name' => 'Julia Roberts'));
		$actorRoberts->save();

		// Create a director
		$dq = $qf->Director;
		$directorSoderbergh = $dq->create(array('name' => 'Steven Soderbergh'));
		$directorSoderbergh->save();

		// Create a film
		$fq = $qf->Film;
		$filmOceans = $fq->create(array(
			'title' => 'Ocean\'s 11',
			'director' => $directorSoderbergh,
			'released' => mktime(0,0,0,12,7,2001)));

		// Film must be saved before we add items to a relation set field
		$filmOceans->save();

		// Adding to a many-to-many set automatically creates a mapping entry,
		// so no further save() call is needed.
		$filmOceans->actors->add($actorClooney);
		$filmOceans->actors->add($actorPitt);
		$filmOceans->actors->add($actorRoberts);

	}
