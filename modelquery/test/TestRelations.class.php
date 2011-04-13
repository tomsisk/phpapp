<?php
	require_once('TestBase.class.php');

	class TestRelations extends TestBase {

		public function testOneToManyLink() {
			$d = $this->qf->Director->any();
			$this->assertEquals(1, $d->films->count());
			foreach ($d->films as $f) {
				$this->assert($f->pk > 0);
			}
		}

		public function testManyToOneLink() {
			$d = $this->qf->Director->any();
			$f = $this->qf->Film->any();
			$this->assertEquals($f->director, $d);
		}

		public function testManyToManyLink() {
			$f = $this->qf->Film->any();
			$this->assertEquals(3, $f->actors->count());
			foreach ($f->actors as $a) {
				$this->assert($a->pk > 0);
			}
		}

		public function testOneToManyPreload() {
			$d = $this->qf->Director->preload('films')->any();
			$ct = $this->qf->queryCount;
			$this->assertEquals(1, $d->films->count());
			foreach ($d->films as $f) {
				$this->assert($f->pk > 0);
			}
			$this->assertEquals($ct, $this->qf->queryCount);
		}

		public function testManyToManyPreload() {
			$f = $this->qf->Film->preload('actors')->any();
			$ct = $this->qf->queryCount;
			$this->assertEquals(1, $f->actors->count());
			foreach ($f->actors as $a) {
				$this->assert($a->pk > 0);
			}
			$this->assertEquals($ct, $this->qf->queryCount);
		}

		public function testM2MSaved() {
			$actor = $this->qf->Actor->create(array('name' => 'Bernie Mac'));
			$actor->save();
			$film = $this->qf->Film->any();
			// Immediately adds glue record, since both records exist in the DB
			$film->actors->add($actor);
			$this->qf->cacheFlush();
			$this->assertEquals(4, $film->actors->count());
			// Cleanup
			$film->actors->remove($actor);
			$actor->delete();
		}

		public function testM2MUnsaved() {
			$director = $this->qf->Director->any();
			$actor = $this->qf->Actor->create(array('name' => 'Sasha Grey'));
			$film = $this->qf->Film->create(array(
				'title' => 'The Girlfriend Experience',
				'director' => $director,
				'released' => mktime(0,0,0,7,8,2009)));
			// Add just adds in memory, because there are no DB records yet
			$film->actors->add($actor);
			$this->assertEquals(1, $film->actors->count());
			$this->assertEquals(null, $actor->pk);
			$this->assertEquals(null, $film->pk);
			// On save, it should:
			// 1) save related obejct (Actor)
			// 2) save parent object (Film)
			// 3) insert glue records (FilmActor)
			$film->save();
			$this->qf->cacheFlush();
			$this->assertEquals(1, $film->actors->count());
			$this->assert($actor->pk > 0);
			$this->assert($film->pk > 0);
			// Cleanup
			$film->actors->remove($actor);
			$film->delete();
			$actor->delete();
		}

		public function testM2MUnsavedOnSaved() {
			$actor = $this->qf->Actor->create(array('name' => 'Bernie Mac'));
			$film = $this->qf->Film->any();
			// Should immediately save Actor and add glue record,
			// since Film is already in the database
			$film->actors->add($actor);
			$this->assertEquals(4, $film->actors->count());
			$this->assert($actor->pk > 0);
			// Cleanup
			$film->actors->remove($actor);
			$actor->delete();
		}

		public function testM2MSavedOnUnsaved() {
			$director = $this->qf->Director->any();
			$actor = $this->qf->Actor->filter('name', 'Julia Roberts')->one();
			$film = $this->qf->Film->create(array(
				'title' => 'Erin Brokovich',
				'director' => $director,
				'released' => mktime(0,0,0,3,17,2000)));
			// Just adds in memory since Film does not exist in DB
			$film->actors->add($actor);
			$this->assertEquals(1, $film->actors->count());
			$this->assertEquals(null, $film->pk);
			// On save, it should:
			// 1) Save the parent object (Film)
			// 2) Insert glue records (FilmActor)
			$film->save();
			$this->assert($film->pk > 0);
			$this->qf->cacheFlush();
			$film = $this->qf->Film->get($film->pk);
			$this->assertEquals(1, $film->actors->count());
			// Cleanup
			$film->actors->remove($actor);
			$film->delete();
		}

		public function testO2MUnsaved() {
			$director = $this->qf->Director->create(array('name' => 'Roger Michell'));
			$film = $this->qf->Film->create(array(
				'title' => 'Notting Hill',
				'released' => mktime(0,0,0,5,28,1999)));
			$director->films->add($film);
			// Add just adds in memory, because there are no DB records yet
			$this->assertEquals(1, $director->films->count());
			$this->assertEquals(null, $director->pk);
			$this->assertEquals(null, $film->pk);
			// On save, it should:
			// 1) save parent object (Director)
			// 2) save related obejct (Film)
			$director->save();
			$this->qf->cacheFlush();
			$this->assertEquals(1, $director->films->count());
			$this->assert($director->pk > 0);
			$this->assert($film->pk > 0);
			// Cleanup
			$film->delete();
			$director->delete();
		}

		public function testO2MUnsavedOnSaved() {
			$director = $this->qf->Director->any();
			$film = $this->qf->Film->create(array(
				'title' => 'Erin Brokovich',
				'released' => mktime(0,0,0,3,17,2000)));
			// Should immediately save Film and add glue record,
			// since Director is already in the database
			$director->films->add($film);
			$this->assertEquals(2, $director->films->count());
			$this->assert($film->pk > 0);
			// Cleanup
			$film->delete();
		}

		public function testO2MSavedOnUnsaved() {
			$director = $this->qf->Director->any();
			$film = $this->qf->Film->create(array(
				'title' => 'Erin Brokovich',
				'director' => $director,
				'released' => mktime(0,0,0,3,17,2000)));
			$film->save();
			$this->assertEquals(2, $director->films->count());

			$newdirector = $this->qf->Director->create(array('name' => 'New Director'));
			// Just adds in memory since Film does not exist in DB
			$newdirector->films->add($film);
			$this->assertEquals(1, $newdirector->films->count());
			// Not going to change until we save
			$this->assertEquals(2, $director->films->count());
			// On save, it should:
			// 1) Save the parent object (Director)
			// 1) Update the child field (Film)
			$newdirector->save();
			$this->assert($newdirector->pk > 0);
			//$this->qf->cacheFlush();
			$film = $this->qf->Film->get($film->pk);
			$newdirector = $this->qf->Director->get($newdirector->pk);
			$this->assertEquals(1, $newdirector->films->count());
			$this->assertEquals($newdirector, $film->director);
			// Cleanup
			$film->delete();
			$newdirector->delete();
		}

		public function testO2MCascadeExisting() {

			$director = $this->qf->Director->any();
			$film = $director->films[0];
			$title = $film->title;

			$film->title = 'New Title';
			$director->saveCascade();
			$this->qf->cacheFlush();
			
			$film = $this->qf->Film->get($film->pk);
			$this->assertEquals($film->title, 'New Title');
		}

	}
