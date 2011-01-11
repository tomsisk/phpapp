<?php
	require_once('phpapp-modules/users/admin/BaseGroupAdmin.class.php');

	class GroupAdmin extends BaseGroupAdmin {

		public function __construct($model, $module) {

			parent::__construct($model, $module);

			// Display custom fields in admin (only needed
			// if you customized the Group model)

			// $this->setFieldGroups(...);

		}

	}
