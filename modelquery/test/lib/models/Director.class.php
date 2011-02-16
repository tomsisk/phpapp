<?php
class Director extends Model {

	public function configure() {
		$this->id = new IntegerField('ID', array('pk' => true));
		$this->name = new CharField('Name', 255, array('required' => true));
		$this->films = new OneToManyField('Films', 'Film');
		$this->setDefaultOrder('+name');
	}

	public function __toString() {
		return strval($this->name);
	}

}
