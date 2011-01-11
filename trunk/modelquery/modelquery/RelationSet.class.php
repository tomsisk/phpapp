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

	require_once('ModelFields.php');
	require_once('Exceptions.php');

	abstract class RelationSet implements ArrayAccess, Countable, Iterator {

		protected $relations;

		protected $relationObj;
		protected $field;

		public function __construct($object, $field) {
			$this->relationObj = $object;
			$this->field = $field;
		}

		abstract public function add($object);
		abstract public function remove($object);

		public function set($objects) {
			$this->clear();
			if ($objects)
				foreach ($objects as $r)
					$this->add($r);
		}

		public function clear() {
			foreach ($this->relations as $rel)
				$this->remove($rel);
			if ($this->relations instanceof QueryFilter)
				$this->relations->flush();
		}

		// Initializes the internal dataset
		public function preload($objects) {
			$this->relations = $objects;
		}

		// Adds to the internal dataset
		public function preadd($object) {
			if (!$this->relations)
				$this->relations = array();
			$this->relations[] = $object;
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

		public function __call($func, $args) {
			// Pass other function calls through to query object
			return call_user_func_array(array($this->relations, $func), $args);
		}

		//public function __toString() {
		//	return '['.get_class($this).']';
		//}
		public function __toString() {
			$strvals = array();
			foreach ($this->relations as $item)
				$strvals[] = strval($item);
			return implode(', ', $strvals);
		}
	
	}

	class OneToManyRelationSet extends RelationSet {

		public function __construct($object, $field) {
			parent::__construct($object, $field);
			$rq = $field->getRelationModel()->getQuery();
			$this->relations = $rq->filter($field->joinField, $object->pk);
		}

		public function add($object) {
			$oid = $object instanceof Model ? $object->pk : $object;

			if (!$oid && $object instanceof Model) {
				$object[$this->field->joinField] = $this->relationObj->pk;
				$object->save();
			} elseif ($oid) {
				$rq = $this->field->getRelationModel()->getQuery();
				$rq->filter('pk', $oid)->update(array($this->field->joinField => $this->relationObj->pk));
			} else {
				throw new InvalidParametersException('An object or id must be specified.');
			}

			if ($this->relations instanceof QueryFilter)
				$this->relations->flush();
		}

		public function remove($object) {
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
		}

	}

	class ManyToManyRelationSet extends RelationSet {

		public function __construct($object, $field) {
			parent::__construct($object, $field);
			$rq = $field->getRelationModel()->getQuery();
			$jt = $field->getJoinModel()->_table;
			$qt = $rq->tableName;
			$where = $jt.'.'.$field->joinField.' = ?';
			$join = array($jt.' ON ('.$jt.'.'.$field->targetField.' = '.$qt.'.'.$rq->model->_idField.')' => 'INNER JOIN');
			$params = array($object->pk);
			$this->relations = $rq->extra(null, $where, $params, $join);
		}

		public function add($object) {
			$oid = $object instanceof Model ? $object->pk : $object;
			if (!$oid && !($object instanceof Model))
				throw new InvalidParametersException('An object or id must be specified.');

			if (!$oid && $object instanceof Model) {
				$rq = $this->field->getRelationModel()->getQuery();
				$rq->save($object);
				$oid = $object->pk;
			}

			$jq = $this->field->getJoinModel()->getQuery();
			$join = $jq->create(array($this->field->joinField => $this->relationObj->pk,
									$this->field->targetField => $oid));
			$join->save();

			if ($this->relations instanceof QueryFilter)
				$this->relations->flush();
		}

		public function remove($object) {
			$oid = $object instanceof Model ? $object->pk : $object;
			if (!$oid)
				throw new InvalidParametersException('That object does not exist.');

			$jq = $this->field->getJoinModel()->getQuery();
			$join = $jq->filter($this->field->joinField, $this->relationObj->pk,
								$this->field->targetField, $oid)->one();
			$join->delete();

			if ($this->relations instanceof QueryFilter)
				$this->relations->flush();
		}

	}
