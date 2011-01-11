<?php
	require_once('phpapp-core/ModelAdmin.class.php');

	class RoleAdmin extends ModelAdmin {

		public function __construct($model, $module) {
			parent::__construct($model, $module);

			// Human readable interface
			$this->setName('Role');
			$this->setDescription('Create or edit user roles');
			$this->setIcon($module->getMediaURL('roles.png'));

			// List display
			$this->setDisplayFields(array('name', 'description', 'internal'));

			// Access restrictions
			$this->addFieldRestriction('internal', true, 'ADMIN');

			// Form hooks
			$this->registerActionPreHook('add', array($this, 'preEdit'));
			$this->registerActionPreHook('addpopup', array($this, 'preEdit'));
			$this->registerActionPreHook('edit', array($this, 'preEdit'));
			$this->registerActionPostHook('save', array($this, 'postSave'));
			$this->registerActionPostHook('savepopup', array($this, 'postSave'));
		}

		public function preEdit($id, $params, $modeladmin, $method) {
			$permmap = array();

			if ($id) {
				$rpq = $modeladmin->getQuery('RolePermission');
				$permissions = $rpq->filter('role', $id)->order('module', 'type', 'instance', 'action');

				$last = array();
				foreach ($permissions as $p) {
					if ($p->instance != 'ALL')
						continue;
					if ($last->module != $p->module)
						$permmap[$p->module] = array();
					elseif ($last->type != $p->type)
						$permmap[$p->module][$p->type] = array();
					$permmap[$p->module][$p->type][$p->action] = true;
					$last = $p;
				}
			}

			$modeladmin->addContext('roleperms', $permmap);
			$modeladmin->addContext('permactions', array('VIEW' => 'View',
														'CREATE' => 'Create',
														'MODIFY' => 'Modify',
														'DELETE' => 'Delete',
														'ADMIN' => 'Admin'));
		}

		public function postSave(&$id, $params, $modeladmin, $method) {
			$rpq = $modeladmin->getQuery('RolePermission');
			try {
				$rpq->filter('role', $id)->delete();
			} catch (Exception $e) {
			}
			foreach ($params as $k => $v) {
				if (substr($k, 0, 6) == '_perm_') {
					$def = explode('_', $k);
					$perm = $rpq->modelquery->create(array('role' => $id,
																'module' => $def[2],
																'type' => $def[3],
																'instance' => 'ALL',
																'action' => $def[4]));
					try {
						$perm->save();
					} catch (ValidationException $ve) {
					}
				}
			}
		}

	}
