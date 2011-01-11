<?php
	require_once('phpapp-modules/users/admin/BaseUserAdmin.class.php');

	class UserAdmin extends BaseUserAdmin {

		public function __construct($model, $module) {

			parent::__construct($model, $module);

			// Display custom fields in admin (only needed
			// if you customized the User model)

			// $this->setFieldGroups(...);

		}

	}
