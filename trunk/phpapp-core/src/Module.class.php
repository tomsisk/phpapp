<?php
	require_once('ModelAdmin.class.php');

	class Module {

		public $id;
		public $admin;
		public $name;
		public $permission;
		public $modelAdmins;
		public $requiredFilter;
		public $master;
		public $baseDir;
		public $extendsDir;
		public $visible = true;

		public $templateContext;
		public $globalJS = array();
		public $globalCSS = array();

		public $modelAdminDict = array();

		public function  __construct($id, &$admin, $permission = null, $name = null, $baseDir = null) {

			$this->id = $id;
			$this->admin =& $admin;
			$this->permission = $permission ? strtoupper($permission) : strtoupper($id);
			$this->name = $name ? $name : ucfirst($id);
			$this->baseDir = $baseDir;

			$this->templateContext = new PhpAppTemplateContext($admin->templateContext);
			if ($baseDir)
				$this->templateContext->resolver->addPath($this->baseDir.'/templates');

			$this->templateContext->context = array(
				'module' => $this,
				'moduleid' => $this->id,
				'moduleurl' => $this->relativeUrl('')
				);

			$this->modelAdmins = array();

		}

		public function extend($modulepath, $includeConfig = true) {
			$this->templateContext->resolver->addPath($modulepath.'/templates');
			$this->extendsDir = $modulepath;
			if ($includeConfig && $this->admin->fileExistsInPath($modulepath.'/module.php')) {
				$module = $this;
				include($modulepath.'/module.php');
			}
		}

		public function execute($path, $params, $method) {

			if (!$this->checkAccess()) {
				$ade = new AccessDeniedException('You are not allowed to view this section.');
				$ade->setContext($this->templateContext->context);
				throw $ade;
			}

			$handler = array_shift($path);
			if (!$handler) {
				$this->renderTemplate('model_list.tpl', array('models' => $this->modelAdmins));
			} else {
				$modeladmin = $this->getModelAdmin($handler);
				if ($modeladmin)
					$modeladmin->execute($path, $params, $method);
				else
					throw new NotFoundException('The requested page does not exist.');
			}
		}
	
		public function checkAction($action, $type = null) {
			return $this->admin->checkAction($action, $this->permission, $type);
		}

		public function checkAccess($type = null, $instance = null) {
			$hasaccess = $this->admin->checkAccess($this->permission, $type, $instance);
			if ($this->requiredFilter)
				$hasaccess = $hasaccess && $this->admin->getFilter($this->requiredFilter);
			return $hasaccess;
		}

		public function checkAvailable() {
			if ($this->master && $this->admin->getBaseFilter() == $this->master
					&& !$this->admin->getFilter($this->master->getQueryName()))
				return false;
			return $this->visible && $this->checkAccess();
		}

		public function checkPermission($type, $action, $instance = 'ALL') {
			return $this->admin->checkPermission($this->permission, $type, $action, $instance);
		}

		public function getQuery($name) {
			return $this->admin->getQuery($name);
		}

		public function renderTemplate($template, $context = null, $overridePath = null, $basetemplate = null) {

			$base = $this->templateContext->getTemplate(
				$basetemplate ? $basetemplate : 'base.tpl');

			if (!$context)
				$context = array();
			$context['_template'] = $template;

			$base->render($context);

		}

		public function fetchTemplate($template, $context = null, $overridePath = null, $inheritContext = true, $globalSearch = false) {
			$tpl = $this->templateContext->getTemplate($template);
			return $tpl->fetch($context);
		}

		public function relativeRedirect($url) {
			$this->admin->relativeRedirect('/modules/'.$this->id.$url);
		}

		public function relativeUrl($url) {
			return $this->admin->relativeUrl('/modules/'.$this->id.$url);
		}

		public function addModel($name, $adminclass = 'ModelAdmin', $showinadmin = true) {
			$admin = null;
			if ($adminclass) {
				$admindir = $this->baseDir.'/admin';
				$include = $admindir.'/'.$adminclass.'.class.php';
				if ($this->admin->fileExistsInPath($include)) {
					include_once($include);
				} else if ($this->extendsDir) {
					$admindir = $this->extendsDir.'/admin';
					$include = $admindir.'/'.$adminclass.'.class.php';
					if ($this->admin->fileExistsInPath($include))
						include_once($include);
				}
				$admin = new $adminclass($name, $this);
				if (!$showinadmin)
					$admin->setInlineOnly(true);
				$this->modelAdmins[] = $admin;
				if (!array_key_exists($admin->id, $this->modelAdminDict)) 
					$this->modelAdminDict[$admin->id] = $admin;
			} else {
				$admin = new ModelAdmin($name, $this);
				if (!$showinadmin)
					$admin->setInlineOnly(true);
				$this->modelAdmins[] = $admin;
			}
			return $admin;
		}

		public function addCustomModel($modelAdmin, $showinadmin = true) {
			if (!$showinadmin)
				$modelAdmin->setInlineOnly(true);
			$this->modelAdmins[] = $modelAdmin;
			if (!array_key_exists($modelAdmin->id, $this->modelAdminDict)) 
				$this->modelAdminDict[$modelAdmin->id] = $modelAdmin;
		}

		public function getModelAdmin($name) {
			return $this->modelAdminDict[$name];
		}

		public function getUser() {
			return $this->admin->getUser();
		}

		public function getMediaURL($path) {
			return $this->admin->mediaRoot . '/modules/' . $this->id . '/'. $path;
		}

		public function registerPlugin($name, $plugin) {
			$this->admin->registerPlugin($name, $plugin);
		}

	}
