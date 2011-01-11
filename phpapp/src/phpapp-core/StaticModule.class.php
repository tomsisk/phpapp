<?php

	// Bit of a hack for shared static pages
	class StaticModule extends Module {

		public function __construct($id, &$admin, $permission, $name, $baseDir) {
			parent::__construct($id, $admin, $permission, $name, $baseDir);
			$this->visible = false;
		}

		public function execute($path, $params, $method) {
			$template = implode('/', $path).'.tpl';
			if ($params['popup'])
				$this->renderTemplate($template, null, null, 'popup.tpl');
			else
				$this->renderTemplate($template);
		}

	}
