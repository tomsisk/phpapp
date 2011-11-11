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

	require_once('Module.class.php');
	require_once('PhpAppTemplate.class.php');

	class AdminController {

		public $app;
		public $modules;
		public $prefix;
		public $base;
		public $baseUrl;
		public $mediaRoot;
		public $appName;
		public $baseFilter;
		public $filters;
		public $filterObjects;
		public $plugins = array();
		public $forceAccessFilter = false;

		public $templateContext;

		private $modelCache = array();

		public function __construct(&$app, $prefix = '', $public = false) {

			$this->app =& $app;
			$this->prefix = $prefix;

			$this->base = $app->getAppRoot().$prefix;
			$this->baseUrl = $app->getBaseUrl().$prefix;
			$this->mediaRoot = $this->baseUrl.'/media';
			$this->modules = array();
			$this->appName = $app->getName();
			$this->filters = $app->getUserVar('adminFilters');
			if (!$this->filters) $this->filters = array();

			$this->forceAccessFilter = $public;

			$mods = $app->getModules();

			$templatePaths = array(dirname(__FILE__).'/templates');
			foreach ($mods as $name => $mod)
				$templatePaths[] = $mod.'/templates';

			$resolver = new PhpAppTemplateResolver($templatePaths);
			$this->templateContext = new PhpAppTemplateContext(null, $resolver);

			$jsIncludes = array();
			$cssIncludes = array();

			foreach ($mods as $name => $mod) {

				if (is_numeric($name))
					$name = basename($mod);

				$moduleConfig = $mod.'/module.php';

				if ($this->fileExistsInPath($moduleConfig)) {
					$module = new Module($name, $this);
					$module->baseDir = $mod;
					include($moduleConfig);
					$this->modules[$name] = $module;

					foreach ($module->globalJS as $js)
						$jsIncludes[] = $this->mediaRoot.'/modules/'.$module->id.'/'.$js;
					foreach ($module->globalCSS as $css)
						$cssIncludes[] = $this->mediaRoot.'/modules/'.$module->id.'/'.$css;
				}

			}

			$this->templateContext->context = array(
				'app' => $this->app,
				'admin' => $this,
				'user' => $this->app->getUser(),
				'debug' => isset($this->app->config['debug']) && $this->app->config['debug'],
				'modules' => $this->modules,
				'moduleGroups' => $this->app->config['moduleGroups'],
				'host' => $_SERVER['HTTP_HOST'],
				'defScripts' => $jsIncludes,
				'defStylesheets' => $cssIncludes,
				'popup' => false
				);
			if (isset($this->app->config['public_host']))
				$this->templateContext->context['publichost'] =
					$this->app->config['public_host'];

		}

		public function fileExistsInPath($file) {

			return $this->app->fileExistsInPath($file);
		}

		public function findFileInPath($file) {

			return $this->app->findFileInPath($file);

		}

		public function registerPlugin($name, $plugin) {
			$this->plugins[$name] = $plugin;
		}

		public function getPlugin($name) {
			if (isset($this->plugins[$name]))
				return $this->plugins[$name];
			return null;
		}

		public function getModules() {
			return $this->modules;
		}

		public function getModule($name) {
			return $this->modules[$name];
		}

		public function setBaseFilter($model) {
			$this->baseFilter = $model;
		}

		public function getBaseFilter() {
			return $this->baseFilter;
		}

		public function addFilter($model, $id) {
			
			if ($this->baseFilter && $model == $this->baseFilter->getQueryName()) {
				$this->filters = array();
				$this->filterObjects = array();
			}

			if ($id)
				$this->filters[$model] = $id;
			else
				unset($this->filters[$model]);

			unset($this->filterObjects[$model]);

			if ($this->baseFilter && $model == $this->baseFilter->getQueryName()) {
				// Load single-object master filters of submodules
				foreach ($this->modules as $mod) {
					if ($mod->master && $mod->master != $this->baseFilter) {
						$mod->master->flush();
						$query = $mod->master->getQuery()->all();
						if ($query->count() > 0)
							$this->filters[$mod->master->getQueryName()] = $query->one()->pk;
					}
				}
			}

			$this->app->setUserVar('adminFilters', $this->filters);
		}

		public function getFilter($name) {
			if (array_key_exists($name, $this->filters))
				return $this->filters[$name];
			return null;
		}

		public function getFilterObjectByName($name) {
			return $this->getFilterObject($this->findModelAdmin($name));
		}

		public function getFilterObject($model) {

			$name = $model->getQueryName();

			if (!isset($this->filterObjects[$name])
					&& isset($this->filters[$name])) {
				$id = $this->filters[$name];
				if ($id)
					try {
						$this->filterObjects[$name] = $model->getQuery()->get($id);
					} catch (DoesNotExistException $dne) {
						return null;
					}
			}

			return $this->filterObjects[$name];

		}

		public function findModelAdmin($model) {
			$modelName = is_object($model) ? get_class($model) : $model;
			if (!array_key_exists($modelName, $this->modelCache)) {
				foreach ($this->modules as $mod) {
					foreach ($mod->modelAdmins as $ma) {
						if (get_class($ma->getPrototype()) == $modelName) {
							$this->modelCache[$modelName] = $ma;
							return $ma;
						}
					}
				}
			}
			if (isset($this->modelCache[$modelName]))
				return $this->modelCache[$modelName];
			return null;
		}

		// Shortcut function to simplify request handler script
		public function handleRequest() {

			$parts = explode('?', $_SERVER['REQUEST_URI']);
			$path = $parts[0];
			$method = strtolower($_SERVER['REQUEST_METHOD']);
			$params = array_merge($_GET, $_POST, $_FILES);

			$this->execute($path, $params, $method);

		}

		public function execute($path, $params, $method = 'get') {
			$start = microtime(true);
			$this->app->query->begin();
			$path = explode('/', substr($path, strlen($this->baseUrl)+1));
			if (!$path[sizeof($path)-1]) array_pop($path);

			if (!$this->app->isLoggedIn()) {
				if (count($path) > 0 && $path[0] == 'media') {
					// Pass
				} elseif (count($path) > 1 && $path[0] == 'account' && $path[1] == 'login') {
					// Pass
				} else {
					$this->relativeRedirect('/account/login?redirect='.rawurlencode($_SERVER['REQUEST_URI']));
					return;
				}
			} elseif (!$this->app->getUser()->staff) {
				$this->app->logout();
				unset($this->templateContext->context['user']);
				if (count($path) > 0 && $path[0] == 'media') {
					// Pass
				} elseif (count($path) > 1 && $path[0] == 'account' && $path[1] == 'login') {
					// Pass
				} else {
					$this->relativeRedirect('/account/login?redirect='.rawurlencode($_SERVER['REQUEST_URI']));
					return;
				}
			}

			foreach ($params as $k => $v)
				if (strpos($k, '__') !== false) {
					$params[str_replace('__', '.', $k)] = $v;
					unset($params[$k]);
				}

			try {
				$handler = array_shift($path);
				if ($handler == 'account') {
					header('Content-Type: text/html; charset=UTF-8');
					$this->doAccountAction($path, $params, $method);
				} elseif ($handler == 'media') {
					$this->sendMedia($path, $params, $method);
				} elseif ($handler == 'thumb') {
					$this->sendThumbnail($path, $params, $method);
				} elseif (!$handler || $handler == 'modules') {
					header('Content-Type: text/html; charset=UTF-8');
					if (sizeof($path) == 0) {
						if (isset($this->app->config['defaultModule']))
							// Default module
							$this->relativeRedirect('/modules/'.$this->app->config['defaultModule'].'/');
						else
							// Module index
							$this->renderTemplate('module_list.tpl', array('modules' => $this->modules));
					} else {
						$moduleid = array_shift($path);
						$module = $this->modules[$moduleid];
						if ($module)
							$module->execute($path, $params, $method);
						else
							throw new NotFoundException('The requested page does not exist.');
					}
				} else {
					throw new NotFoundException('The requested page does not exist.');
				}
			} catch (NotFoundException $nfe) {
				$context = array('modules' => $this->modules,
						'title' => 'Page Not Found',
						'error' => $nfe->getMessage());
				$this->renderTemplate('error.tpl', $context);
			} catch (AccessDeniedException $ade) {
				$context = array_merge(array('modules' => $this->modules,
						'title' => 'Access Denied',
						'error' => $ade->getMessage()),
					$ade->getContext());
				$this->renderTemplate('access_denied.tpl', $context);
			} catch (Exception $e) {
				$context = array('modules' => $this->modules,
						'title' => 'Error',
						'exception' => $e,
						'error' => $e->getMessage());
				$this->renderTemplate('error.tpl', $context);
			}
			$this->app->query->complete();
		}

		public function doAccountAction($path, $params, $method = 'get') {
			$action = array_shift($path);
			switch($action) {
				case 'login':
					$redirect = null;
					if (isset($params['redirect']))
						$redirect = $params['redirect'];
					if (substr($redirect, 0, 8 + strlen($this->prefix)) == $this->prefix.'/account')
						$redirect = null;
					if ($method == 'post') {
						if ($this->app->query->User->model->_fields['password'] instanceof PasswordField)
							$params['password'] = md5($params['password']);
						if (($user = $this->app->login($params['username'], $params['password'], isset($params['remember']) ? true : false)) && $this->app->getUser()->staff) {
							if ($this->baseFilter) {
								$query = $this->baseFilter->getQuery()->all();
								if ($query->count() == 1)
									$this->addFilter($this->baseFilter->getQueryName(), $query->one()->pk);
							}
							if ($params['redirect'])
								header('Location: '.$redirect);
							else
								$this->relativeRedirect('/');
						} else {
							$this->renderTemplate('account/login.tpl', array('redirect' => $redirect, 'error' => '<b>Login Failed</b><br />Incorrect username or password.'));
						}
					} else {
						$this->renderTemplate('account/login.tpl', array('redirect' => $redirect));
					}
					break;
				case 'logout':
					$this->app->logout();
					$this->relativeRedirect('/account/login');
					break;
				case 'heartbeat':
					header('Content-Type: image/gif');
					// Invisible image bytes
					echo "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00"
						."\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00"
						."\x00\x02\x02\x44\x01\x00\x3b";
					break;
				case 'save':
					$user = $this->app->getUser();
					$pass1 = $params['password1'];
					$pass2 = $params['password2'];
					if ($pass1 || $pass2) {
						if ($pass1 == $pass2) {
							if ($this->app->query->User->model->_fields['password'] instanceof PasswordField)
								$user['password'] = md5($pass1);
							else
								$user['password'] = $pass1;
						}
						else $user->addError('Passwords do not match');
					}
					$user['email'] = $params['email'];
					$user['firstname'] = $params['firstname'];
					$user['lastname'] = $params['lastname'];
					try {
						$user->save();
						$this->app->setUser($user);
						$this->relativeRedirect('/account/saved');
					} catch (ValidationException $ve) {
						$context = array('errors' => $user->getErrors(),
							'fieldErrors' => $user->getFieldErrors());
						$this->renderTemplate('account/account.tpl', $context);
					}
					break;
				case 'saved':
					$this->renderTemplate('account/saved.tpl');
					break;
				case 'account':
				case null:
					$this->renderTemplate('account/account.tpl');
					break;
				default:
					throw new NotFoundException('The requested page does not exist.');
			}
		}

		public function sendMedia($path, $params, $method = 'get') {
			$overrides = array(
				'.css' => 'text/css',
				'.html' => 'text/html',
				'.js' => 'text/javascript',
				'.css' => 'text/css',
				'.png' => 'image/png',
				'.jpg' => 'image/jpeg',
				'.jpeg' => 'image/jpeg',
				'.gif' => 'image/gif'
			);
			$relpath = '/'.implode('/', $path);
			$mediapath = dirname(__FILE__).'/media'.$relpath;
			if (file_exists($mediapath) && is_file($mediapath)) {
			
				if (array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)) {
					$lastmod = filemtime($mediapath);
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

				$ext = substr($mediapath, strrpos($mediapath, '.'));
				if ($ext == '.php') {
					include($mediapath);
				} else {
					if (array_key_exists($ext, $overrides))
						$mimetype = $overrides[$ext];
					else
						//$mimetype = mime_content_type($mediapath);
						$mimetype = 'text/plain';
					header('Pragma: phpsucks');
					header('Cache-Control: public');
					header('Content-type: '.$mimetype);
					header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($mediapath)).' GMT');
					if ($fh = fopen($mediapath, 'r')) {
						while (!feof($fh))
							echo fread($fh, 8192);
						fclose($fh);
					}
				}
			} else {
				throw new NotFoundException();
			}
		}

		public function sendThumbnail($path, $params, $method = 'get') {
			$src = implode('/', $path);
			if ($src{0} == '/') {
				if ($this->app->config['public_host'])
					$src = 'http://'.$this->app->config['public_host'].$src;
				else
					$src = 'http://localhost'.$src;
			}
			$thumbpath = $this->getThumbnailPath($src);
			if ($thumbpath) {
				header('Content-Type: '.mime_content_type($thumbpath));
				header('Content-Length: '.filesize($thumbpath));
				echo file_get_contents($thumbpath);
			} else {
				echo 'Image not found.';
			}
		}

		private function getThumbnailPath($url) {
			$cachedir = $this->app->getCachePath('thumbnails', true);
			$thumbfile = $cachedir.'/thumb-'.md5($url);
			if (!file_exists($thumbfile) || (time() - filemtime($thumbfile) > 60)) {
				$cachefile = $cachedir.'/cache-'.md5($url);
				file_put_contents($cachefile, file_get_contents($url));
				$imginfo = getimagesize($cachefile);
				$mime = $imginfo['mime'];
				$image = null;
				switch($mime) {
					case 'image/jpeg':
					case 'image/pjpeg':
						$image = imagecreatefromjpeg($cachefile);
						break;
					case 'image/png':
					case 'image/x-png':
						$image = imagecreatefrompng($cachefile);
						imagealphablending($image, false);
						imagesavealpha($image, true);
						break;
					case 'image/gif':
						$image = imagecreatefromgif($cachefile);
						break;
					default:
						return $cachefile;
				}
				if ($image) {
					$this->resizeImage($image, 100, 100, $thumbfile, 80, $mime);
					imagedestroy($image);
				}
			}
			return file_exists($thumbfile) ? $thumbfile : null;
		}
		
		private function resizeImage($im, $maxwidth, $maxheight, $urlandname, $comp, $imagetype) {
			$width = imagesx($im);
			$height = imagesy($im);
			if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight)) {
				if($maxwidth && $width > $maxwidth) {
					$widthratio = $maxwidth/$width;
					$resizewidth = true;
				} else
					$resizewidth=false;

				if($maxheight && $height > $maxheight) {
					$heightratio = $maxheight/$height;
					$resizeheight=true;
				} else
					$resizeheight=false;

				if($resizewidth && $resizeheight) {
					if($widthratio < $heightratio) $ratio = $widthratio;
					else $ratio = $heightratio;
				} elseif($resizewidth) {
					$ratio = $widthratio;
				} elseif($resizeheight) {
					$ratio = $heightratio;
				}

				$newwidth = $width * $ratio;
				$newheight = $height * $ratio;

				if(function_exists('imagecopyresampled') && $imagetype !='image/gif')
					$newim = imagecreatetruecolor($newwidth, $newheight);
				else
					$newim = imagecreate($newwidth, $newheight);

				// additional processing for png / gif transparencies (credit to Dirk Bohl)
				if($imagetype == 'image/x-png' || $imagetype == 'image/png') {
					imagealphablending($newim, false);
					imagesavealpha($newim, true);
				} elseif($imagetype == 'image/gif') {
					$originaltransparentcolor = imagecolortransparent( $im );
					if($originaltransparentcolor >= 0 && $originaltransparentcolor < imagecolorstotal($im)) {
						$transparentcolor = imagecolorsforindex( $im, $originaltransparentcolor );
						$newtransparentcolor = imagecolorallocate($newim,$transparentcolor['red'],$transparentcolor['green'],$transparentcolor['blue']);
						imagefill( $newim, 0, 0, $newtransparentcolor );
						imagecolortransparent( $newim, $newtransparentcolor );
					}
				}

				imagecopyresampled($newim, $im, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

				if($imagetype == 'image/pjpeg' || $imagetype == 'image/jpeg') {
					imagejpeg ($newim,$urlandname,$comp);
				} elseif($imagetype == 'image/x-png' || $imagetype == 'image/png') {
					imagepng ($newim,$urlandname,substr($comp,0,1));
				} elseif($imagetype == 'image/gif') {
					imagegif ($newim,$urlandname);
				}
				imagedestroy ($newim);
			} else {
				if($imagetype == 'image/pjpeg' || $imagetype == 'image/jpeg') {
					imagejpeg ($im,$urlandname,$comp);
				} elseif($imagetype == 'image/x-png' || $imagetype == 'image/png') {
					imagepng ($im,$urlandname,substr($comp,0,1));
				} elseif($imagetype == 'image/gif') {
					imagegif ($im,$urlandname);
				}
			}
		}

		public function relativeRedirect($url) {
			$this->app->relativeRedirect($this->prefix.$url);
		}

		public function relativeUrl($url) {
			return $this->app->relativeUrl($this->prefix.$url);
		}
		
		public function checkAction($action, $module = null, $type = null) {
			return $this->app->checkAction($action, $module, $type);
		}

		public function checkAccess($module = null, $type = null, $instance = null) {
			return $this->app->checkAccess($module, $type, $instance);
		}

		public function checkPermission($module, $type, $action, $instance = 'ALL') {
			return $this->app->checkPermission($action, $module, $type, $instance);
		}

		public function getQuery($name) {
			if ($ma = $this->findModelAdmin($name))
				return $ma->getQuery();
			else
				return $this->app->query->$name;
		}

		public function getAdmin() {
			return $this;
		}

		public function getApplication() {
			return $this->app;
		}

		public function renderTemplate($template, $context = array(), $basetemplate = null) {

			$base = $this->templateContext->getTemplate(
				$basetemplate ? $basetemplate : 'base.tpl');

			if (!$context)
				$context = array();
			$context['_template'] = $template;

			$base->render($context);

		}

		public function fetchTemplate($template, $context = array()) {
			$tpl = $this->templateContext->getTemplate($template);
			return $tpl->fetch($context);
		}

		public function getUser() {
			return $this->app->getUser();
		}

		public function getMediaRoot() {
			return $this->mediaRoot;
		}

	}

	class NotFoundException extends Exception {}
	class AccessDeniedException extends Exception {
		private $context;
		public function setContext($context) {
			$this->context = $context;
		}
		public function getContext() {
			return $this->context;
		}
	}
