<?php
	require_once('phpapp-core/ModelAdmin.class.php');

	class BaseGroupAdmin extends ModelAdmin {

		public function __construct($model, $module) {
			parent::__construct($model, $module);

			// Human-readable interface
			$this->setName('Group');
			$this->setDescription('Create or edit user groups');
			$this->setIcon($module->getMediaURL('users.png'));

			$this->setDisplayFields(array('name'));
			$this->setSearchFields(array('name'));

			$this->setModelAccess('customer');
			$this->setFieldOption('users', 'style', 'multilist');

			$this->setFieldGroups(array(
					array('name' => null,
						'fields' => array('name', 'users')
						)
				));

		}

	}
