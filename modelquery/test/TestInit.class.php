<?php
	require_once('TestBase.class.php');

	class TestInit extends TestBase {

		public function testInitialData() {
			$this->assertEquals(3, $this->qf->Actor->count());
			$this->assertEquals(1, $this->qf->Film->count());
			$this->assertEquals(3, $this->qf->FilmActor->count());
			$this->assertEquals(1, $this->qf->Director->count());

		}

	}
