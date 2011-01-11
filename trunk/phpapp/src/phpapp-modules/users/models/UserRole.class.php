<?php
	class UserRole extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->user = new ManyToOneField('User', 'User', array('required' => true));
			$this->role = new ManyToOneField('Role', 'Role', array('required' => true));
		}

	}
