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
