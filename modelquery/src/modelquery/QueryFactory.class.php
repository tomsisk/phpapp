<?php
	/**
	 * @package modelquery
	 * @filesource
	 */

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
	require_once('QueryHandler.class.php');
	require_once('LRUCache.class.php');

	// Maintain backwards compatibility, don't require
	@include_once('adodb/adodb.inc.php');

	/**
	 * Entry point for all interaction with the ModelQuery API.
	 *
	 * Handles locating, loading and configuring Model objects, and provides
	 * database connection and caching services to models.
	 *
	 * @package modelquery
	 */
	class QueryFactory {
		
		private static $path;

		private $conn;
		private $connParams;
		private $appName;
		private $modelRoots = array();

		private $objectCache = array();
		private $modelCache;
		private $transOpen = false;
		private $transFailed = false;

		public $queryCount = 0;
		public $logger;
										
		/**
		 * Create a new QueryFactory.
		 *
		 * @param Array $conn An array of connection parameters: array('host' => ...,
		 *		'user' => ..., 'password' => ..., 'database' => ...)
		 * @param Array $modelRoot A list of directories that contain model definitions
		 * @param string $appName An application name that also serves as the default
		 *			table prefix
		 * @param int $cacheSize The default size of the model cache
		 */
		public function __construct($conn, $modelRoot, $appName = null, $cacheSize = 100) {
			$this->setConnection($conn);
			$this->appName = $appName;
			$this->modelRoots = is_array($modelRoot) ? $modelRoot : array($modelRoot);
			$this->modelCache = new LRUCache($cacheSize);
		}

		/**
		 * Get a configured QueryHandler object by name.
		 * <code>$qf->get('User')</code> will look for a file named User.class.php
		 * in the list of model directories specified in the constructor, and upon
		 * finding one will include it and attempt to instantiate a <b>User</b>
		 * class.
		 *
		 * @param string $name The model name.
		 * @return QueryHandler A QueryHandler object configured for querying the
		 * 		specified model type.
		 */
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
					$mq = new QueryHandler($name, $this, $this->appName);
					$this->objectCache[$name] =& $mq;
				}
			}
			return $this->objectCache[$name];
		}

		/**
		 * Default property getter.
 		 * 
		 * Allows alternate syntax for fetching a QueryHandler object.  The calls
		 * $qf->User and $qf->get('User') are equivalent.
		 */
		public function &__get($name) {
			return $this->get($name);
		}

		/**
		 * Configure QueryHandler objects for all model objects found in the
		 * search path specified in the constructor.
		 *
		 * Because instantiating large numbers of QueryHandler objects can be
		 * expensive, this method is useful to prepare a QueryFactory instance
		 * for adding to a shared application cache.
		 *
		 * @return int The last modified timestamp
		 */
		public function precacheModels() {
			$classes = QueryFactory::getModelClasses($this->modelRoots);
			foreach ($classes as $c => $f)
				$this->get($c);
			return QueryFactory::getLastUpdated($classes);
		}

		/**
		 * Set the shared database connection for QueryHandler objects.
		 *
		 * @param $conn An ADODB database connection object
		 */
		public function setConnection($conn) {
			if ($this->conn)
				$this->conn->Close();
			if (is_array($conn)) {
				$this->connParams = $conn;
				$this->conn = null;
			} else
				// Backwards compatibility: use given ADODB connection object
				$this->conn = $conn;
		}

		/**
		 * Get the shared database connection.
		 * @return An ADODB database connection object
		 */
		public function &getConnection() {
			if (!$this->conn && $p = $this->connParams) {
				$c = ADONewConnection('mysqlt');
				$c->setFetchMode(ADODB_FETCH_ASSOC);
				$c->autoCommit = false;
				$c->NConnect($p['host'], $p['user'], $p['password'], $p['database']);
				$this->conn = $c;
			}
			return $this->conn;
		}

		/**
		 * Close the shared database connection. This should usually not be called
		 * by user code. If getConnection() is called in the future the connection
		 * will be re-opened.
		 */
		public function closeConnection() {
			if ($this->conn) {
				$this->conn->Close();
				$this->conn = null;
			}
		}

		/**
		 * Begin a transaction.
		 * @exception TransactionException if a transaction is already in progress
		 */
		public function begin() {
			if ($this->transOpen)
				throw new TransactionException('A transaction is already in progress.');
			$this->transOpen = true;
			$this->transFailed = false;
			$this->getConnection()->BeginTrans();
		}

		/**
		 * Commit the current transaction to the database.
		 * @exception TransactionException if no transaction is in progress
		 */
		public function commit() {
			if (!$this->transOpen)
				throw new TransactionException('No transaction is in progress.');
			$this->getConnection()->CommitTrans();
			$this->transOpen = false;
		}

		/**
		 * Rollback the current transaction.
		 * @exception TransactionException if no transaction is in progress
		 */
		public function rollback() {
			if (!$this->transOpen)
				throw new TransactionException('No transaction is in progress.');
			$this->getConnection()->RollbackTrans();
			$this->transOpen = false;
		}

		/**
		 * Cause any currently open transaction to fail, triggering a rollback.
		 */
		public function fail() {
			$this->transFailed = true;
		}

		/**
		 * Complete a transaction,
		 *
		 * If the transaction was manually cancelled with fail(), this calls
		 * rollback().  Otherwise, commit() is called.
		 */
		public function complete() {
			if ($this->transFailed)
				$this->rollback();
			else
				$this->commit();
		}

		/**
		 * Check if a transaction is in process.
		 * @return bool TRUE if a transaction is in process.
		 */
		public function inTransaction() {
			return $this->transOpen;
		}

		/**
		 * Add a model to the in-memory cache.
		 *
		 * This method is only intended for use for classes that are part of
		 * the ModelQuery package.
		 *
		 * @param string $key A unique cache key
		 * @param Model $model
		 */
		public function cachePut($key, $model) {
			return $this->modelCache->put($key, $model);
		}

		/**
		 * Fetch a model from the in-memory cache.
		 *
		 * This method is only intended for use for classes that are part of
		 * the ModelQuery package.
		 *
		 * @param string $key A unique cache key
		 * @return Model The cached model
		 */
		public function cacheGet($key) {
			return $this->modelCache->get($key);
		}

		/**
		 * Check if a model exists from the in-memory cache.
		 *
		 * This method is only intended for use for classes that are part of
		 * the ModelQuery package.
		 *
		 * @param string $key A unique cache key
		 * @return bool TRUE if the model is currently cached
		 */
		public function cacheExists($key) {
			return $this->modelCache->exists($key);
		}

		/*!
		 * Remove a model from the in-memory cache.
		 *
		 * This method is only intended for use for classes that are part of
		 * the ModelQuery package.
		 *
		 * @param string $key A unique cache key
		 * @return Model The previously cached model, if found
		 */
		public function cacheRemove($key) {
			return $this->modelCache->remove($key);
		}

		/**
		 * Remove all models from the in-memory cache.
		 *
		 * This method is only intended for use for classes that are part of
		 * the ModelQuery package.
		 *
		 * @param string $key A unique cache key
		 * @return Model The previously cached model, if found
		 */
		public function cacheFlush() {
			return $this->modelCache->flush();
		}

		/**
		 * Check if a file exists in any directory of PHP's include_path.
		 * @param string $file The file/relative path to search for
		 * @return TRUE if the file is found and able to be included
		 */
		public static function file_exists_in_path($file) {
			if (file_exists($file))
				return TRUE;
			if (!QueryFactory::$path)
				QueryFactory::$path = explode(':', ini_get('include_path'));
			foreach(QueryFactory::$path as $dir)
				if (file_exists($dir.'/'.$file))
					return TRUE;
			return FALSE;
		}

		/**
		 * Resolve the path of a file in any directory of PHP's include_path.
		 * @param string $file The file/relative path to search for
		 * @return string The full path of the file, if found
		 */
		public static function find_file_in_path($file) {
			if (file_exists($file))
				return $file;
			if (!QueryFactory::$path)
				QueryFactory::$path = explode(':', ini_get('include_path'));
			foreach(QueryFactory::$path as $dir)
				if (file_exists($dir.'/'.$file))
					return $dir.'/'.$file;
			return null;
		}

		/**
		 * Load the class definitions of all known models.
		 *
		 * Searches the specfied list of directories for model definitions,
		 * and loads the class files without instantiating the model classes.
		 * This can be useful in combination with precacheModels().  After
		 * calling precacheModels(), a QueryFactory can be serialized to a
		 * shared cache (like memcache or xcache), and on future requests,
		 * calling preloadModelClasses() before deserialization avoids
		 * any class loading errors.
		 *
		 * @param Array $roots A list of directories to search for models in
		 * @return int The last modified timestamp
		 */
		public static function preloadModelClasses($roots) {
			$classes = QueryFactory::getModelClasses($roots);
			foreach ($classes as $c => $f)
				require_once($f);
			return QueryFactory::getLastUpdated($classes);
		}

		/**
		 * Get the class names of all model definitions found in the search path.
		 *
		 * Any class names returned will have already had their class definitions
		 * loaded into memory, and are ready for instantiation.
		 */
		private static function getModelClasses($roots) {
			$classes = array();
			foreach ($roots as $root) {
				$dir = QueryFactory::find_file_in_path($root);
				if (is_dir($dir)) {
					$d = opendir($dir);
					while ($f = readdir($d))
						if (substr($f, -10) == '.class.php') {
							$modelName = substr($f, 0, -10);
							if (!isset($classes[$modelName]))
								$classes[$modelName] = $dir.'/'.$f;
						}
					closedir($d);
				}
			}
			return $classes;
		}

		/**
		 * Return the timestamp of the model most recently modified.
		 *
		 * This is useful information for refreshing the model cache.
		 *
		 * @param Array A name => value array of model name => filename
		 * @return int The last modified timestamp
		 */
		private static function getLastUpdated($models) {
			$latest = 0;
			foreach ($models as $m => $f) {
				$modified = filemtime($f);
				if ($modified > $latest)
					$latest = $modified;
			}
			return $latest;
		}

	}
