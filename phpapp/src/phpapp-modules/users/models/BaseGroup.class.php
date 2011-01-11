<?php
	class BaseGroup extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->name = new CharField('Name', 30, array('required' => true));
		}

		public function __toString() {
			return strval($this->name);
		}

	}
