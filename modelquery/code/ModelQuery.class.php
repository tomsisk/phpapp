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
	require_once('QueryFilter.class.php');

	class ModelQuery extends QueryFilter {
		
		public $modelClass;

		private $appName;

		public function __construct(&$modelClass_, &$factory_, $appName_ = null) {
			$this->modelClass = $modelClass_;
			$this->factory = $factory_;
			$this->appName = $appName_;

			$this->model = new $modelClass_($this, $this->makeTableName($modelClass_), $appName_);
			parent::__construct($this);
		}

		// Create a model from a dictionary.
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

		public function getConnection() {
			return $this->factory->getConnection();
		}

		public function getAppName() {
			return $this->appName;
		}

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

		public function select() {
			// Block calling select() - we don't want queries cached in $this->models on
			// the shared ModelQuery object.
			throw new InvalidUsageException('Cannot call select() on the root query object (try calling all() first)');
		}

		public function hash() {
			// Block calling select() - we don't want queries cached in $this->models on
			// the shared ModelQuery object.
			throw new InvalidUsageException('Cannot call hash() on the root query object (try calling all() first)');
		}

		public function raw() {
			// Block calling select() - we don't want queries cached in $this->models on
			// the shared ModelQuery object.
			throw new InvalidUsageException('Cannot call raw() on the root query object (try calling all() first)');
		}

	}
