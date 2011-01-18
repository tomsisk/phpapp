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
	require_once('QueryFilter.class.php');

	/**
	 * The root query object for a specific model.
	 *
	 * This class should not be instantiated manually; you should always
	 * retrieve a configured ModelQuery object via QueryFactory->get().
	 * Most QueryFilter methods are available from this object, except those
	 * that actually execute a query (select(), hash(), raw(), etc).  If you
	 * need to fetch all instances of a model, try calling the all() filter
	 * first.
	 *
	 * @see QueryFilter
	 */
	class ModelQuery extends QueryFilter {
		
		public $modelClass;

		private $appName;

		/**
		 * Create a new query object.
		 *
		 * This class should not be instantiated manually.
		 *
		 * @see QueryFactory
		 * @param Model $modelClass_ A Model instance to build a query object for
		 * @param QueryFactory $factory_ The QueryFactory that is creating this object
		 * @param string $appName_ The application name (also serves as a table name prefix)
		 */
		public function __construct(&$modelClass_, &$factory_, $appName_ = null) {
			$this->modelClass = $modelClass_;
			$this->factory = $factory_;
			$this->appName = $appName_;

			$this->model = new $modelClass_($this, $this->makeTableName($modelClass_), $appName_);
			parent::__construct($this);
		}

		/**
		 * Create a model object from a hash of key/value pairs.
		 *
		 * @param Array $params A hash array of name/value pairs
		 * @param Array $rawvalues A list of field names that should be ignored
		 * 		during type conversion
		 * @param int $flags A bitmask of flags specifying information about the
		 * 		source of the params (UPDATE_* constants)
		 * @return Model The new Model instance
		 * @see	UPDATE_FROM_FORM
		 * @see	UPDATE_FORCE_BOOLEAN
		 * @see	UPDATE_FROM_DB
		 */
		public function create($params = null, $rawvalues = null, $flags = 0) {
			$model = null;
			$scField = $this->model->_subclassField;
			$scFilter = $this->model->_subclassFilter;
			if ($scField
					&& isset($params[$scField])
					&& isset($scFilter[$params[$scField]])
					&& $mq = $this->factory->get($scFilter[$params[$scField]])) {
				$model = clone $mq->model;
			} else {
				$model = clone $this->model;
			}
			$model->updateModel($params, $rawvalues, $flags);
			return $model;
		}

		/**
		 * Determine if the specified model requires an alternate model subclass
		 * (if the model called mapConcreteTypes()).
		 * @params Array A hash of field name/value pairs
		 * @return Model The model prototype that fits the field values
		 * @see Model::mapConcreteTypes()
		 */
		public function getConcreteType($params) {

			$scField = $this->model->_subclassField;
			$scFilter = $this->model->_subclassFilter;

			if ($scField
					&& isset($params[$scField])
					&& isset($scFilter[$params[$scField]])
					&& $mq = $this->factory->get($scFilter[$params[$scField]]))
				return $mq->model;

			return $this->model;

		}

		/**
		 * Update an existing model object from a hash of key/value pairs.
		 *
		 * @param mixed $id The unique object ID
		 * @param Array $params A hash array of name/value pairs
		 * @param Array $rawvalues A list of field names that should be ignored
		 * 		during type conversion
		 * @param int $flags A bitmask of flags specifying information about the
		 * 		source of the params (UPDATE_* constants)
		 * @return Model The updated Model instance
		 * @see	UPDATE_FROM_FORM
		 * @see	UPDATE_FORCE_BOOLEAN
		 * @see	UPDATE_FROM_DB
		 */
		public function getAndUpdate($id, $params, $flags = 0, $filter = null) {
			if (!$id)
				return $this->create($params);
			try {
				if (!$filter) $filter = $this;
				$model = $filter->get($id);
				$model->updateModel($params, null, $flags);
				return $model;
			} catch(DoesNotExist $dne) {
				return $this->create($params);
			}
		}

		/**
		 * Get the current database connection.
		 * @return ADOConnection The database connection
		 */
		public function getConnection() {
			return $this->factory->getConnection();
		}

		/**
		 * Get the application name (from QueryFactory).
		 * @return string The application name
		 */
		public function getAppName() {
			return $this->appName;
		}

		/**
		 * Get this model's database table name.
		 * @return string The table name
		 */
		public function getTable() {
			return $this->model->_table;
		}

		// Make an educated guess on the table name.  Will usually be overridden in
		// configure() though.
		public function makeTableName($string) {
			$last = substr($string, -1);
			if ($last == 'y') $string = substr($string, 0, -1).'ies';
			elseif ($last == 's') $string .= 'es';
			else $string .= 's';
			
			$out = $this->appName;
			for ($i = 0; $i < strlen($string); ++$i) {
				$c = $string{$i};
				if (ctype_upper($c))
					$out .= '_'.strtolower($c);
				elseif (ctype_alpha($c))
					$out .= $c;
			}
			if ($out{0} == '_')
				$out = substr($out, 1);
			return $out;
		}

		/**
		 * This method is illegal on ModelQuery to avoid query caching in the
		 * base object.
		 *
		 * @throws InvalidUsageException Method is not valid on this object.
		 */
		public function select() {
			// Block calling select() - we don't want queries cached in $this->models on
			// the shared ModelQuery object.
			throw new InvalidUsageException('Cannot call select() on the root query object (try calling all() first)');
		}

		/**
		 * This method is illegal on ModelQuery to avoid query caching in the
		 * base object.
		 *
		 * @throws InvalidUsageException Method is not valid on this object.
		 */
		public function hash() {
			// Block calling select() - we don't want queries cached in $this->models on
			// the shared ModelQuery object.
			throw new InvalidUsageException('Cannot call hash() on the root query object (try calling all() first)');
		}

		/**
		 * This method is illegal on ModelQuery to avoid query caching in the
		 * base object.
		 *
		 * @throws InvalidUsageException Method is not valid on this object.
		 */
		public function raw() {
			// Block calling select() - we don't want queries cached in $this->models on
			// the shared ModelQuery object.
			throw new InvalidUsageException('Cannot call raw() on the root query object (try calling all() first)');
		}

	}
