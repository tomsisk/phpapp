<?php
class Actor extends Model {
	public function configure() {
		$this->id = new IntegerField('ID', array('pk' => true));
		$this->name = new CharField('Name', 255, array('required' => true));
		$this->films = new ManyToManyField('Films', 'Film', 'FilmActor');
	}
}
