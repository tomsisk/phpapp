<?php

	// Bit of a hack for shared static pages
	class StaticModel extends ModelAdmin {

		public function  __construct($module, $name, $id, $plural = null) {

			$this->id = $id;
			$this->module = $module;
			$this->setName($name, $plural);

			$this->templateContext = new PhpAppTemplateContext($module->templateContext);
			if ($module->baseDir)
				$this->templateContext->resolver->addPath($this->module->baseDir.'/templates/'.$this->id);

			$this->templateContext->context = array(
				'modeladmin' => $this,
				'section' => $this->id,
				'sectionurl' => $this->relativeUrl('')
				);

		}

		public function execute($path, $params, $method) {

			if (!$this->checkAccess()) {
				$ade = new AccessDeniedException('You are not allowed to view this section.');
				$ade->setContext($this->templateContext->context);
				throw $ade;
			}

			$template = implode('/', $path);
			if (!$template)
				$template = 'index';

			$this->renderTemplate($template.'.tpl');

		}

		public function getQuery($name = null) {
			if ($name)
				return $this->module->getQuery($name);
			return null;
		}

		public function getPrototype() {
			return null;
		}

	}
