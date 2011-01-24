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

	class BaseUser extends Model {

		public function configure() {
			$this->id = new IntegerField('Id', array('pk' => true));
			$this->username = new CharField('Username', 50, array('required' => true, 'readonly' => true, 'unique' => true, 'blank' => false));
			$this->password = new PasswordField('Password', 50, array('required' => true));
			$this->email = new EmailField('Email', array('required' => true));

			$this->verified = new BooleanField('Verified', array('default' => false));
			$this->active = new BooleanField('Active', array('default' => true));
			$this->staff = new BooleanField('Staff', array('default' => false));
			$this->created = new DateTimeField('Created', array('readonly' => true, 'default' => CURRENT_TIMESTAMP));
			$this->last_login = new DateTimeField('Last Login', array('readonly' => true));

			$this->preferences = new OneToManyField('Preferences', 'UserPreference');
			$this->groups = new ManyToManyField('Groups', 'Group', 'UserGroup');
			$this->roles = new ManyToManyField('Roles', 'Role', 'UserRole');

			$this->setDefaultOrder('+username');
		}

		public function __toString() {
			return strval($this->username);
		}

	}
