<?php
	/**
	 * @package phpapp-modules
	 * @filesource
	 */

	/*
	 * PHPApp - a PHP application framework
	 * Copyright (C) 2004 Jeremy Jongsma.  All rights reserved.
	 * 
	 * This library is free software; you can redistribute it and/or
	 * modify it under the terms of the GNU Lesser General Public
	 * License as published by the Free Software Foundation; either
	 * version 2.1 of the License, or (at your option) any later version.
	 * 
	 * This library is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	 * Lesser General Public License for more details.
	 * 
	 * You should have received a copy of the GNU Lesser General Public
	 * License along with this library; if not, write to the Free Software
	 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
	 */

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
