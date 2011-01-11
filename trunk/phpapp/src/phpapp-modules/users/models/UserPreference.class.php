<?php
	class UserPreference extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->user = new ManyToOneField('User', 'User', array('required' => true));
			$this->name = new CharField('Name', 50, array('required' => true));
			$this->value = new CharField('Value', 255);
		}

		public function __toString() {
			return strval($this->name);
		}

	}
