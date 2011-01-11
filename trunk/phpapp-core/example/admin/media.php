<?php
	// Standalone media handler
	// NOTE: Requests are routed to this controller via the .htaccess file in this directory
	$prefix = '/media';

	$config = array();
	$root = dirname(dirname(__FILE__));
	require_once($root.'/config/default.inc.php');
	if (file_exists($root.'/local.inc.php'))
		require_once($root.'/local.inc.php');

	if (isset($config['admin_path']))
		$prefix = $config['admin_path'].$prefix;

	$parts = explode('?', $_SERVER['REQUEST_URI']);
	$path = substr($parts[0], strlen($prefix)+1);

	$include = null;
	$modpath = null;
	if (substr($path, 0, 8) == 'modules/') {
		list($junk, $module, $path) = explode('/', $path, 3);
		if (array_key_exists($module, $config['modules'])) {
			$modpath = $config['modules'][$module];
			$include = $modpath.'/media/'.$path;
		}
	} else {
		$include = 'phpapp-core/media/'.$path;
	}

	if (includePath($include))
		exit;
	
	// Try a parent module - a bit sloppy, but
	// we usually won't get to this code
	$modconfig = findFile($modpath.'/module.php');
	if ($modconfig) {
		$module = new FakeModule();
		include($modconfig);
		if ($module->extends) {
			$include = $module->extends.'/media/'.$path;
			if (includePath($include))
				exit;
		}
	}

	# Specified media not found
	header('HTTP/1.1 404 Not Found');
	echo '<h1>404 Not Found</h1>';
	exit;

	function includePath($path) {
		if ($path) {
			$file = findFile($path);
			if ($file) {
				sendFile($file);
				return true;
			}
		}
	}

	function findFile($name) {
		if ($name{0} == '/') {
			if (file_exists($name))
				return $name;
		} else {
			$incPaths = explode(':', ini_get('include_path'));
			foreach ($incPaths as $ip) {
				$file = $ip.'/'.$name;
				if (file_exists($file))
					return $file;
			}
		}
		return null;
	}

	function sendFile($file) {
		$overrides = array(
			'.css' => 'text/css',
			'.html' => 'text/html',
			'.htm' => 'text/html',
			'.js' => 'text/javascript',
			'.css' => 'text/css',
			'.png' => 'image/png',
			'.jpg' => 'image/jpeg',
			'.jpeg' => 'image/jpeg',
			'.gif' => 'image/gif'
		);
		if (file_exists($file) && is_file($file)) {
		
			if (array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)) {
				$lastmod = filemtime($file);
				$lastreq = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
				if (array_key_exists('HTTP_CACHE_CONTROL', $_SERVER)) {
					$cc = $_SERVER['HTTP_CACHE_CONTROL'];
					// max-age just specifies to validate against modified time
					//if ($cc == 'max-age=0' || $cc = 'no-cache')
					if ($cc == 'no-cache')
						$lastreq = 0;
				}
				if ($lastmod <= $lastreq) {
					header('HTTP/1.1 304 Not Modified');
					header('Pragma: phpsucks');
					header('Cache-Control: public');
					exit;
				}
			}

			$ext = substr($file, strrpos($file, '.'));
			if ($ext == '.php') {
				include($file);
			} else {
				if (array_key_exists($ext, $overrides))
					$mimetype = $overrides[$ext];
				else
					//$mimetype = mime_content_type($file);
					$mimetype = 'text/plain';
				header('Pragma: phpsucks');
				header('Cache-Control: public');
				header('Content-type: '.$mimetype);
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');
				if ($fh = fopen($file, 'r')) {
					while (!feof($fh))
						echo fread($fh, 8192);
					fclose($fh);
				}
			}
		} else {
			throw new NotFoundException();
		}
	}

	class FakeModule {

		public $extends;

		public function __call($name, $args) {
			if ($name == 'extend') {
				$this->extends = $args[0];
			}
		}

		public function __set($name, $val) {
		}

	}
