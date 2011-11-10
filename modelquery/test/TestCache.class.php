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
			$newfilm = $this->qf->Film->create(array(
				'title' => 'Ocean\'s 12',
				'director' => 1,
				'released' => mktime(0,0,0,12,7,2001)));
			$newfilm->save();
			$this->qf->cacheFlush();
			$oct = $this->qf->queryCount;
			$directors = $this->qf->Director->all()->preload('films');
			$directors->select();
			$this->assertEquals($oct + 1, $this->qf->queryCount);
			foreach ($directors as $d) {
				foreach ($d->films as $f) {
					// Just loop to make sure everything is loaded
				}
			}
			$this->assertEquals($oct + 1, $this->qf->queryCount);
			// Test single object cache
			$director = $directors->get(1);
			$this->assertEquals($oct + 1, $this->qf->queryCount);
			// Test dependent object cache
			$film = $director->films->get(1);
			$film = $director->films->get(1);
			$this->assertEquals($oct + 1, $this->qf->queryCount);
		}

	}
