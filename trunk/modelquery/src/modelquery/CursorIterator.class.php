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

	/**
	 * An iterable class that wraps a database result, functioning
	 * as a scrollable cursor.
	 *
	 * AbstractCursorIterator can be used in foreach loops or with direct
	 * array index access.
	 */
	abstract class AbstractCursorIterator implements ArrayAccess, Iterator, Countable {

		private $queryhandler;
		private $result;
		private $aggregates;

		private $position = false;
		private $reccount = 0;
		private $current = null;

		public function __construct($queryhandler_, $result_, $aggregates_ = null) {
			$this->queryhandler = $queryhandler_;
			$this->result = $result_;
			$this->aggregates = $aggregates_;
			if ($result_ && !$result_->EOF) {
				$this->position = 0;
				$this->reccount = $result_->RecordCount();
			}
		}

		// Iterator functions
		public function rewind() {
			if ($this->result && $this->position !== false) {
				$this->result->MoveFirst();
				$this->position = 0;
				$this->current = null;
			}
		}

		public function current() {
			if ($this->result && !$this->result->EOF) {
				if (!$this->current)
					$this->current = $this->createModel($this->queryhandler, $this->result->fields, $this->aggregates);
				return $this->current;
			}
			return false;
		}

		public function key() {
			return $this->position;
		}

		public function next() {
			if ($this->result) {
				$this->result->MoveNext();
				$this->current = null;
				if ($this->result->EOF)
					$this->position = false;
				else
					$this->position = $this->result->CurrentRow();
				return $this->current();
			}
			return false;
		}

		public function valid() {
			if ($this->result)
				return $this->position !== false;
			return false;
		}

		// ArrayAccess functions
		public function offsetUnset($index) {
			// Ignore
		}

		public function offsetSet($index, $value) {
			// Ignore
		}

		public function offsetGet($index) {
			if ($this->result) {
				if ($index == $this->position + 1) {
					return $this->next();
				} elseif ($index != $this->position) {
					$this->result->Move($index);
					$this->current = null;
				}
				return $this->current();
			}
			return false;
		}

		public function offsetExists($index) {
			if ($this->result)
				return $index < $this->reccount;
			return false;
		}

		// Countable functions
		public function count() {
			if ($this->result)
				return $this->reccount;
			return 0;
		}

		public function close() {
			if ($this->result) {
				$this->result->Close();
				$this->result = null;
			}
		}

		public abstract function createModel($queryhandler, $fields, $raw);

	}

	/**
	 * An iterable class that wraps a database result and returns
	 * Model instances for each record.
	 *
	 * CursorIterator can be used in foreach loops or with direct
	 * array index access.
	 */
	class CursorIterator extends AbstractCursorIterator {

		public function createModel($queryhandler, $fields, $raw) {

			$pk = $queryhandler->model->_idField;
			$model = null;

			$cacheKey = get_class($queryhandler->model).':'
				.$queryhandler->model->_fields[$pk]->convertFromDbValue($fields[$pk]);

			if ($queryhandler->factory->cacheExists($cacheKey)) {
				$model = $queryhandler->factory->cacheGet($cacheKey);
			} else {
				$model = $queryhandler->create($fields, $raw, UPDATE_FROM_DB);
				// Don't cache things dumbass, the whole purpose is to save memory.
				//$queryhandler->factory->cachePut($cacheKey, $model);
			}

			return $model;

		}

	}

	/**
	 * An iterable class that wraps a database result and returns
	 * hash arrays for each record.
	 *
	 * HashCursorIterator can be used in foreach loops or with direct
	 * array index access.
	 */
	class HashCursorIterator extends AbstractCursorIterator {

		public function createModel($queryhandler, $fields, $raw) {
			return $queryhandler->createHashFromRow($fields, $queryhandler->model, $raw);
		}

	}
