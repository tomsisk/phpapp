<?php
class Film extends Model {

	public function configure() {
		$this->id = new IntegerField('ID', array('pk' => true));
		$this->title = new CharField('Title', 255, array('required' => true));
		$this->director = new ManyToOneField('Director', 'Director', array('required' => true));
		$this->released = new DateTimeField('Release Date', array('required' => true));
		$this->nominated = new BooleanField('Oscar Nomination', array('required' => true,
					'default' => false));

		$this->actors = new ManyToManyField('Actors', 'Actor', 'FilmActor');

		$this->setDefaultOrder('-released', '+title');
	}

	public function __toString() {
		return strval($this->title);
	}

}
