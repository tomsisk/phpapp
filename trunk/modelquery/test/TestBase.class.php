<?php
	require_once('lib/initdb.php');

	class TestBase extends TestCase {

		public function setUp() {
			global $qf;
			//load_modelquery_test_data($qf);
			$qf->cacheFlush();
			$this->qf = $qf;
		}

		public function tearDown() {
			$this->qf = null;
		}

	}
