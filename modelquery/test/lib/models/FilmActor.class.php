<?php
class FilmActor extends Model {
	public function configure() {
		$this->id = new IntegerField('ID', array('pk' => true));
		$this->film = new ManyToOneField('Film', 'Film');
		$this->actor = new ManyToOneField('Actor', 'Actor');
	}
}
