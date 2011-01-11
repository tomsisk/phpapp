<?php
	/*
	 * ModelQuery - a simple ORM layer.
	 * Copyright (C) 2004 Jeremy Jongsma.  All rights reserved.
	 * Portions inspired by the OpenSymphony WebWork project.
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

	require_once('Exceptions.php');
	require_once('Model.class.php');
	require_once('ModelQuery.class.php');
	require_once('LRUCache.class.php');

	class QueryFactory {
		
		private $conn;
		private $appName;
		private $modelRoots;
		private $objectCache;
		private $modelCache;
		private $transOpen = false;
		private $transFailed = false;
		private static $path;

		public function __construct($conn, $modelRoot, $appName = null, $cacheSize = 100) {
			$this->conn = $conn;
			$this->appName = $appName;
			$this->modelRoots = is_array($modelRoot) ? $modelRoot : array($modelRoot);
			$this->objectCache = array();
			$this->modelCache = new LRUCache($cacheSize);
		}

		public function &__get($name) {
			return $this->get($name);
		}

		public function &get($name) {
			if (!array_key_exists($name, $this->objectCache)) {
				$found = false;
				foreach ($this->modelRoots as $modelDir) {
					$includeFile = $modelDir.'/'.$name.'.class.php';
					if (QueryFactory::file_exists_in_path($includeFile)) {
						require_once($includeFile);
						$found = true;
						break;
					}
				}
				if ($found) {
					$mq = new ModelQuery($name, $this, $this->appName);
					$this->objectCache[$name] =& $mq;
				}
			}
			return $this->objectCache[$name];
		}

		public function precacheModels() {
			$classes = QueryFactory::getModelClasses($this->modelRoots);
			foreach ($classes as $c => $f)
				$this->get($c);
		}

		public function setConnection($conn) {
			$this->conn = $conn;
		}

		public function getConnection() {
			return $this->conn;
		}

		public function begin() {
			if ($this->transOpen)
				throw new TransactionException('A transaction is already in progress.');
			$this->transOpen = true;
			$this->transFailed = false;
			$this->conn->BeginTrans();
		}

		public function commit() {
			if (!$this->transOpen)
				throw new TransactionException('No transaction is in progress.');
			$this->conn->CommitTrans();
			$this->transOpen = false;
		}

		public function rollback() {
			if (!$this->transOpen)
				throw new TransactionException('No transaction is in progress.');
			$this->conn->RollbackTrans();
			$this->transOpen = false;
		}

		public function fail() {
			$this->transFailed = true;
		}

		public function complete() {
			if ($this->transFailed)
				$this->rollback();
			else
				$this->commit();
		}

		public function inTransaction() {
			return $this->transOpen;
		}

		public function cachePut($key, $model) {
			return $this->modelCache->put($key, $model);
		}

		public function cacheGet($key) {
			return $this->modelCache->get($key);
		}

		public function cacheExists($key) {
			return $this->modelCache->exists($key);
		}

		public function cacheRemove($key) {
			return $this->modelCache->remove($key);
		}

		private static function file_exists_in_path($file) {
			if (file_exists($file))
				return TRUE;
			if (!QueryFactory::$path)
				QueryFactory::$path = explode(':', ini_get('include_path'));
			foreach(QueryFactory::$path as $dir)
				if (file_exists($dir.'/'.$file))
					return TRUE;
			return FALSE;
		}

		private static function find_file_in_path($file) {
			if (file_exists($file))
				return TRUE;
			if (!QueryFactory::$path)
				QueryFactory::$path = explode(':', ini_get('include_path'));
			foreach(QueryFactory::$path as $dir)
				if (file_exists($dir.'/'.$file))
					return $dir.'/'.$file;
			return null;
		}

		public static function preloadModelClasses($roots) {
			$classes = QueryFactory::getModelClasses($roots);
			foreach ($classes as $c => $f)
				require_once($f);
		}

		private static function getModelClasses($roots) {
			$classes = array();
			foreach ($roots as $root) {
				$dir = QueryFactory::find_file_in_path($root);
				if (is_dir($dir)) {
					$d = opendir($dir);
					while ($f = readdir($d))
						if (substr($f, -10) == '.class.php')
							$classes[substr($f, 0, -10)] = $dir.'/'.$f;
					closedir($d);
				}
			}
			return $classes;
		}

	}
