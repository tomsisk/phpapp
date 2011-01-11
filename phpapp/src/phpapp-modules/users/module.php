<?php
	// For include only by PHPAPP core
	$module->name = 'Users';
	$usermod = $module->addModel('User', 'UserAdmin');
	$usermod->includeScript('user.js');
	$module->addModel('UserPreference', null, false);
	$module->addModel('Group', 'GroupAdmin');
	$rolemod = $module->addModel('Role', 'RoleAdmin');
	$rolemod->includeScript('role.js');
