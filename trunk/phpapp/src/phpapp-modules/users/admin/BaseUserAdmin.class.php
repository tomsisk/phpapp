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

	class BaseUserAdmin extends ModelAdmin {

		public function __construct($model, $module) {
			parent::__construct($model, $module);

			// Human-readable interface
			$this->setName('User');
			$this->setDescription('Create or edit users');
			$this->setIcon($module->getMediaURL('users.png'));
			
			// Search / index page options
			$this->setSearchFields(array('username', 'email'));
			$this->setDisplayFields(array('username', 'email', 'site', 'staff', 'last_login'));
			$this->setFilterFields(array('verified', 'staff', 'active', 'groups', 'roles'));
			$this->setDeleteConfirm(true);

			$this->setFieldOption('users', 'style', 'multilist');

			$this->setFieldOption('staff', 'help', 'If checked, the user will be allowed '
				.'to login to this admin site');

			// Edit form field groupings
			$this->setFieldGroups(array(
					array('name' => null,
						'fields' => array('username', 'password', 'email', 'verified', 'active', 'created', 'last_login')
						),
					array('name' => 'User Privileges',
						'fields' => array('staff', 'groups', 'roles')
						)
					)
				);

			// Relation set display options
			$this->addInlineObject('preferences');
			$this->setFieldOptions('groups', array('style' => 'checkbox'));
			$this->setFieldOptions('roles', array(
				'style' => 'checkbox',
				'groupby' => 'internal',
				'groupheadings' => array(true, 'Internal Roles')
				));

			// Custom hooks for individual permissions
			$this->registerActionPreHook('add', array($this, 'preEdit'));
			$this->registerActionPreHook('addpopup', array($this, 'preEdit'));
			$this->registerActionPreHook('edit', array($this, 'preEdit'));
			$this->registerActionPreHook('save', array($this, 'preEdit'));
			$this->registerActionPreHook('savepopup', array($this, 'preEdit'));
			$this->registerActionPostHook('save', array($this, 'postSave'));
			$this->registerActionPostHook('savepopup', array($this, 'postSave'));
			$this->registerActionHandler('permjs', array($this, 'getPermsJSON'));

		}

		public function preEdit($id, $params, $modeladmin, $method) {
			if ($id) {
				$rpq = $modeladmin->getQuery('UserPermission');
				$permissions = $rpq->filter('user', $id)->order('module', 'type', 'instance', 'action');

				$userperms = array();
				foreach ($permissions as $p) {
					$token = $p->module.'|'.$p->type.'|'.$p->instance.'|'.$p->action;
					$desc = $this->permDesc($p, $modeladmin);
					if ($desc)
						$userperms[] = array('token' => $token, 'desc' => $desc);
				}

				$modeladmin->addContext('userperms', $userperms);
			}
			$modeladmin->addContext('permactions', array('VIEW' => 'View',
														'CREATE' => 'Create',
														'MODIFY' => 'Modify',
														'DELETE' => 'Delete',
														'ADMIN' => 'Admin'));
		}

		public function postSave(&$id, $params, $modeladmin, $method) {
			$upq = $modeladmin->getQuery('UserPermission');
			try {
				$upq->filter('user', $id)->delete();
			} catch (Exception $e) {
			}
			if (array_key_exists('_permissions', $params)) {
				foreach ($params['_permissions'] as $p) {
					$parts = explode('|', $p);
					// Only let users assign permissions they have themselves
					if ($modeladmin->getAdmin()->checkPermission($parts[0], $parts[1], $parts[3], $parts[2])) {
						$perm = $upq->modelquery->create(array('user' => $id,
																	'module' => $parts[0],
																	'type' => $parts[1],
																	'instance' => $parts[2],
																	'action' => $parts[3]));
						try {
							$perm->save();
						} catch (ValidationException $ve) {
						}
					}
				}
			}
		}

		public function getPermsJSON($id, $params, $modeladmin, $method) {
			$results = array('ALL' => 'All items');
			if (array_key_exists('type', $params)) {
				if ($module = $this->findModule($modeladmin, $params['module'])) {
					if ($module->checkAccess()) {
						if ($model = $this->getModelAdmin($module, $params['type'])) {
							if ($model->checkAccess()) {
								$q = $model->getQuery();
								if ($q) {
									$objects = $model->getQuery()->all();
									foreach ($objects as $obj)
										$results[$obj->pk] = strval($obj);
								}
							}
						}
					}
				}
			} elseif (array_key_exists('module', $params)) {
				if ($module = $this->findModule($modeladmin, $params['module'])) {
					if ($module->checkAccess()) {
						$models = $module->modelAdmins;
						if ($models)
							foreach ($models as $ma)
								$results[$ma->permission] = $ma->getPlural();
					}
				}
			}
			echo json_encode($results);
		}

		private function findModule($modeladmin, $perm) {
			$modules = $modeladmin->getAdmin()->modules;
			foreach($modules as $module)
				if ($module->permission == $perm)
					return $module;
			return null;
		}

		private function getModelAdmin($module, $perm) {
			$models = $module->modelAdmins;
			if ($models)
				foreach ($models as $model)
					if ($model->permission == $perm)
						return $model;
			return null;
		}

		private function permDesc($perm, $modeladmin) {

			$module = $this->findModule($modeladmin, $perm['module']);

			if ($module) {

				$ma = $this->getModelAdmin($module, $perm['type']);

				if ($ma) {

					$desc = '[<i>'.($module?$module->name:'All').'::'.($ma?$ma->getName():'All').'</i>] ';
					if ($perm->instance == 'ALL')
						$desc .= 'All items';
					else
						$desc .= strval($ma->getQuery()->get($perm->instance));
					$desc .= ' - ';
					if ($perm->action == 'ALL')
						$desc .= 'All privileges';
					else
						$desc .= ucfirst(strtolower($perm->action));
					return $desc;
				}

			}

			return null;

		}

	}
