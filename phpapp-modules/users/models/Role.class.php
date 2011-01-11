<?php
	class Role extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->name = new CharField('Name', 50, array('required' => true));
			$this->description = new TextField('Description', array('required' => true));
			$this->sort_order = new OrderedField('Sort Order', null, array('required' => true));
			$this->internal = new BooleanField('Internal', array('required' => true, 'default' => false));
			$this->setDefaultOrder('+sort_order');
		}

		public function __toString() {
			return strval($this->name);
		}

	}
