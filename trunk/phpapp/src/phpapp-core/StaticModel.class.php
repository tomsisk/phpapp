<?php
	/**
	 * @package phpapp-core
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
