<?php
	require_once('lib/initdb.php');

	class TestCache extends TestBase {

		public function testSetAddCache() {
			$director = $this->qf->Director->any();
			$ct = count($director->films);
			$fq = $this->qf->Film;
			for ($i = 0; $i < 10; $i++) {
				$film = $fq->create(array(
					'title' => 'Film '.$i,
					'released' => mktime(0,0,0,7,8,2009)));
				$director->films->add($film);
				$this->assertEquals($film->director, $director);
			}
			$this->assertEquals($ct + 10, count($director->films));
		}

		public function testSetMemberCache() {
			$oct = $this->qf->queryCount;
			$actors = $this->qf->Actor->all()->preload('films');
			$actors->select();
			$this->assertEquals($oct + 1, $this->qf->queryCount);
			foreach ($actors as $a) {
				foreach ($a->films as $f) {
					// Just loop to make sure everything is loaded
				}
			}
			$this->assertEquals($oct + 1, $this->qf->queryCount);
			// Test single object cache
			$actor = $actors->get(1);
			$this->assertEquals($oct + 1, $this->qf->queryCount);
			// Test dependent object cache
			$film = $actor->films->get(1);
			$this->assertEquals($oct + 1, $this->qf->queryCount);
		}

	}
