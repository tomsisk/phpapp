<?php
	class RolePermission extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->role = new ManyToOneField('Role', 'Role');
			$this->module = new CharField('Module', 50, array('required' => true));
			$this->type = new CharField('Type', 50, array('required' => true));
			$this->instance = new CharField('Instance', 50, array('required' => true));
			$this->action = new CharField('Action', 50, array('required' => true));
		}

	}
