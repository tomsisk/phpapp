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

	require_once('adodb/adodb.inc.php');
	require_once('modelquery2/QueryFactory.class.php');
	require_once('Mail.php');
	require_once('Mail/mime.php');
	require_once('smarty/Smarty.class.php');

	class Application {
		
		protected $id;
		protected $name;
		protected $config;
		protected $smarty;
		protected $isRendering = false;
		protected $permCache = array();
		protected $fileCache = array();
		protected $query;
		protected $userQuery;
		protected $user;
		protected $preferences;
		protected $authKey = 'VU@$FDSJLK7*(&sdf0989SD8^&fd@#$';
		public $startTime;
		protected $lastDebug;
		protected $appRoot;
		protected $baseUrl = '';
		protected $modules = array();
		protected $path;
		protected $siteTemplate = 'templates/default.tpl';
		public $logs = array('debug' => array(),
						'info' => array(),
						'warn' => array(),
						'error' => array());

		public function __construct($name, $appRoot = null, $configDir = null, $localConfig = null) {


			$this->id = md5(strtolower(str_replace(' ', '_', $name)));
			$this->name = $name;
			$this->appRoot = $appRoot ? $appRoot : $_SERVER['DOCUMENT_ROOT'];

			$config = array();

			// Check domain-specific config
			if ($configDir) {

				$host = array_key_exists('HTTP_HOST', $_SERVER) ? $_SERVER['HTTP_HOST'] : null;
				if (strpos($host, ':')) $host = preg_replace('/:[^:]*/', '', $host);

				$siteconfig = $configDir.'/'.$host.'.inc.php';
				$globalConfig = file_exists($siteconfig) ? $siteconfig : $configDir.'/default.inc.php';

				if ($globalConfig && file_exists($globalConfig))
					include($globalConfig);

			}

			if ($localConfig && file_exists($localConfig))
				include($localConfig);

			$this->config = $config;

			if (isset($config['base_url']))
				$this->baseUrl = $config['base_url'];
			elseif ($_SERVER['DOCUMENT_ROOT'] && strstr($appRoot, $_SERVER['DOCUMENT_ROOT']))
				$this->baseUrl = substr($appRoot, strlen($_SERVER['DOCUMENT_ROOT']));
			if (substr($this->baseUrl, -1) == '/')
				$this->baseUrl = substr($this->baseUrl, 0, -1);

			if ($config['modules']) {

				$this->modules = $config['modules'];

				$db= ADONewConnection('mysqlt');
				$db->setFetchMode(ADODB_FETCH_ASSOC);
				$db->autoCommit = false;
				$db->Connect($this->config['db_host'], $this->config['db_user'], $this->config['db_pass'], $this->config['db_name']);

				$roots = array();
				foreach ($config['modules'] as $mdir)
					$roots[] = $mdir.'/models';

				if (isset($config['models']))
					foreach ($config['models'] as $mdir)
						$roots[] = $mdir;

				$qfSrc = $this->getAppVar($this->id.'_queryFactory');
				if ($qfSrc) {
					QueryFactory::preloadModelClasses($roots);
					$this->query = unserialize($qfSrc);
					$this->query->setConnection($db);
				} else {
					$this->query = new QueryFactory($db, $roots);
					$this->query->precacheModels();
					$this->setAppVar($this->id.'_queryFactory', serialize($this->query));
				}

				if (isset($config['debug']) && $config['debug'])
					$this->query->logger = $this;
				
				if (isset($config['user_class']) && $config['user_class'])
					$this->userQuery = $this->query->$config['user_class'];
				else
					$this->userQuery = $this->query->User;

			}
		}

		public function start($autoLogin = true) {
			// Load classes that are likely to be cached in session
			$this->query->User;
			$this->startTime = microtime(true);
			session_start();
			if ($autoLogin)
				$this->autoLogin();
		}

		public function stop() {
			$this->logout();
		}

		public function getName() {
			return $this->name;
		}

		public function getModules() {
			return $this->modules;
		}

		public function setBaseUrl($url) {
			$this->baseUrl = $url;
		}

		public function getBaseUrl() {
			return $this->baseUrl;
		}

		public function getAppRoot() {
			return $this->appRoot;
		}

		public function getUser() {
			if (!$this->user) {
				$userid = $this->getUserId();
				if ($userid)
					$this->user = $this->query->User->get($userid);
			}
			return $this->user;
		}

		public function getUserId() {
			if (isset($_SESSION[$this->id])
					&& isset($_SESSION[$this->id]['user']))
				return $_SESSION[$this->id]['user'];
			return null;
		}

		public function setUser(&$user) {
			$curruser = $this->getUserId();
			if ($curruser && (!$user || $user->pk != $curruser))
				$this->logout();
			if ($user)
				$this->_createSession($user);
		}

		public function setSessionVar($name, $value) {
			if (!array_key_exists('vars', $_SESSION))
				$_SESSION['vars'] = array();
			$_SESSION['vars'][$name] = $value;
		}

		public function getSessionVar($name, $default = null) {
			if (array_key_exists('vars', $_SESSION) &&
					array_key_exists($name, $_SESSION['vars']))
				return $_SESSION['vars'][$name];
			return $default;
		}

		public function setUserVar($name, $value) {
			if (!array_key_exists($this->id, $_SESSION))
				return null;
			if (!array_key_exists('vars', $_SESSION[$this->id]))
				$_SESSION[$this->id]['vars'] = array();
			$_SESSION[$this->id]['vars'][$name] = $value;
		}

		public function getUserVar($name, $default = null) {
			if (!array_key_exists($this->id, $_SESSION))
				return null;
			if (array_key_exists('vars', $_SESSION[$this->id]) &&
					array_key_exists($name, $_SESSION[$this->id]['vars']))
				return $_SESSION[$this->id]['vars'][$name];
			return $default;
		}

		public function setAppVar($name, $value = null) {
			if (function_exists('xcache_set') && !defined('XCACHE_DISABLE')) {
				if (!$value)
					xcache_unset($this->name . '_' . $name);
				else
					xcache_set($this->name . '_' . $name, $value);
			}
		}

		public function getAppVar($name) {
			if (function_exists('xcache_get') && !defined('XCACHE_DISABLE')) {
				if (xcache_isset($this->name . '_' . $name))
					return xcache_get($this->name . '_' . $name);
			}
			return null;
		}

		public function getCachePath($directory = null, $create = false) {
			if (!array_key_exists('cache_dir', $this->config))
				$this->config['cache_dir'] = '/tmp/cache-'.$this->id;
			if (!is_dir($this->config['cache_dir']))
				mkdirs($this->config['cache_dir']);
			if ($directory) {
				$path = $this->config['cache_dir'].'/'.$directory;
				if (!file_exists($path) && $create)
					mkdirs($path);
				if (!is_dir($path))
					trigger_error('Cache directory does not exists and could not be created: ' . $path, E_USER_WARNING);
				else
					return $path;
			} else {
				return $this->config['cache_dir'];
			}
		}

		public function getCacheFile($file) {
			$path = $this->getCachePath('download', true).'/'.$file;
			if (!file_exists($path)
					&& array_key_exists('cache_files', $this->config)
					&& array_key_exists($file, $this->config['cache_files']))
				$this->fetchCacheFile($path, $this->config['cache_files'][$file]);
			if (file_exists($path))
				return $path;
			return null;
		}

		public function fetchCacheFile($cachedfile, $url) {
			file_put_contents($cachedfile, fopen($url, 'r'));
		}

		public function &getPreferences($reload = false) {
			if (!$this->preferences || $reload) {
				if ($this->isLoggedIn()) {
					$up = $this->query->UserPreference;
					$this->preferences = $up->filter('user', $this->getUserId())->select();
				}
			}
			return $this->preferences;
		}

		public function getPreference($name, $defaultval = null) {
			if ($this->isLoggedIn()) {
				$prefs =& $this->getPreferences();
				if ($prefs)
					foreach ($prefs as $pref)
						if ($pref['pref_name'] == $name) return $pref['pref_value'];
			}
			return $defaultval;
		}

		public function setPreference($name, $value) {
			if ($this->isLoggedIn()) {
				$prefs =& $this->getPreferences();
				$user =& $this->getUser();
				$up = $this->query->UserPreference;
				$newpref = $up->create(array('pref_name' => $name, 'user' => $user->pk));
				foreach ($prefs as $pref) {
					if ($pref['pref_name'] == $name) {
						$newpref = $pref;
						break;
					}
				}
				$newpref['pref_value'] = $value;
				return $newpref->save();
			}
			return false;
		}

		public function clearPreference($name) {
			if ($this->isLoggedIn()) {
				foreach ($prefs as $pref)
					if ($pref['pref_name'] == $name)
						return $pref->delete();
			}
			return false;
		}

		public function getPermissions() {
			if (isset($_SESSION[$this->id]) && isset($_SESSION[$this->id]['permissions']))
				return $_SESSION[$this->id]['permissions'];
			return null;
		} 

		public function checkModule($module) {
			$perms = $this->getPermissions();
			return array_key_exists($module, $perms) || array_key_exists('ALL', $perms);
		}

		public function checkAction($action, $module = null, $type = null) {
			$perms = $this->getPermissions();
			if ($module) {
				$perms = array_merge_recursive(isset($perms[$module]) ? $perms[$module] : array(), isset($perms['ALL']) ? $perms['ALL'] : array());
				if ($perms && $type)
					$perms = array_merge_recursive(isset($perms[$type]) ? $perms[$type] : array(), isset($perms['ALL']) ? $perms['ALL'] : array());
			}
			$actions = $this->getLeaves($perms);
			return in_array($action, $actions) || in_array('ALL', $actions);
		}

		private function getLeaves($tree) {
			$leaves = array();
			foreach ($tree as $branch) {
				if (is_array($branch))
					$leaves = array_unique(array_merge($leaves, $this->getLeaves($branch)));
				else
					$leaves[] = $branch;
			}
			return $leaves;
		}

		public function checkAccess($module = null, $type = null, $instance = null) {
			$perms = $this->getPermissions();
			if ($module) {
				$perms = array_merge_recursive(isset($perms[$module]) ? $perms[$module] : array(), isset($perms['ALL']) ? $perms['ALL'] : array());
				if ($perms && $type) {
					$perms = array_merge_recursive(isset($perms[$type]) ? $perms[$type] : array(), isset($perms['ALL']) ? $perms['ALL'] : array());
					if ($perms && $instance) {
						$perms = array_merge_recursive(isset($perms[$instance]) ? $perms[$instance] : array(), isset($perms['ALL']) ? $perms['ALL'] : array());
					}
				}
			}
			return $perms && sizeof($perms) > 0;
		}

		public function checkPermission($action, $module = 'ALL', $type = 'ALL', $instance = 'ALL') {
			// Recursively check for permissions
			$allowed = $this->_checkObjectPermission($action);
			if (!$allowed) $allowed = $this->_checkObjectPermission($action, $module);
			if (!$allowed) $allowed = $this->_checkObjectPermission($action, $module, $type);
			if (!$allowed) {
				// Allowed checking multiple objects
				if (is_array($instance)) {
					$allowed = true;
					foreach($instance as $inst)
						$allowed = $allowed && $this->_checkObjectPermission($action, $module, $type, $inst);
				} else {
					$allowed = $this->_checkObjectPermission($action, $module, $type, $instance);
				}
			}
			return $allowed;
		}
		
		public function checkMultiPermission($action = null, $type = null, $module = null, $instance = null) {

			$action = $action ? strtoupper($action) : 'ALL';
			$type = $type ? strtoupper($type) : 'ALL';
			$module = $module ? strtoupper($module) : 'ALL';
			$instance = $instance ? $instance : 'ALL';

			$types = array($type);
			$typemod = '&';

			if (strpos($type, '|') !== false) {
				$types = explode('|', $type);
				$typemod = '|';
			} elseif (strpos($type, '&') !== false) {
				$types = explode('&', $type);
				$typemod = '&';
			}

			$result = ($typemod == '|' ? false : true);

			foreach ($types as $t) {
				$key = $action.':'.$t.':'.$instance;
				$allowed = false;
				if (array_key_exists($key, $this->permCache)) {
					$allowed = $this->permCache[$key];
				} else {
					$allowed = $this->checkPermission($action, $module, $t, $instance);
					$this->permCache[$key] = $allowed;
				}

				if ($typemod == '|' && $allowed) {
					$result = true;
					break;
				} elseif ($typemod == '&' && !$allowed) {
					$result = false;
					break;
				}
			}

			return $result;

		}
		public function _checkObjectPermission($action, $module = 'ALL', $type = 'ALL', $instance = 'ALL') {
			$actions = $this->_getObjectActions($module, $type, $instance);
			if ($actions)
				return in_array($action, $actions) || in_array('ALL', $actions);
			return false;
		}

		public function _getObjectActions($module = 'ALL', $type = 'ALL', $instance = 'ALL') {
			$perms = $this->getPermissions();
			if($perms) $perms = $perms[$module];
			if($perms) $perms = $perms[$type];
			if($perms) $perms = $perms[$instance];
			if($perms) return $perms;
			return false;
		}

		public function getActionPermissions($module, $type, $action) {
			$objects = array();
			$perms = $this->getPermissions();
			if ($perms) $perms = $perms[$module];
			if ($perms) $perms = $perms[$type];
			if ($perms)
				foreach ($perms as $object => $actions)
					if (in_array($action, $perms[$object]) || in_array('ALL', $perms[$object]))
						$objects[] = $object;
			return null;
		}

		public function getObjectPermissions($module, $type, $instance = 'ALL') {
			// Recursively check for permissions
			$validactions = array();
			$actions = $this->_getObjectActions();
			if ($actions) $validactions = array_merge($validactions, $actions);
			if ($module != 'ALL') $actions = $this->_getObjectActions($module);
			if ($actions) $validactions = array_merge($validactions, $actions);
			if ($type != 'ALL') $actions = $this->_getObjectActions($module, $type);
			if ($actions) $validactions = array_merge($validactions, $actions);
			if ($instance != 'ALL') $actions = $this->_getObjectActions($module, $type, $instance);
			if ($actions) $validactions = array_merge($validactions, $actions);
			if (in_array('ALL', $validactions))
				return array('ALL');
			return array_unique($validactions);
		}

		public function _parsePermissions($raw) {
			$perms = array();
			foreach ($raw as $perm) {
				if (!isset($perms[$perm['module']])) $perms[$perm['module']] = array();
				if (!isset($perms[$perm['module']][$perm['type']]))
					$perms[$perm['module']][$perm['type']] = array();
				if (!isset($perms[$perm['module']][$perm['type']][$perm['instance']]))
					$perms[$perm['module']][$perm['type']][$perm['instance']] = array();
				if (!in_array($perm['action'], $perms[$perm['module']][$perm['type']][$perm['instance']]))
					$perms[$perm['module']][$perm['type']][$perm['instance']][] = $perm['action'];
			}
			return $perms;
		}

		public function hasCreateOrEditPermission($module, $type, $instance) {
			if ($instance) {
				$action = 'MODIFY';
			} else {
				$action = 'CREATE';
				$instance = 'ALL';
			}
			return $this->checkPermission($action, $module, $type, $instance);
		}

		public function login($username, $password, $remember = false) {
			if ($username && $password) {
				$u = $this->query->User;
				try {
					$user = $u->filter(
						'username', $username,
						'password', $password)->one();
					if ($remember)
						setcookie('autologin', $user->pk.':'.md5($user->username.$user->password), time() + 30*86400, '/');
					$this->_createSession($user);
					return $user;
				} catch (DoesNotExistException $dne) {
					return false;
				}
			}
			return false;
		}

		public function isLoggedIn() {
			return ($this->getUser() != null);
		}

		public function requireLogin() {
			return $this->isLoggedIn() || $_SERVER['SCRIPT_NAME'] == $this->config['login_url'];
		}

		public function autoLogin() {
			if (!$this->isLoggedIn() && array_key_exists('autologin', $_COOKIE) && $_COOKIE['autologin']) {
				list($uid, $hash) = explode(':', $_COOKIE['autologin']);
				if ($uid) {
					$u = $this->query->User;
					$user = $u->get($uid);
					if ($user) {
						if ($user && md5($user['username'].$user['password']) == $hash) {
							$this->_createSession($user);
							return $user;
						}
					}
				}
			}
			if ($this->getPermissions() === null) {
				// Load default permissions
				$this->_createSession(null);
			}
			return false;
		}

		public function randomToken($length) {
			$pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
			// Force first character to be alpha
			$token = $pattern{rand(10,35)};
			for ($i=1; $i < $length; $i++)
				$token .= $pattern{rand(0,35)};
			return $token;
		}

		public function logout() {
			$this->_destroySession();
			setcookie('autologin', null, 0, '/');
		}

		public function _createSession($user = null) {	
			$session = $user ? array('user' => $user->pk) : array();
			$this->user = $user;
			$_SESSION[$this->id] = $session;
			$this->_refreshPermissions();
		}

		public function _refreshPermissions() {
			$user = $this->getUser();
			$userid = $user ? $user->pk : null;
			$up = $this->query->UserPermission;
			$params = array();
			$sql = 'SELECT module, type, instance, action FROM user_permissions up ';
			$sql .= 'WHERE ';
			if ($userid) {
				$sql .= 'up.user = ? OR ';
				$params[] = $userid;
			}
			$sql .= 'up.user IS NULL ';
			$sql .= 'UNION ';
			$sql .= 'SELECT module, type, instance, action FROM role_permissions rp ';
			$sql .= ' INNER JOIN user_roles ur ON (rp.role = ur.role) ';
			$sql .= ' WHERE ';
			if ($userid) {
				$sql .= 'ur.user = ? OR ';
				$params[] = $userid;
			}
			$sql .= 'ur.user IS NULL';
			$result = $up->query($sql, $params);
			$perms = $up->createHash($result, null);
			$_SESSION[$this->id]['permissions'] = $this->_parsePermissions($perms);
		}

		public function _destroySession() {
			$_SESSION[$this->id] = array();
		}

		public function pluralize($string) {
			$last = substr($string, -1);
			if ($last == 'y') $string = substr($string, 0, -1).'ies';
			elseif ($last == 's') $string .= 'es';
			else $string .= 's';
			return $string;
		}

		// Mimic PHP 5.2+ strval() functionality
		public function toString($variable) {
			if (is_object($variable) && method_exists($variable, '__toString'))
				return $variable->__toString();
			return strval($variable);
		}

		private function initSmarty() {

			$this->smarty = new Smarty();
			$this->smarty->template_dir = $this->config['mail_templates'];
			$this->smarty->compile_dir = $this->getCachePath('templates', true);
			$this->smarty->cache_dir = $this->getCachePath('tplcache', true);
			$this->smarty->caching = $config['template_caching'] ? $config['template_caching'] : 0;
			$this->smarty->cache_lifetime = $config['template_cache_lifetime'] ? $config['template_cache_lifetime'] : -1;

		}

		private function &getMailTemplateEngine($context = null) {

			if (!$this->smarty)
				$this->initSmarty();
			else
				$this->smarty->clear_all_assign();

				$this->smarty->assign('host', $_SERVER['HTTP_HOST']);
				$this->smarty->assign('user', $this->getUser());
				$this->smarty->assign('debug', $this->config['debug']);
				$this->smarty->assign('baseurl', $this->baseUrl);

			if ($context)
				foreach ($context as $key => $value)
					$this->smarty->assign($key, $value);

			return $this->smarty;

		}

		// Send mail with SMTP authentication
		public function authMail($to, $from, $subject, $message, $extraheaders = null) {
			if ($this->config['mail_user'])
				$mailer = Mail::factory('smtp', array('host' => $this->config['mail_host'],
									'port' => 25,
									'auth' => true,
									'username' => $this->config['mail_user'],
									'password' => $this->config['mail_pass'],
									'persist' => false
								)
							);
			else
				$mailer = Mail::factory('smtp', array('host' => $this->config['mail_host'],
									'port' => 25,
									'persist' => false
								)
							);
			$headers = array('To' => $to, 'Subject' => $subject, 'From' => $from);
			if ($extraheaders)
				$headers = array_merge($extraheaders, $headers);
			return $mailer->send($to, $headers, $message);
		}

		/* Send an email based on a template */
		public function sendMailTemplate($subject, $template, $recip = null, $sender = null, $extravars = null, $images = null) {

			$mime = new Mail_mime("\r\n");

			if ($images)
				foreach ($images as $name => $file)
					$mime->addHTMLImage($file, null, $name);

			// Reset engine to clear template variables
			$engine = $this->getMailTemplateEngine($extravars);
			$engine->assign('subject', $subject);

			if ($engine->template_exists($template.'.html.tpl'))
				$mime->setHTMLBody($engine->fetch($template.'.html.tpl'));
			if ($engine->template_exists($template.'.text.tpl'))
				$mime->setTXTBody($engine->fetch($template.'.text.tpl'));

			if (!$recip) {
				$user = $this->getUser();
				if ($user->firstname)
					$recip = $user->firstname.' '.$user->lastname.' <'.$user->email.'>';
				else
					$recip = $user->email;
			}

			if (!$sender) {
				$sender = $this->config['mail_from'];
				$headers = $this->config['mail_replyto'] ? array('Reply-To' => $this->config['mail_replyto']) : null;
			}

			return $this->authMail($recip,
									$sender,
									$subject,
									$mime->get(),
									$mime->headers($headers));

		}

		public function checkMailAuth($to, $auth) {
			return $auth == md5($to . $this->authKey);
		}

		public function getMailAuth($to) {
			return md5($to . $this->authKey);
		}

		public function __get($name) {
			$readable = array('query', 'config', 'smarty');
			if (property_exists($this, $name) && in_array($name, $readable))
				return $this->$name;
			return null;
		}

		public function relativeRedirect($url) {
			header('Location: '.$this->baseUrl.$url);
		}

		public function relativeUrl($url) {
			return $this->baseUrl.$url;
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

			if (!$this->path)
				$this->path = explode(':', ini_get('include_path'));

			foreach($this->path as $dir)
				if (file_exists($dir.'/'.$file)) {
					$this->fileCache[$file] = $dir.'/'.$file;
					return $dir.'/'.$file;
				}

			$this->fileCache[$file] = FALSE;
			return FALSE;

		}

		public function elapsedTime($timestamp = null) {
			if (!$timestamp) $timestamp = $this->startTime;
			$ms = microtime(true);
			$frac = $ms - intval($ms);
			return round(($ms - $timestamp)*1000)/1000;
		}

		public function getLogTimestamp($basetime = null) {
			$elapsed = $this->elapsedTime($basetime);
			$ms = microtime(true);
			$frac = $ms - intval($ms);
			return date('H:i:s').substr($frac, 1, 4).' (+'.$elapsed.'s)';
		}

		public function logDebug($msg, $moreinfo = null) {
			if ($this->config['debug']) {
				$this->logs['debug'][] = array($this->getLogTimestamp($this->lastDebug).': '.$msg, $moreinfo);
				$this->lastDebug = microtime(true);
			}
		}

		public function logInfo($msg, $moreinfo = null) {
			$this->logs['info'][] = array($this->getLogTimestamp().': '.$msg, $moreinfo);
		}

		public function logWarning($msg, $moreinfo = null) {
			$this->logs['warn'][] = array($this->getLogTimestamp().': '.$msg, $moreinfo);
		}

		public function logError($msg, $moreinfo = null) {
			$this->logs['error'][] = array($this->getLogTimestamp().': '.$msg, $moreinfo);
		}

	}

	if (!function_exists('mkdirs')) {
		function mkdirs($path) {
			$parent = dirname($path);
			if (!is_dir($parent))
				mkdirs($parent);
			mkdir($path);
		}
	}
