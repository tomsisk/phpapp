<?php
	class UserGroup extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->user = new ManyToOneField('User', 'User', array('required' => true));
			$this->group = new ManyToOneField('Group', 'Group', array('required' => true));
		}

	}
