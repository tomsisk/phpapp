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

	class PhpAppTemplateFactory {

		protected $templateContext;
		protected $resolver;

		public function __construct($resolver = null, $templateContext = null) {
			$this->resolver = $resolver;
			if ($templateContext) {
				$this->templateContext->resolver = $resolver;
				$this->templateContext = $templateContext;
			} else
				$this->templateContext = new PhpAppTemplateContext(null, $resolver);
		}

		public function setContext($values) {
			$this->templateContext->context = $values;
		}

		public function addContext($name, $value) {
			$this->templateContext->context[$name] = $value;
		}

		public function registerFunction($name, $callback) {
			$this->templateContext->functions[$name] = $callback;
		}

		public function getContext() {
			return $this->templateContext->getContext();
		}

		public function getTemplate($template) {
			return $this->templateContext->getTemplate($template);
		}

		// For nesting contexts
		public function getChildFactory($resolver = null) {
			if ($resolver)
				$resolver->setFallback($this->resolver);
			else
				$resolver = $this->resolver;
			$tctx = new PhpAppTemplateContext($this->templateContext, $resolver);
			return new PhpAppTemplateFactory($resolver, $tctx);
		}

	}

	class PhpAppTemplateResolver {
		
		private $includePath;

		private $fallbackResolver;
		private $searchPath;

		private $fileCache = array();
		private $templateCache = array();

		public function __construct($searchPath, $fallbackResolver = null) {
			$this->searchPath = array_merge(array('.'),
				 $searchPath ? $searchPath : array());
			$this->fallbackResolver = $fallbackResolver;
		}

		public function findTemplate($name) {

			if (isset($this->templateCache[$name]))
				return $this->templateCache[$name];

			if ($name{0} == '/') {
				if (file_exists($name))
					return $name;
			} else {
				foreach ($this->searchPath as $spath) {

					$full = $this->findFileInPath($spath.'/'.$name);

					if ($full) {
						$this->templateCache[$name] = $full;
						return $full;
					}

				}
			}

			if ($this->fallbackResolver)
				return $this->fallbackResolver->findTemplate($name);

			return null;

		}

		public function fileExistsInPath($file) {

			if ($this->findFileInPath($file))
				return TRUE;

			return FALSE;

		}

		public function findFileInPath($file) {

			if (isset($this->fileCache[$file]))
				return $this->fileCache[$file];

			if ($file{0} == '/' && file_exists($file))
				return $file;

			if (!$this->includePath)
				$this->includePath = explode(':', ini_get('include_path'));

			foreach($this->includePath as $dir)
				if (file_exists($dir.'/'.$file)) {
					$this->fileCache[$file] = $dir.'/'.$file;
					return $dir.'/'.$file;
				}

			$this->fileCache[$file] = FALSE;
			return FALSE;

		}

		public function addPath($search) {
			$this->searchPath[] = $search;
		}

		public function prependPath($search) {
			array_unshift($this->searchPath, $search);
		}

		public function setFallback($resolver) {
			$this->fallbackResolver = $resolver;
		}

	}

	class PhpAppTemplateContext {

		public $context = array();
		public $resolver;
		public $functions = array();

		protected $parent;
		
		public function __construct($parent = null, $resolver = null) {
			if ($parent) {
				$this->parent = $parent;
				if (!$resolver)
					$this->resolver = new PhpAppTemplateResolver(null, $parent->resolver);
			}
			if ($resolver)
				$this->resolver = $resolver;
		}

		public function getContext() {
			if ($this->parent)
				return array_merge($this->parent->getContext(), $this->context);
			return $this->context;
		}

		public function getFunctions() {
			if ($this->parent)
				return array_merge($this->parent->getFunctions(), $this->functions);
			return $this->functions;
		}

		public function getTemplate($template) {
			$f = $this->resolver
				? $this->resolver->findTemplate($template)
				: $template;
			if ($f)
				return new PhpAppTemplate($f, $this);
			return null;
		}

		public function renderTemplate($template, $context = null) {
			$t = $this->getTemplate($template);
			$t->render($context);
		}

	}

	class PhpAppTemplate {
		
		protected $file;
		protected $functions = array();
		protected $context;
		protected $localContext;
		protected $templateContext;

		public function __construct($file, $templateContext = null) {

			$this->file = $file;
			$this->templateContext = $templateContext;

			if ($templateContext)
				$this->functions =  $templateContext->getFunctions();

		}

		public function render($context = null) {

			$this->localContext = $context ? $context : array();

			$this->context = $this->templateContext
				? array_merge($this->templateContext->getContext(), $this->localContext)
				: $this->localContext;

			if ($this->context)
				extract($this->context, EXTR_SKIP);
			
			$context =& $this->context;

			include($this->file);

		}

		public function fetch($context = null) {
			ob_start();
			$this->render($context);
			$contents = ob_get_contents();
			ob_end_clean();
			return $contents;
		}

		public function renderAsJS($context = null) {
			ob_start(array($this, 'convertToJS'), 1, TRUE);
			$this->render($context);
			ob_end_flush();
		}

		public function convertToJS($text) {

			$lines = explode("\n", $text);
			$js = '';

			$search = array("\r", "\\", "'", '<script', '</script');
			$replace = array("", "\\\\", "\\'", '<scr\' + \'ipt', '</scr\' + \'ipt');

			if ($lines)
				$last = array_pop($lines);

			foreach ($lines as $line)
				$js .= 'document.write(\''
					.str_replace($search, $replace, $line)
					."\\n');\n";

			// Last line is not blank; we didn't end on a newline, 
			// so don't spit one out
			if ($last)
				$js .= 'document.write(\''
					.str_replace($search, $replace, $last)
					."');\n";

			return $js;

		}

		public function __get($name) {

			if (isset($this->context[$name]))
				return $this->context[$name];

			return null;

		}

		public function incl($template, $context = null) {

			if ($this->templateContext && $this->templateContext->resolver) {
				
				$incFile = $this->templateContext->resolver->findTemplate($template);
				if ($incFile) {
					$tpl = new PhpAppTemplate($incFile, $this->templateContext);
					$tpl->render($context
						? array_merge($this->localContext, $context)
						: $this->localContext);
				} else {
					throw new Exception("Template not found: $template");
				}

			} else {

				$tpl = new PhpAppTemplate($template);
				$tpl->render($context
					? array_merge($this->context, context)
					: $this->context);

			}

		}

		// URL parameter modifier
		public function selfurl($name, $value = null, $addSeparator = false) {

			$parts = explode('?', $_SERVER['REQUEST_URI']);
			$path = $parts[0].'?';
			 $query = count($parts) > 1 ? $this->parseQS($parts[1]) : array();

			if ($value === null)
				unset($query[$name]);
			else
				//$query[$name] = $value ? $value : 0;
				$query[$name] = $value;

			foreach ($query as $k => $v) {
				if (is_array($v))
					foreach ($v as $p)
						$path .= rawurlencode($k).'[]='.rawurlencode($p).'&';
				else
					$path .= rawurlencode($k).'='.rawurlencode($v).'&';
			}

			if ($addSeparator)
				print $path;
			else
				print substr($path, 0, -1);
		}

		// Form parameter modifier
		public function selfform($name = null, $value = null) {

			$query = $this->parseQS($_SERVER['QUERY_STRING']);
			if ($value === null)
				unset($query[$name]);
			else
				//$query[$name] = $value ? $value : 0;
				$query[$name] = $value;

			foreach ($query as $k => $v) {
				if (is_array($v))
					foreach ($v as $p)
						print '<input type="hidden" name="'.rawurlencode($k).'[]" value="'.rawurlencode($p).'"/>'."\n";
				else
					print '<input type="hidden" name="'.rawurlencode($k).'" value="'.rawurlencode($v).'"/>'."\n";
			}
		}

		private function parseQS($qs) {

			$params = array();
			
			if (!$qs)
				return $params;

			$pairs = explode('&', $qs);

			foreach($pairs as $p) {
				$field = explode('=', $p);
				$k = $field[0];
				$v = isset($field[1]) ? $field[1] : null;
				$params[urldecode($k)] = urldecode($v);
			}

			return $params;

		}

		public function toString($variable) {
			if (is_object($variable) && method_exists($variable, '__toString'))
				return $variable->__toString();
			return strval($variable);
		}

		public function yesNo($variable) {
			if ($variable)
				return 'Yes';
			return 'No';
		}

		public function pluralize($string) {
			$last = substr($string, -1);
			if ($last == 'y') $string = substr($string, 0, -1).'ies';
			elseif ($last == 's') $string .= 'es';
			else $string .= 's';
			return $string;
		}

		public function singularize($string) {

			if (substr($string, -1) == 's') {
				// Make singular
				if (substr($string, -3) == 'ies')
					$name = substr($string, 0, -3).'y';
				elseif (substr($string, -3) == 'ses')
					$name = substr($string, 0, -2);
				else
					$name = substr($string, 0, -1);
			}

			$parts = explode('_', $name);

			foreach ($parts as &$part)
				$part = ucfirst($part);

			return implode(' ', $parts);

		}

		public function defaultValue($value, $default) {
			if ($value)
				return $value;
			return $default;
		}

		public function priceFormat($value, $zeroDefault = null, $sign = true) {
			if ($value == 0 && $zeroDefault)
				return $zeroDefault;
			else {
				if ($sign)
					return sprintf('%+0.2f', $value);
				else
					return sprintf('%0.2f', $value);
			}
		}

		public function truncate($value, $length, $suffix = '...', $boundary = true) {

			if (!isset($value{$length}))
				return $value;

			$lastChar = $length - strlen($suffix);
			if ($boundary) {
				while ($lastChar > 0 && !ctype_space($value{$lastChar+1}))
					$lastChar--;
				$lastChar++;
			}

			// No spaces, default to original length
			if ($lastChar == 0)
				$lastChar = $length - strlen($suffix);

			return substr($value, 0, $lastChar);
			
		}

		public function __call($method, $args) {
			if (isset($this->functions[$method]))
				return call_user_func_array($this->functions[$method], $args);
			trigger_error('Unknown function: '.$method, E_USER_WARNING);
		}

	}
