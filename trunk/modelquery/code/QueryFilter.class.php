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
	require_once('CursorIterator.class.php');

	define('ALLFIELDS', 'ALLFIELDS');

	class QueryFilter implements ArrayAccess, Iterator, Countable {

		public $modelquery;
		public $model;
		public $factory;
		public $tableName;

		protected $parentFilter;
		protected $query;
		protected $fullQuery;

		private $models;

		public function __construct(&$modelquery_, &$parentFilter_ = null) {
			$this->modelquery =& $modelquery_;
			// Cache some ModelQuery properties
			$this->model =& $modelquery_->model;
			$this->factory =& $modelquery_->factory;
			$this->tableName =& $modelquery_->model->_table;

			$this->parentFilter =& $parentFilter_;

			$this->query = array('distinct' => false,
								'select' => array(),
								'filters' => array(),
								'aggregates' => array(),
								'insert' => array(),
								'where' => array(),
								'tables' => array(),
								'order' => null,
								'limit' => 0,
								'offset' => 0,
								'preload' => array(),
								'groupby' => array(),
								'converters' => array(),
								'debug' => false);
		}

		/*
		 * Methods that refine a query and return another ModelQuery object
		 */

		// Everything in the current set (blank filter)
		public function &all() {
			$filter = new QueryFilter($this->modelquery, $this);
			return $filter;
		}

		// Filters are defined as arrays, like: ("username:exact", "jjongsma")
		public function &filter() {
			$filter = new QueryFilter($this->modelquery, $this);
			$args = func_get_args();
			$filter->applyFilter($this->getFilters($args));
			return $filter;
		}

		// Filters are defined as arrays, like: ("username:exact", "jjongsma")
		public function &filteror() {
			$filter = new QueryFilter($this->modelquery, $this);
			$args = func_get_args();
			$filter->applyFilterOr($this->getFilters($args));
			return $filter;
		}

		// Filters are defined as arrays, like: ("username:exact", "jjongsma")
		public function &exclude() {
			$filter = new QueryFilter($this->modelquery, $this);
			$args = func_get_args();
			$filter->applyExclude($this->getFilters($args));
			return $filter;
		}

		// Filters are defined as arrays, like: ("username:exact", "jjongsma")
		public function &excludeor() {
			$filter = new QueryFilter($this->modelquery, $this);
			$args = func_get_args();
			$filter->applyExcludeOr($this->getFilters($args));
			return $filter;
		}

		private function getFilters($args) {
			if (sizeof($args) == 1 && is_array($args[0])) {
				$filters = array();
				$keys = array_keys($args[0]);
				foreach ($keys as $key) {
					$filters[] = $key;
					$filters[] = $args[0][$key];
				}
				return $filters;
			} else
				return $args;
		}

		// Extra SQL to add to the query
		public function &extra($select = null, $where = null, $params = null, $tables = null) {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applyExtra($select, $where, $params, $tables);
			return $filter;
		}

		// Sort randomly
		public function &random() {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applyRandom();
			return $filter;
		}

		// Accepts a variable length list of field names
		// Prefix with '-' to reverse sort
		public function &order() {
			$filter = new QueryFilter($this->modelquery, $this);
			$args = func_get_args();
			call_user_func_array(array($filter, 'applyOrder'), $args);
			return $filter;
		}

		// Slice a range from the middle of the queryset
		public function &slice($limit = 0, $offset = 0) {
			if ($offset < 0) $offset = 0;
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applySlice($limit, $offset);
			return $filter;
		}

		// Filter by unique rows
		public function &distinct() {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applyDistinct();
			return $filter;
		}

		// Add a group clause to the query
		public function group($groupby) {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applyGrouping($groupby);
			return $filter;
		}

		/*
		 * Request to preload related objects
		 * Important notes:
		 *  1) You may preload as many ManyToOne fields as you like,
		 *     but you may only preload a single ManyToMany/OneToMany
		 *     due to limitations in SQL joining
		 *  2) Applying this filter for a ManyToMany/OneToMany field
		 *     will result in inaccurate sqlcount() calls
		 */
		public function &preload($field) {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applyPreload($field);
			return $filter;
		}

		// Output debug info for this query
		public function &debug() {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->applyDebug();
			return $filter;
		}

		// Add a maximum field to the query
		public function maxfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'MAX', $groupby);
		}

		// Add a minimum field to the query
		public function minfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'MIN', $groupby);
		}

		// Add a average field to the query
		public function avgfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'AVG', $groupby);
		}

		// Add a sum field to the query
		public function sumfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'SUM', $groupby);
		}

		// Add a maximum field to the query
		public function countfield($name = null, $field = null, $groupby = null) {
			return $this->aggregate($name, $field, 'COUNT', $groupby);
		}

		// Add an aggregate field to the query - does not return
		// an immediate result, but adds to the field list
		public function aggregate($name, $field, $fn, $groupby = null) {
			$filter = new QueryFilter($this->modelquery, $this);
			$filter->addAggregate($name, $field, $fn, $groupby);
			return $filter;
		}

		// Get the maximum value of a field
		public function max($field, $groupby = null) {
			return $this->returnAggregate($field, $field, 'MAX', $groupby, true);
		}

		// Get the minimum value of a field
		public function min($field, $groupby = null) {
			return $this->returnAggregate($field, $field, 'MIN', $groupby, true);
		}

		// Get the average value of a field
		public function avg($field, $groupby = null) {
			return floatval($this->returnAggregate($field, $field, 'AVG', $groupby));
		}

		// Get the sum of all field values
		public function sum($field, $groupby = null) {
			return $this->returnAggregate($field, $field, 'SUM', $groupby, true);
		}

		// Get the row count of a field
		public function sqlcount($field = null, $groupby = null) {
			return intval($this->returnAggregate($field, $field, 'COUNT', $groupby));
		}

		// Return an aggregate value of a field
		public function returnAggregate($name, $field, $fn, $groupby = null, $convert = false) {
			$filter = new QueryFilter($this->modelquery, $this);
			list($name, $fieldObj) = $filter->addAggregate($name, $field, $fn, $groupby);
			$result = $filter->raw();
			$aggregate = $result[0][$name];
			if ($convert) {
				return $fieldObj->convertFromDbValue($aggregate);
			} else {
				return $aggregate;
			}
		}

		/*
		 * Methods used by QuerySet-returning filters above to apply to current object.
		 */
		// Filters are defined as arrays, like: ("username:exact", "jjongsma")
		public function applyFilter($filters) {
			$this->createFilterQuery($filters, false, false);
		}

		public function applyFilterOr($filters) {
			$this->createFilterQuery($filters, false, true);
		}

		// Filters are defined as arrays, like: ("username:exact", "jjongsma")
		public function applyExclude($filters) {
			$this->createFilterQuery($filters, true, false);
		}

		public function applyExcludeOr($filters) {
			$this->createFilterQuery($filters, true, true);
		}

		// Extra SQL to add to the query
		public function applyExtra($select = null, $where = null, $params = null, $tables = null) {
			if ($select) {
				foreach ($select as $name => $field) {
					list ($realField, $joins, $relmodel, $relfield) = $this->getJoinInfo($field);
					$this->query['select'][$name] = $realField;
					$this->query['converters'][$name] = $relmodel->_fields[$relfield];
					if (count($joins))
						$this->query['tables'] = $this->mergeJoins((array)$this->query['tables'], $joins);
				}
			}
			if ($where) {
				if (isset($this->query['where']['sql']))
					$this->query['where']['sql'] = $this->query['where']['sql'].' AND ('.$where.')';
				else
					$this->query['where']['sql'] = $where;
				if ($params)
					$this->query['where']['params'] = array_merge((array)$this->query['where']['params'], $params);
			}
			if ($tables)
				$this->query['tables'] = $this->mergeJoins((array)$this->query['tables'], $tables);
		}


		public function applyRandom() {
			if ($this->query['order'])
				$this->query['order'] .= ', RAND()';
			else
				$this->query['order'] = 'RAND()';
		}

		// Accepts a variable length list of field names
		// Prefix with '-' to reverse sort
		public function applyOrder() {
			$args = func_get_args();
			if ($args && is_array($args[0]))
				$args = $args[0];
			list($clause, $joins) = $this->orderClause($args);
			if ($this->query['order'])
				$this->query['order'] .= ', '.$clause;
			else
				$this->query['order'] = $clause;
			if (count($joins))
				foreach ($joins as $t => $j)
					$this->query['tables'][$t] = $j;
		}

		private function orderClause($fields) {
			$clauses = array();
			$joins = array();
			foreach ($fields as $order) {
				$dir = 'ASC';
				$table = $this->tableName;
				if (substr($order, 0, 1) == '-') {
					$order = substr($order, 1);
					$dir = 'DESC';
				}
				if (substr($order, 0, 1) == '+')
					$order = substr($order, 1);
				// Add appropriate table joins here
				$joinInfo = $this->getJoinInfo($order);
				$joins = $this->mergeJoins($joins, $joinInfo[1]);
				$clauses[] = $joinInfo[0].' '.$dir;
			}
			return array(implode(', ', $clauses), $joins);
		}

		private function getFieldJoinInfo($model, $field, $type = 'LEFT OUTER') {

			$joins = array();
			$relmodel = null;

			$fieldobj = $model->_fields[$field];
			if ($fieldobj) {

				if ($fieldobj instanceof RelationField) {

					$relmodel = $fieldobj->getRelationModel();
					$tname = $relmodel->_table;
					if ($fieldobj instanceof OneToManyField) {
						$tjoin = $fieldobj->joinField;
						$joins['`'.$tname.'` ON (`'.$tname.'`.`'.$tjoin.'` = `'.$model->_table.'`.`'.$model->_idField.'`)'] = $type.' JOIN';
					} elseif ($fieldobj instanceof ManyToManyField) {
						$joinmodel = $fieldobj->getJoinModel();
						$joinfield = $fieldobj->joinField;
						$relid = $fieldobj->getFieldType()->field;
						$targetfield = $fieldobj->targetField;
						$joins['`'.$joinmodel->_table.'` ON (`'.$joinmodel->_table.'`.`'.$joinfield.'` = `'.$model->_table.'`.`'.$model->_idField.'`)'] = $type.' JOIN';
						$joins['`'.$relmodel->_table.'` ON (`'.$relmodel->_table.'`.`'.$relid.'` = `'.$joinmodel->_table.'`.`'.$targetfield.'`)'] = $type.' JOIN';
					} elseif ($fieldobj->options['reverseJoin']) {
						$joinField = $fieldobj->options['reverseJoin'];
						$joins['`'.$tname.'` ON (`'.$tname.'`.`'.$joinField.'` = `'.$model->_table.'`.`'.$model->_idField.'`)'] = $type.' JOIN';
					} else {
						$tid = $relmodel->_idField;
						$joins['`'.$tname.'` ON (`'.$tname.'`.`'.$tid.'` = `'.$model->_table.'`.`'.$field.'`)'] = $type.' JOIN';
					}

				} else {
					throw new InvalidParametersException('The field "'.$field.'" is not a relation field.');
				}

			}

			return array($joins, $relmodel);

		}

		private function getJoinInfo($field, $type = 'LEFT OUTER') {

			$joins = array();
			$currmodel = $this->model;

			if (strpos($field, '.') !== false
					|| $this->model->_fields[$field] instanceof RelationSetField) {

				$relfields = explode('.', $field);
				$field = array_pop($relfields);
				foreach ($relfields as $f) {
					$joinInfo = $this->getFieldJoinInfo($currmodel, $f, $type);
					$currmodel = $joinInfo[1];
					$joins = array_merge($joins, $joinInfo[0]);
				}

				if ($field == 'pk')
					$field = $currmodel->_idField;

				// RelationSetFields are artifical fields; need to add another
				// join and return the relation table's primary key instead
				if ($currmodel->_fields[$field] instanceof RelationSetField) {
					$joinInfo = $this->getFieldJoinInfo($currmodel, $field, $type);
					$currmodel = $joinInfo[1];
					$joins = array_merge($joins, $joinInfo[0]);
					$field = $currmodel->_idField;
				}

			}

			$qualField = '`'.$currmodel->_table.'`.`'.$field.'`';

			return array($qualField, $joins, $currmodel, $field);

		}

		// Slice a range from the middle of the queryset
		public function &applySlice($limit = 0, $offset = 0) {
			$this->query['limit'] = $limit;
			$this->query['offset'] = $offset;
		}

		// Filter by unique rows
		public function &applyDistinct() {
			$this->query['distinct'] = true;
		}

		public function &applyPreload($field) {
			if ($field) {
				list($joins, $relmodel) = $this->getFieldJoinInfo($this->model, $field);
				if ($relmodel) {
					$this->query['preload'][] = $field;
					$this->query['tables'] = $this->mergeJoins($this->query['tables'], $joins);
					$tname = $relmodel->_table;
					foreach ($relmodel->_fields as $fname => $fobj) {
						if (!($fobj instanceof RelationSetField))
							$this->query['select']['__'.$field.'__'.$fname] = $tname.'.'.$fname;
					}
				}
			}
		}

		public function &applyDebug() {
			$this->query['debug'][] = true;
		}

		// Add grouping clause
		public function applyGrouping($groupby, $select = false) {

			if ($groupby) {

				for ($i = 0; $i < count($groupby); ++$i) {
					$gf = $groupby[$i];
					if (strpos($gf, '.') !== FALSE) {
						$ji = $this->getJoinInfo($gf);
						$gf = $ji[0];
					} else {
						$gf = '`'.$this->tableName.'`.`'.$ji[0].'`';
					}
					if ($select)
						$this->query['select'][$groupby[$i]] = $gf;
				}

				$this->query['groupby'] = $groupby;

			}

		}

		// Add aggregate field
		public function addAggregate($name, $field, $fn, $groupby = null) {

			if (!$field)
				$field = $this->model->_idField;
			if (!$name)
				$name = $field;

			$realField = null;

			if (strpos($field, '.') !== FALSE || $this->model->_fields[$field] instanceof RelationSetField) {

				list($realField, $joins, $relmodel, $relfield) = $this->getJoinInfo($field);

				if (!$groupby)
					$groupby = array_keys($this->model->_dbFields);

				if ($joins && count($joins) > 0)
					$this->query['tables'] = $joins;
				$this->query['converters'][$name] = $relmodel->_fields[$relfield];
				$this->query['select'][$name] = $fn.'('.$realField.')';
				$this->applyGrouping($groupby, false);

			} else  {

				$this->query['aggregates'][] = $name;
				$this->query['select'][$name] = $fn.'(`'.$this->tableName.'`.`'.$field.'`)';
				$this->applyGrouping($groupby, true);
				$realField = $this->model->_fields[$field];

			}

			return array($name, $realField);
			
		}

		/*
		 * Methods that execute a query and return the results
		 */

		// Execute current query as-is and return results
		public function select($map = null, $multimap = false) {	
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			$result = $this->doSelect($q);
			$models =& $this->createModels($result, $q['aggregates'], $map, $q['preload'], $multimap);
			$this->models = $models;
			return $this->models;
		}

		// Execute current query as-is and return results as a hash
		public function hash($map = null, $multimap = false) {
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			$result = $this->doSelect($q);
			return $this->createHash($result, $q['aggregates'], $map, $q['preload'], $multimap);
		}

		// Execute current query as-is and return results as a hash,
		// without performing any type conversion.
		// This is the fastest way to query if you're trying to squeeze
		// every bit of performance out of a function.
		public function raw($map = null, $multimap = false) {
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			$result = $this->doSelect($q);
			return $this->createRawHash($result, $map, $q['preload'], $multimap);
		}

		// Execute current query as-is and return results as a field-mapped
		// array.  Identical to calling select($key), but defaults to ID
		// field if no parameter is given.
		public function map($key = null, $multimap = false) {
			if ($key == null)
				$key = $this->model->_idField;
			return $this->select($key, $multimap);
		}

		// Execute current query as-is and return results as cursor
		public function cursor() {
			$q = $this->mergedQuery(true);
			$result = $this->doSelect($q);
			return new CursorIterator($this->modelquery, $result, $q['aggregates']);
		}

		// Execute current query as-is and return results as cursor
		public function hashCursor() {
			$q = $this->mergedQuery(true);
			$result = $this->doSelect($q);
			return new HashCursorIterator($this, $result, $q['aggregates']);
		}

		// Return an array of a single field value
		public function pluck($field) {
			$values = $this->values($field);
			return $values[$field];
		}

		// Return an array of arrays of values mapped to field names
		public function values() {
			$q = $this->mergedQuery(true);
			$fields = func_get_args();
			$values = array();
			$fieldDefs = array();

			for ($i = 0; $i < count($fields); ++$i) {
				list ($realField, $joins, $relmodel, $relfield) = $this->getJoinInfo($fields[$i]);
				if ($relfield) {
					$fieldDefs[$fields[$i]] = $relmodel->_fields[$relfield];
					$q['select'][$fields[$i]] = $realField;
					$q['converters'][$name] = $relmodel->_fields[$relfield];
					if (count($joins))
						$q['tables'] = $this->mergeJoins((array)$q['tables'], $joins);
					$values[$fields[$i]] = array();
				}
			}

			$result = $this->doSelect($q, false);
			$modelfields =& $this->model->_dbFields;
			while (!$result->EOF) {
				$row =& $result->fields;
				foreach ($fields as $field) {
					if (isset($fieldDefs[$field])) {
						$def = $fieldDefs[$field];
						$raw = $q['aggregates'] && in_array($field, $q['aggregates']);
						if (array_key_exists($field, $row))
							$values[$field][] = $raw ? $row[$field] : $def->convertFromDbValue($row[$field]);
					}
				}
				$result->MoveNext();
			}
			return $values;
		}

		// Get a single object.  Throws error if not found, or
		// more than one is found
		public function get($id = null) {

			$q = $this->mergedQuery(true);

			if (count($q['filters']) == 0) {
				$cacheKey = get_class($this->model).':'.$id;
				$model = $this->modelquery->factory->cacheGet($cacheKey);
				if ($model)
					return $model;
			}
			
			$query = $id ? $this->filter('pk', $id) : $this->all();
			$models = $query->select();

			if (count($models) > 1)
				throw new TooManyResultsException('Multiple models were found.');
			elseif (count($models) == 0)
				throw new DoesNotExistException('No models were returned.');

			return $models[0];

		}

		// Return only the first result, throwing error if none found
		public function one() {
			$models = $this->slice(1)->select();
			if (!count($models))
				throw new DoesNotExistException('No models were returned.');
			return $models[0];
		}

		// Return the first result or null if nonexistent
		public function any() {
			$models = $this->slice(1)->select();
			if (count($models) > 0)
				return $models[0];
			return null;
		}

		// Update the current set of objects
		public function update($values, $pk = null) {
			return $this->doUpdate($values, $pk);
		}

		// Insert a new object
		public function insert($model) {
			return $this->doInsert($model);
		}

		// Delete the current set of objects
		public function delete() {
			return $this->doDelete();
		}

		// Clear query cache
		public function flush() {
			$this->models = null;
		}

		/*
		 * Helper functions to generate query SQL using the filter interface.
		 */
		public function where_sql() {
			$q = $this->mergedQuery(true);
			if (isset($q['where']['sql']))
				return $q['where']['sql'];
			return null;
		}

		public function where_params() {
			$q = $this->mergedQuery(true);
			if (isset($q['where']['params']))
				return $q['where']['params'];
			return array();
		}

		/*
		 * Private methods
		 */

		private function doSelect($query, $selectAllFields = true) {
			$selectAllFields = $selectAllFields && !count($query['aggregates']);
			$fields = $this->model->_dbFields;
			$params = array();

			// Select fields
			$sql = 'SELECT ';
			if ($query['distinct'])
				$sql .= 'DISTINCT ';
			$first = true;
			if ($selectAllFields) {
				foreach ($fields as $field => $junk) {
					if (!$first) $sql .= ', ';
					$sql .= '`'.$this->tableName.'`.`'.$field.'`';
					$first = false;
				}
			}
			if (count($query['select'])) {
				foreach ($query['select'] as $field => $spec) {
					$d = $p = null;
					if (is_array($spec)) {
						$d = $spec[0];
						$p = $spec[1];
					} else {
						$d = $spec;
					}
					if (!$first) $sql .= ', ';
					$sql .= '('.$d.') AS `'.$field.'`';
					$first = false;
					if ($p && is_array($p))
						$params = array_merge($params, $p);
				}
			}

			// Tables
			$sql .= ' FROM '.$this->tableName;
			if (count($query['tables']))
				foreach ($query['tables'] as $table => $jointype)
					$sql .= ' '.$jointype.' '.$table;
			
			// Where clause
			if (isset($query['where']['sql'])) {
				$sql .= ' WHERE '.$query['where']['sql'];
				if (isset($query['where']['params']) && is_array($query['where']['params']))
					$params = array_merge($params, $query['where']['params']);
			}

			// Grouping
			if (count($query['groupby'])) {
				$sql .= ' GROUP BY ';
				$first = true;
				foreach ($query['groupby'] as $field) {
					if (!$first) $sql .= ', ';
					$sql .= $field;
					$first = false;
				}
			}

			// Ordering
			if ($query['order'])
				$sql .= ' ORDER BY '.$query['order'];

			// Offset / limit
			if ($query['limit']) {
				$sql .= ' LIMIT ?';
				$params[] = $query['limit'];
			}
			if ($query['offset']) {
				$sql .= ' OFFSET ?';
				$params[] = $query['offset'];
			}

			$sql .= ';';

			if ($query['debug']) {
				//echo '<pre>';
				//print_r($query['filters']);
				//echo '</pre>';
				//echo "<br><br>\n\n";
				print_r($sql);
				echo "<br><br>\n\n";
				print_r($params);
				echo "<br><br>\n\n";
			}

			return $this->query($sql, $params);
		}

		private function doUpdate($model, $pk) {

			// Automatically set ordered fields
			if ($pk)
				$this->adjustOrders($model);

			$dbmodel = $this->convertToDBModel($model);
			$q = $this->mergedQuery(true);
			$bindparams = array();
			$idField = $this->model->_idField;

			$sql = 'UPDATE '.$this->tableName;
			if ($q['tables'] && count($q['tables'])) {
				foreach ($q['tables'] as $t => $j)
					$sql .= ' '.$j.' '.$t;
			}
			$sql .= ' SET ';
			$first = true;
			foreach ($dbmodel as $field => $value) {
				if ($field != $this->model->_idField || $value != $pk) {
					if (!$first) $sql .= ', ';
					$sql .= '`'.$this->tableName.'`.`'.$field.'` = ?';
					$bindparams[] = $value;
					$first = false;
				}
			}
			if (isset($q['where']['sql'])) {
				$sql .= ' WHERE '.$q['where']['sql'];
				$bindparams = array_merge($bindparams, (array)$q['where']['params']);
			} else {
				$sql .= ' WHERE '.$this->tableName.'.'.$this->model->_idField.' = ?';
				$bindparams[] = $pk;
			}
			$sql .= ';';

			$result = $this->query($sql, $bindparams);

			// Updates that don't change any field values still return 0 rows
			//if ($this->affectedRows() > 0)
			//	return true;
			//else
			//	throw new DoesNotExistException('No matching objects were found in the database.');

			$id = $pk;

			if (array_key_exists($this->model->_idField, $model))
				$id = $model[$this->model->_idField];

			return $id;

		}

		private function doBatchUpdate(&$models) {
		}

		private function doInsert($model) {

			// Automatically set ordered fields
			$this->adjustOrders($model);

			$dbmodel = $this->convertToDBModel($model);
			$idField = $this->model->_idField;

			// Merge with any ":exact" query filter values
			$q = $this->mergedQuery(true);
			if ($q['insert'])
				$dbmodel = array_merge($this->convertToDBModel($q['insert']), $dbmodel);

			$bindparams = array();

			$sql = 'INSERT INTO '.$this->tableName.' (';
			$first = true;
			foreach ($dbmodel as $key => $value) {
				if (!$first) $sql .= ', ';
				$sql .= '`'.$key.'`';
				if (array_key_exists($key, $q['insert']))
					$bindparams[] = $q['insert'][$key];
				else
					$bindparams[] = $value;
				$first = false;
			}
			$sql .= ') VALUES (';
			for ($i = 0; $i < sizeof($bindparams); ++$i) {
				$sql .= '?';
				if ($i != sizeof($bindparams) - 1) $sql .= ', ';
			}
			$sql .= ');';

			$result = $this->query($sql, $bindparams);
			if ($this->affectedRows() > -1) {
				$fields = $this->model->_dbFields;
				if (!isset($model[$idField]))
					$model[$idField] = $fields[$idField]->convertFromDbValue($this->insertId());
				return $model[$idField];
			} else {
				throw new SQLException('Could not insert record: '.$this->getError());
			}
		}

		private function doDelete() {
			$ordered = $this->model->orderedFields();
			// If there's ordered fields we have to load all affected and process reordering first
			$cascade = array();
			foreach ($this->model->_fields as $field => $def)
				if ($def instanceof ManyToOneField
						&& $def->options['cascadeDelete']
						&& !$def->options['reverseJoin'])
					$cascade[$field] = array($def, array());
			if (count($ordered) || count($cascade)) {
				$models = $this->select();
				foreach ($models as $model) {
					$this->modelquery->factory->cacheRemove(get_class($model).':'.$model->pk);
					if (count($ordered)) $this->deleteOrders($model);
					if (count($cascade))
						foreach ($cascade as $f => $junk)
							$cascade[$f][1][] = $model->getPrimitiveFieldValue($f);
				}
			}
			$bindparams = array();
			$q = $this->mergedQuery(true);
			$sql = 'DELETE FROM '.$this->tableName;
			if ($q['tables'] && count($q['tables'])) {
				$sql .= ' USING '.$this->tableName;
				foreach ($q['tables'] as $t => $j)
					$sql .= ' '.$j.' '.$t;
			}
			if (isset($q['where']['sql'])) {
				$sql .= ' WHERE '.$q['where']['sql'];
				$bindparams = array_merge($bindparams, (array)$q['where']['params']);
			}
			$sql .= ';';

			$result = $this->query($sql, $bindparams);

			// Cascade-delete children after parents are deleted in case
			// they're null-restricted
			if (count($cascade))
				foreach ($cascade as $f => $d)
					$d[0]->getRelationModel()->getQuery()->filter('pk:in', $d[1])->delete();

			// Affected rows is messed up for mysqlt :|
			//if ($this->affectedRows() > -1)
				return true;
			//else
				//throw new DoesNotExistException('No matching objects were found in the database.');
		}

		private function convertToDBModel($model) {
			$modelVals = ($model instanceof Model ? $model->getFieldValues() : $model);
			$values = array();
			$fields = $this->model->_dbFields;
			foreach ($fields as $field => $def)
				if (array_key_exists($field, $modelVals))
					$values[$field] = $def->convertToDbValue($modelVals[$field]);
			return $values;
		}

		private function adjustOrders(&$model) {

			$ordered = $this->model->orderedFields();

			if (count($ordered)) {

				foreach ($ordered as $field => $def) {

					$groupquery = $this->modelquery;
					$group_by = $def->groupBy ? $def->groupBy : array();
					$currorder = null;

					// Try to use versioning info if a Model object was passed in
					if ($model instanceof Model) {

						$previous = $model->getPreviousFieldValues();
						$currorder = $previous[$field];
						$changedGroup = false;

						if ($currorder)
							foreach ($group_by as $gf) {
								if ($previous[$gf] != $model->getPrimitiveFieldValue($gf)) {
									$changedGroup = true;
									$this->deleteOrders($previous);
									$currorder = null;
									break;
								}
							}

					}

					if (!$changedGroup && !$currorder && isset($model[$this->model->_idField])) {
						try {
							$original = $this->modelquery->get($model[$this->model->_idField]);
							$currorder = $original[$field];
						} catch (DoesNotExist $e) {
							$currorder = null;
						}
					}

					if ($group_by)
						foreach ($group_by as $g)
							$groupquery = $groupquery->filter($g, $model[$g]);

					if ($model[$field] !== null) {

						$params = array();
						$targetorder = $model[$field];

						if ($currorder !== $targetorder) {

							$eq = $groupquery->filter($field, $targetorder)->exclude($this->model->_idField, $model[$this->model->_idField]);
							$exists = $eq->count();

							if ($exists) {

								$maxorder = $groupquery->max($field);
								$maxorder = ($maxorder ? ++$maxorder : 1);

								$sort = 'ASC';

								$sql = 'UPDATE '.$this->tableName.' SET '.$field.' = ';

								if (!$currorder) {

									$sql .= $field.' + 1 WHERE '.$field.' >= ?';
									$params = array($targetorder);

									$sort = 'DESC';

								} elseif ($currorder < $targetorder) {

									$sql .= 'CASE WHEN '.$field.' = ? THEN ? ELSE '.$field.' - 1 END'
										.' WHERE '.$field.' >= ? AND '.$field.' <= ?';
									$params = array($currorder, $maxorder, $currorder, $targetorder);

								} else {

									$sql .= 'CASE WHEN '.$field.' = ? THEN ? ELSE '.$field.' + 1 END'
										.' WHERE '.$field.' <= ? AND '.$field.' >= ?';
									$params = array($currorder, $maxorder, $currorder, $targetorder);

									$sort = 'DESC';

								}

								if ($wheresql = $groupquery->where_sql())
									$sql .= ' AND '.$wheresql;

								$sql .= ' ORDER BY '.$field.' '.$sort.';';

								$params = array_merge($params, $groupquery->where_params());

								$this->query($sql, $params);

							}
						}
					} else {
						$maxorder = $groupquery->max($field);
						$maxorder = ($maxorder ? ++$maxorder : 1);
						$model[$field] = $maxorder;
					}
				}
			}
		}

		private function deleteOrders($model) {
			$ordered = $this->model->orderedFields();
			if (count($ordered)) {
				foreach ($ordered as $field => $def) {
					$groupquery = $this->modelquery;
					$group_by = $def->groupBy;
					if ($group_by)
						foreach ($group_by as $g)
							$groupquery = $groupquery->filter($g, $model[$g]);
					$maxorder = $groupquery->max($field);
					$maxorder = ($maxorder ? ++$maxorder : 1);
					if ($model[$field] !== null) {
						$order = $model[$field];
						$sql = 'UPDATE '.$this->tableName.' SET '.$field.' = ';
						$sql .= 'CASE WHEN '.$field.' = ? THEN ? ELSE '.$field.' - 1 END';
						$sql .= ' WHERE '.$field.' >= ?';
						if ($wheresql = $groupquery->where_sql())
							$sql .= ' AND '.$wheresql;
						$sql .= ' ORDER BY '.$field.' ASC;';
						$bindparams = array_merge(array($order, $maxorder, $order), $groupquery->where_params());
						$this->query($sql, $bindparams);
					}
				}
			}
		}

		public function mergedQuery($applyDefaults = false, $skipPreload = false) {

			$q = $this->query;

			if ($this->parentFilter) {
				$pq = $this->parentFilter->mergedQuery(false, $skipPreload);
				if ($skipPreload && count($q['preload'])) {
					$q = $pq;
				} else {
					$q['select'] = array_merge((array)$pq['select'], (array)$q['select']);
					if (isset($pq['where']['sql'])) {
						if (isset($q['where']['sql'])) {
							$q['where']['sql'] = $pq['where']['sql'].' AND '.$q['where']['sql'];
							$q['where']['params'] = array_merge((array)$pq['where']['params'], (array)$q['where']['params']);
						} else {
							$q['where']['sql'] = $pq['where']['sql'];
							$q['where']['params'] = $pq['where']['params'];
						}
					}
					$q['filters'] = array_merge((array)$pq['filters'], (array)$q['filters']);
					$q['tables'] = $this->mergeJoins((array)$pq['tables'], (array)$q['tables']);
					$q['preload'] = array_merge((array)$pq['preload'], (array)$q['preload']);
					$q['aggregates'] = array_merge((array)$pq['aggregates'], (array)$q['aggregates']);
					$q['groupby'] = array_merge((array)$pq['groupby'], (array)$q['groupby']);
					$q['converters'] = array_merge((array)$pq['converters'], (array)$q['converters']);
					if ($pq['order']) {
						if ($q['order'])
							$q['order'] = $pq['order'].', '.$q['order'];
						else
							$q['order'] = $pq['order'];
					}
					if ($pq['limit'] && !$q['limit']) $q['limit'] = $pq['limit'];
					if ($pq['offset'] && !$q['offset']) $q['offset'] = $pq['offset'];
					if ($pq['distinct']) $q['distinct'] = true;
				}
			}

			if ($applyDefaults && !$q['order']) {
				if ($defOrder = $this->model->getDefaultOrder()) {
					list($oclause, $ojoins) = $this->orderClause($defOrder);
					$q['order'] = $oclause;
					$q['tables'] = $this->mergeJoins($q['tables'], $ojoins ? $ojoins : array());
				}
				if ($q['preload']) {
					// Can't sort child element without sorting by parent first
					foreach ($q['preload'] as $pl) {
						$rfield = $this->model->_fields[$pl];
						if ($rfield instanceof RelationField && $defOrder = $rfield->getRelationModel()->getDefaultOrder()) {
							for ($i = 0; $i < count($defOrder); ++$i) {
								if ($defOrder[$i]{0} == '-' || $defOrder[$i]{0} == '+')
									$defOrder[$i] = $defOrder[$i]{0}.$pl.'.'.substr($defOrder[$i], 1);
								else
									$defOrder[$i] = $pl.'.'.$defOrder[$i];
							}

							// Always need to add sort by primary key in case the primary
							// order field is not unique, otherwise the child items
							// will not necessarily be consecutive.
							list ($pc, $pj) = $this->orderClause(array($this->model->_idField));
							if (!$q['order']) {
								$q['order'] = $pc;
							} else {
								$q['order'] .= ', '.$pc;
							}

							list($oclause, $ojoins) = $this->orderClause($defOrder);
							$q['order'] .= ', '.$oclause;

						}
					}
				}
			}

			if ($pq['debug']) $q['debug'] = true;

			$this->fullQuery = $q;

			return $q;

		}

		private function createFilterQuery($filters, $negate = false, $or = false) {

			if (count($filters)%2 == 1)
				throw new InvalidParametersException('Invalid parameter count: filter parameters come in pairs.');

			$sql = array();
			$params = array();

			for ($i = 0; $i < count($filters); $i = $i+2) {

				$operator = 'exact';
				$field = $filters[$i];
				$value = $filters[$i+1];

				if ($value instanceof Model)
					$value = $value->pk;

				if (strpos($field, ':') !== false)
					list($field, $operator) = explode(':', $field);

				if ($field == 'pk')
					$field = $this->model->_idField;

				// If field references other relations, add join clauses
				if (strpos($field, '.') !== false
						|| (isset($this->model->_fields[$field])
							&& $this->model->_fields[$field] instanceof RelationSetField)) {

					list($qualField, $joins, $currmodel, $field) =
						$this->getJoinInfo($field, ($or ? 'LEFT OUTER' : 'INNER'));

					if (count($joins))
						$this->query['tables'] = $this->mergeJoins($this->query['tables'], $joins);

					if (is_object($value))
						$value = $value->$field;

					if (is_array($value))
						for ($k = 0; $k < sizeof($value); ++$k)
							$value[$k] = $currmodel->_fields[$field]->convertToDbValue($value[$k]);
					elseif ($operator != 'isnull')
						$value = $currmodel->_fields[$field]->convertToDbValue($value);

					$fq = $this->getFilterQuery($currmodel->_table, $field, $value, $operator);

					if ($fq) {
						$sql[] = $fq['sql'];
						$params = array_merge($params, (array)$fq['params']);
					}

				} elseif (isset($this->model->_fields[$field])) {

					if (is_array($value))
						for ($k = 0; $k < sizeof($value); ++$k)
							$value[$k] = $this->model->_fields[$field]->convertToDbValue($value[$k]);
					elseif ($operator != 'isnull')
						$value = $this->model->_fields[$field]->convertToDbValue($value);
					$fq = $this->getFilterQuery($this->tableName, $field, $value, $operator);
					if ($fq) {
						$sql[] = $fq['sql'];
						$params = array_merge($params, (array)$fq['params']);
					}
				}
			}
			if (sizeof($sql))
				$this->query['where'] = array('sql' => ($negate?'NOT(':'(').implode(' '.($or?'OR':'AND').' ', $sql).')',
											'params' => $params);
		}

		private function getFilterQuery($table, $field, $value, $operator) {

			$q = $this->mergedQuery(true);
			$duplicate = false;

			foreach($q['filters'] as $filter) {
				if ($filter[0] == $table
						&& $filter[1] == $field
						&& $filter[2] == $value
						&& $filter[3] == $operator) {
					$duplicate = true;
					break;
				}
			}

			if ($duplicate)
				return null;
			else
				$this->query['filters'][] = array($table, $field, $value, $operator);

			$sql = null;
			$params = array();
			switch ($operator) {
				case 'exact':
					// Add to default field values for future insert
					if (!$negate) $this->query['insert'][$field] = $value;
					$sql = '`'.$table.'`.`'.$field.'` = ?';
					$params[] = $value;
					break;
				case 'iexact':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = $value;
					break;
				case 'contains':
					$sql = '`'.$table.'`.`'.$field.'` LIKE ?';
					$params[] = '%'.$value.'%';
					break;
				case 'icontains':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = '%'.$value.'%';
					break;
				case 'gt':
					$sql = '`'.$table.'`.`'.$field.'` > ?';
					$params[] = $value;
					break;
				case 'gte':
					$sql = '`'.$table.'`.`'.$field.'` >= ?';
					$params[] = $value;
					break;
				case 'lt':
					$sql = '`'.$table.'`.`'.$field.'` < ?';
					$params[] = $value;
					break;
				case 'lte':
					$sql = '`'.$table.'`.`'.$field.'` <= ?';
					$params[] = $value;
					break;
				case 'in':
					if (!is_array($value) || !count($value))
						throw new InvalidParametersException('IN only accepts a non-zero length array parameter.');
					$tmp = '`'.$table.'`.`'.$field.'` IN (';
					foreach ($value as $v) {
						$tmp .= '?, ';
						$params[] = $v;
					}
					$tmp = substr($tmp, 0, -2) . ')';
					$sql = $tmp;
					break;
				case 'startswith':
					$sql = '`'.$table.'`.`'.$field.'` LIKE ?';
					$params[] = $value.'%';
					break;
				case 'istartswith':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = $value.'%';
					break;
				case 'endswith':
					$sql = '`'.$table.'`.`'.$field.'` LIKE ?';
					$params[] = '%'.$value;
					break;
				case 'iendswith':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = '%'.$value;
					break;
				case 'range':
					if (!is_array($value) || count($value) != 2)
						throw new InvalidParametersException('RANGE only accepts a two length array parameter.');
					$sql = '`'.$table.'`.`'.$field.'` >= ? AND '.$table.'.'.$field.' <= ?';
					$params[] = $value[0];
					$params[] = $value[1];
					break;
				case 'isnull':
					$sql = '`'.$table.'`.`'.$field.'` IS '.(!$value ? 'NOT ' : '').'NULL';
					break;
			}
			return array('sql' => $sql, 'params' => $params);
		}

		public function query($sql, $params) {

			$c =& $this->modelquery->getConnection();
			$qf =& $this->modelquery->factory;
			if (!$qf->inTransaction())
				$c->BeginTrans();
			$stmt = $c->prepare($sql);
			$result = $c->execute($stmt, $params);

			if ($result) {
				if (!$qf->inTransaction())
					$c->CommitTrans();
				return $result;
			} else {
				$error = $c->ErrorMsg();
				if (!$qf->inTransaction())
					$c->RollbackTrans();
				else
					$qf->fail();
				throw new SQLException('Database query failed: '.$error);
			}

		}

		public function createModels($result, $rawfields, $map = null, $preload = null) {
			$models = array();
			$lastid = null;
			$pk = $this->model->_idField;
			if ($map == 'pk')
				$map = $pk;
			$model = null;
			while (!$result->EOF) {
				if (!$preload || $result->fields[$pk] != $lastid) {
					if ($model) {
						if ($map) {
							$mapval = $model->getPrimitiveFieldValue($map);
							if ($multimap) {
								if (!isset($models[$mapval]))
									$models[$mapval] = array();
								$models[$mapval][] = $model;
							} else
								$models[$mapval] = $model;
						} else
							$models[] = $model;
					}
					$cacheKey = get_class($this->model).':'
						.$this->model->_fields[$pk]->convertFromDbValue($result->fields[$pk]);
					if ($this->modelquery->factory->cacheExists($cacheKey)) {
						$model = $this->modelquery->factory->cacheGet($cacheKey);
					} else {
						$model = $this->modelquery->create($result->fields, $rawfields, UPDATE_FROM_DB);
						$this->modelquery->factory->cachePut($cacheKey, $model);
					}
				}

				$lastid = $result->fields[$pk];
				if ($preload) {
					foreach ($preload as $field) {

						$fo = $this->model->_fields[$field];
						if ($fo && $fo instanceof RelationField) {

							$relmodel = $fo->getRelationModel();
							$relobj = null;

							$relCacheKey = get_class($relmodel).':'
								.$relmodel->_fields[$relmodel->_idField]->convertFromDbValue(
									$result->fields['__'.$field.'__'.$relmodel->_idField]);

							if ($this->modelquery->factory->cacheExists($relCacheKey)) {
								$relobj = $this->modelquery->factory->cacheGet($relCacheKey);
							} else {
								$relfields = array();
								foreach($result->fields as $f => $v) {
									$prelen = strlen($field)+4;
									if (substr($f, 0, $prelen) == '__'.$field.'__')
										$relfields[substr($f, $prelen)] = $v;
								}
								$relobj = $relmodel->getQuery()->create($relfields, null, UPDATE_FROM_DB);
								if (!$relobj->pk) continue;
								$this->modelquery->factory->cachePut(get_class($relobj).':'.$relobj->pk, $relobj);
							}

							if ($model->$field instanceof RelationSet)
								$model->$field->preadd($relobj);
							else
								$model->$field = $relobj;

						}
					}
				}
				$result->MoveNext();
			}
			if ($model) {
				if ($map) {
					$mapval = $model->getPrimitiveFieldValue($map);
					if ($multimap) {
						if (!isset($models[$mapval]))
							$models[$mapval] = array();
						$models[$mapval][] = $model;
					} else
						$models[$mapval] = $model;
				} else
					$models[] = $model;
			}
			return $models;
		}

		public function createRawHash($result, $map = null, $preload = null, $multimap = false) {

			$hash = array();
			$lastid = null;
			$pk = $this->model->_idField;
			$record = null;

			if ($map == 'pk')
				$map = $pk;

			while (!$result->EOF) {

				$record = array();

				if ($preload) {
					foreach ($result->fields as $k => $v) {
						if ((strpos($k, '__')) === 0) {
							// Preload object
							list($junk, $field, $k) = explode('__', $k);
							if (!isset($record[$field]))
								$record[$field] = array();
							$record[$field][$k] = $v;
						} elseif (!$preload || !in_array($k, $preload))
							$record[$k] = $v;
					}
				} else
					$record = $result->fields;

				if ($map) {
					if ($multimap) {
						if (!isset($hash[$record[$map]]))
							$hash[$record[$map]] = array();
						$hash[$record[$map]][] = $record;
					} else
						$hash[$record[$map]] = $record;
				} else
					$hash[] = $record;

				$result->MoveNext();

			}

			return $hash;

		}

		public function createHash($result, $rawfields, $map = null, $preload = null, $multimap = false) {

			$hash = array();
			$lastid = null;
			$pk = $this->model->_idField;
			$record = null;

			if ($map == 'pk')
				$map = $this->model->_idField;

			while (!$result->EOF) {
				$model = $this->model;
				if ($this->model->_subclassField)
					$model = $this->modelquery->getConcreteType($result->fields);
				$row =& $result->fields;
				if (!$preload || $row[$pk] != $lastid) {
					if ($record) {
						if ($map) {
							if ($multimap) {
								if (!isset($hash[$record[$map]]))
									$hash[$record[$map]] = array();
								$hash[$record[$map]][] = $record;
							} else
								$hash[$record[$map]] = $record;
						} else
							$hash[] = $record;
					}
					$record = $this->createHashFromRow($row, $model, $rawfields, $preload);
				}
				$lastid = $row[$pk];
				if ($preload) {
					foreach ($preload as $field) {
						$fo = $model->_fields[$field];
						if ($fo && $fo instanceof RelationField) {
							$relmodel = $fo->getRelationModel();
							$relfields = array();
							foreach($result->fields as $f => $v) {
								$prelen = strlen($field)+4;
								if (substr($f, 0, $prelen) == '__'.$field.'__')
									$relfields[substr($f, $prelen)] = $v;
							}
							if ($fo instanceof RelationSetField) {
								if (!isset($record[$field]))
									$record[$field] = array();
								if (!$relfields[$pk]) continue;
								$record[$field][] = $this->createHashFromRow($relfields, $relmodel);
							} else {
								if (!$relfields[$pk]) continue;
								$record[$field] = $this->createHashFromRow($relfields, $relmodel);
							}
						}
					}
				}
				$result->MoveNext();
			}
			if ($record) {
				if ($map) {
					if ($multimap) {
						if (!isset($hash[$record[$map]]))
							$hash[$record[$map]] = array();
						$hash[$record[$map]][] = $record;
					} else
						$hash[$record[$map]] = $record;
				} else
					$hash[] = $record;
			}
			return $hash;
		}

		public function createHashFromRow($row, $model, $rawfields = null, $preload = null) {

			$record = array();
			$fields = $model->_dbFields;

			foreach ($fields as $field => $def) {
				if (!$preload || !in_array($field, $preload) || !$def instanceof RelationField) {
					if (array_key_exists($field, $row)) {
						$raw = $rawfields && ($rawfields == ALLFIELDS || in_array($field, $rawfields));
						$value = $raw ? $row[$field] : $def->convertFromDbValue($row[$field]);
						if ($value instanceof Model)
							$record[$field] = $value->getFieldValues();
						else
							$record[$field] = $value;
					}
				}
			}

			// Format aggregate fields
			if ($this->fullQuery && count($cf = $this->fullQuery['converters']) > 0)
				foreach ($cf as $f => $d)
					if (array_key_exists($f, $row))
						$record[$f] = $d->convertFromDbValue($row[$f]);

			return $record;
		}

		private function affectedRows() {
			return $this->modelquery->getConnection()->Affected_Rows();
		}

		private function insertId() {
			return $this->modelquery->getConnection()->Insert_ID();
		}

		private function getError() {
			return $this->modelquery->getConnection()->ErrorMsg();
		}

		private function mergeJoins($tables1, $tables2) {
			foreach ($tables1 as $t1 => $j1) {
				if (isset($tables2[$t1])) {
					if ($j1 == 'INNER JOIN')
						unset($tables2[$t1]);
					elseif ($tables2[$t1] == 'INNER JOIN')
						unset($tables1[$t1]);
				}
			}
			return array_merge($tables1, $tables2);
		}

		// Iterator functions
		public function rewind() {
			if (!$this->models) $this->select();
			reset($this->models);
		}
		public function current() {
			if (!$this->models) $this->select();
			return current($this->models);
		}
		public function key() {
			if (!$this->models) $this->select();
			return key($this->models);
		}
		public function next() {
			if (!$this->models) $this->select();
			return next($this->models);
		}
		public function valid() {
			if (!$this->models) $this->select();
			return current($this->models) !== false;
		}

		// ArrayAccess
		public function offsetUnset($index) {
			// Blocked, can't unset model
		}
		public function offsetSet($index, $value) {
			// Blocked, can't set model
		}
		public function offsetGet($index) {
			if (!$this->models) $this->select();
			return $this->models[$index];
		}
		public function offsetExists($index) {
			if (!$this->models) $this->select();
			return array_key_exists($index, $this->models);
		}

		// Countable functions
		public function count() {
			if ($this->models) {
				// Already executed a query, return actual results
				return count($this->models);
			} else {
				// Use SQL COUNT() function to get row count without modifying current query
				return $this->sqlcount();
			}
		}
	}
