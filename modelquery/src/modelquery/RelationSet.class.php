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

	require_once('ModelFields.php');
	require_once('Exceptions.php');

	/**
	 * An iterable set of related objects.
	 */
	abstract class RelationSet implements ArrayAccess, Countable, Iterator {

		public $relations;

		protected $relationObj;
		protected $field;
		protected $persistent = false;

		public function __construct($object, $field) {
			$this->relationObj = $object;
			$this->field = $field;
			$this->relationData = array();
			$this->relations = new ArrayIterator($this->relationData);
		}

		/**
		 * Save any unlinked objects
		 */
		abstract public function save();

		/**
		 * Add an object to this relation set.
		 * @param Model $object The new related object
		 */
		public function add($object) {
			$this->relations[] = $object;
		}

		/**
		 * Remove an object from this relation set.
		 * @param Model $object The related object to remove
		 */
		public function remove($object) {
			foreach ($this->relations as $i => $r) {
				if ($r == $object) {
					unset($this->relations[$i]);
					$this->relations = array_values($this->relations);
					break;
				}
			}
		}

		/**
		 * Set the complete list of related objects.
		 * @param Array $objects An array of related Model instances
		 */
		public function set($objects) {
			$this->clear();
			if ($objects)
				foreach ($objects as $r)
					$this->add($r);
		}

		/**
		 * Remove all related objects.
		 */
		public function clear() {
			foreach ($this->relations as $rel)
				$this->remove($rel);
			if ($this->relations instanceof QueryFilter)
				$this->relations->flush();
		}

		/**
		 * Preload the object list, bypassing add triggers.  Only
		 * for use internally by ModelQuery.
		 */
		public function preload($objects) {
			$this->relations = $objects;
		}

		// Iterator
		public function rewind() {
			$this->relations->rewind();
		}
		public function current() {
			return $this->relations->current();
		}
		public function key() {
			return $this->relations->key();
		}
		public function next() {
			return $this->relations->next();
		}
		public function valid() {
			return $this->relations->valid();
		}

		// ArrayAccess
		public function offsetUnset($index) {
			if (array_key_exists($index, $this->relations))
				$this->remove($this->relations[$index]);
		}
		public function offsetSet($index, $value) {
			if (array_key_exists($index, $this->relations))
				$this->remove($this->relations[$index]);
			$this->add($value);
		}
		public function offsetGet($index) {
			return $this->relations[$index];
		}
		public function offsetExists($index) {
			return array_key_exists($index, $this->relations);
		}

		// Countable
		public function count() {
			return $this->relations->count();
		}

		/**
		 * Pluck a list of field values from the related models.
		 * @return Array An array of field values from the related objects.
		 */
		public function values($field) {
			if ($this->relations instanceof QueryFilter)
				return $this->relations->values($field);
			else {
				$values = array($field => array());
				foreach ($this->relations as $rel)
					$values[$field][] = $rel[$field];
				return $values;
			}
		}

		public function __toString() {
			$strvals = array();
			foreach ($this->relations as $item)
				$strvals[] = strval($item);
			return implode(', ', $strvals);
		}
	
		/**
		 * Preload the object list, bypassing add triggers.  Only
		 * for use internally by ModelQuery.
		 *
		 * @deprecated 2.0 Only used for preloading relations from QueryFilter::preload(),
		 *		which now uses an alternate method.
		 */
		public function preadd($object) {
			if (!$this->relations)
				$this->relations = array();
			$this->relations[] = $object;
		}

		/**
		 * Pass through other function calls to the underlying QueryFilter
		 * object, so that this set can function as a QueryFilter.
		 */
		public function __call($func, $args) {
			if ($this->relations instanceof QueryFilter)
				// Pass other function calls through to query object
				return call_user_func_array(array($this->relations, $func), $args);
			else
				throw new UnsupportedOperationException('Relation set must be saved before querying.');
		}

	}

	class OneToManyRelationSet extends RelationSet {

		public function __construct($object, $field) {
			parent::__construct($object, $field);
			if ($object->pk) {
				$this->persistent = true;
				$this->relations = $this->getRelationQuery();
			}
		}

		public function getRelationQuery() {
			$rq = $this->field->getRelationModel()->getQuery();
			return $rq->filter($this->field->joinField, $this->relationObj->pk);
		}

		public function add($object) {
			if ($this->relationObj->pk) {
				$oid = $object instanceof Model ? $object->pk : $object;

				if ($object instanceof Model) {
					$object[$this->field->joinField] = $this->relationObj;
					$object->save();
				} elseif ($oid) {
					$rq = $this->field->getRelationModel()->getQuery();
					$rq->filter('pk', $oid)->update(array($this->field->joinField => $this->relationObj->pk));
				} else {
					throw new InvalidParametersException('An object or id must be specified.');
				}

				if ($this->relations instanceof QueryFilter)
					$this->relations->flush();
			} else {
				parent::add($object);
			}
		}

		public function remove($object) {
			if ($this->relationObj->pk) {
				$oid = $object instanceof Model ? $object->pk : $object;
				if (!$oid)
					throw new InvalidParametersException('That object does not exist.');

				$rm = $this->field->getRelationModel();
				$rq = $rm->getQuery();
				if ($rm->_fields[$this->field->joinField]->options['required'])
					$rq->get($oid)->delete();
				else
					$rq->filter('pk', $oid)->update(array($this->field->joinField => null));

				if ($this->relations instanceof QueryFilter)
					$this->relations->flush();
			} else {
				parent::remove($object);
			}
		}

		public function save() {
			if (!$this->persistent && $this->relationObj->pk) {
				$this->persistent = true;
				foreach ($this->relations as $r)
					$this->add($r);
				$this->relations = $this->getRelationQuery();
			}
		}

	}

	class ManyToManyRelationSet extends RelationSet {

		public function __construct($object, $field) {
			parent::__construct($object, $field);
			if ($object->pk) {
				$this->persistent = true;
				$this->relations = $this->getRelationQuery();
			}
		}

		public function getRelationQuery() {
			$rq = $this->field->getRelationModel()->getQuery();
			$jt = $this->field->getJoinModel()->_table;
			$join = array('`'.$jt.'` ON (`'.$jt.'`.`'.$this->field->targetField.'` = `'.$rq->tableName.'`.`'.$rq->model->_idField.'`)' => 'INNER JOIN');
			$where = '`'.$jt.'`.`'.$this->field->joinField.'` = ?';
			$params = array($this->relationObj->pk);
			return $rq->join($join)->condition($where, $params);
		}

		public function add($object) {
			if ($this->relationObj->pk) {
				$oid = $object instanceof Model ? $object->pk : $object;
				if (!$oid && !($object instanceof Model))
					throw new InvalidParametersException('An object or id must be specified.');

				if (!$oid && $object instanceof Model) {
					$rq = $this->field->getRelationModel()->getQuery();
					$object->save();
					$oid = $object->pk;
				}

				$jq = $this->field->getJoinModel()->getQuery();
				$join = $jq->create(array($this->field->joinField => $this->relationObj->pk,
										$this->field->targetField => $oid));
				$join->save();

				if ($this->relations instanceof QueryFilter)
					$this->relations->flush();
			} else {
				parent::add($object);
			}
		}

		public function remove($object) {
			if ($this->relationObj->pk) {
				$oid = $object instanceof Model ? $object->pk : $object;
				if (!$oid)
					throw new InvalidParametersException('That object does not exist.');

				$jq = $this->field->getJoinModel()->getQuery();
				$join = $jq->filter($this->field->joinField, $this->relationObj->pk,
									$this->field->targetField, $oid)->one();
				$join->delete();

				if ($this->relations instanceof QueryFilter)
					$this->relations->flush();
			} else {
				parent::add($object);
			}
		}

		public function save() {
			if (!$this->persistent && $this->relationObj->pk) {
				$this->persistent = true;
				foreach ($this->relations as $r)
					$this->add($r);
				$this->relations = $this->getRelationQuery();
			}
		}


	}
