<?php

	class LRUCache implements IteratorAggregate {

		private $cache;
		private $lru;
		private $capacity;
		private $accessBased;

		public function __construct($capacity, $accessBased = true) {
			$this->cache = array();
			$this->lru = new LinkedList();
			$this->capacity = $capacity;
			$this->accessBased = $accessBased;
		}

		public function put($key, $data) {
			$key = strval($key);
			if (isset($this->cache[$key]))
				$this->lru->remove($key);
			$this->lru->append($key);
			$this->cache[$key] = $data;
			while ($this->lru->size() > $this->capacity) {
				$stale = $this->lru->removeFirst();
				unset($this->cache[$stale]);
			}
		}

		public function get($key) {
			$key = strval($key);
			if (isset($this->cache[$key])) {
				if ($this->accessBased) {
					$this->lru->remove($key);
					$this->lru->append($key);
				}
				return $this->cache[$key];
			}
			return null;
		}

		public function exists($key) {
			return isset($this->cache[strval($key)]);
		}

		public function remove($key) {
			$key = strval($key);
			if (isset($this->cache[$key])) {
				$data = $this->cache[$key];
				$this->lru->remove($key);
				unset($this->cache[$key]);
				return $data;
			}
			return null;
		}

		public function size() {
			return $this->lru->size();
		}

		public function capacity() {
			return $this->capacity;
		}

		public function getIterator() {
			return new LRUCacheIterator($this->lru->getIterator(), $this->cache);
		}

	}

	class LRUCacheIterator implements Iterator {

		private $keyItr;
		private $cacheMap;

		private $key;
		private $value;

		public function __construct($keyItr, &$cacheMap) {
			$this->keyItr = $keyItr;
			$this->cacheMap = $cacheMap;
			$this->key = $keyItr->current();
			$this->value = $cacheMap[$this->key];
		}

		public function current() {
			return $this->value;
		}

		public function key() {
			return $this->key;
		}

		public function next() {
			$this->key = $this->keyItr->next();
			$this->value = $this->cacheMap[$this->key];
			return $this->value;
		}

		public function rewind() {
			$this->key = $this->keyItr->rewind();
			$this->value = $this->cacheMap[$this->key];
			return $this->value;
		}

		public function valid() {
			return $this->keyItr->valid();
		}

	}
	class LinkedList implements IteratorAggregate {

		private $_firstNode;
		private $_lastNode;
		private $_count;

		public function __construct() {
			$this->_firstNode = NULL;
			$this->_lastNode = NULL;
			$this->_count = 0;
		}

		public function append($data) {
			$n = new ListNode($data);
			if ($this->_firstNode === null)
				$this->_firstNode = $n;
			if ($this->_lastNode !== null)
				$this->_lastNode->next = $n;
			$n->previous = $this->_lastNode;
			$this->_lastNode = $n;
			$this->_count++;
		}

		public function prepend($data) {
			$n = new ListNode($data);
			if ($this->_lastNode === null)
				$this->_lastNode = $n;
			if ($this->_firstNode !== null)
				$this->_firstNode->previous = $n;
			$n->next = $this->_firstNode;
			$this->_firstNode = $n;
			$this->_count++;
		}

		public function remove($data) {
			$node = $this->_firstNode;
			while ($node !== null) {
				if ($node->data == $data) {
					if ($node === $this->_firstNode)
						return $this->removeFirst();
					elseif ($node === $this->_lastNode)
						return $this->removeLast();
					else {
						$node->previous->next = $node->next;
						$node->next->previous = $node->previous;
						$this->_count--;
						return $node->data;
					}
				}
				$node = $node->next;
			}
			return null;
		}

		public function removeFirst() {
			$first = $this->_firstNode;
			if ($first !== null) {
				if ($first->next != null) {
					$second = $first->next;
					$second->previous = null;
					$this->_firstNode = $second;
				} else
					$this->_firstNode = null;
				$this->_count--;
				if ($this->_count == 0)
					$this->_lastNode = null;
				return $first->data;
			}
			return null;
		}

		public function removeLast() {
			$last = $this->_lastNode;
			if ($last !== null) {
				if ($last->previous != null) {
					$second = $last->previous;
					$second->next = null;
					$this->_lastNode = $second;
				} else
					$this->_lastNode = null;
				$this->_count--;
				if ($this->_count == 0)
					$this->_firstNode = null;
				return $last->data;
			}
			return null;
		}

		public function first() {
			return $this->_firstNode;
		}

		public function last() {
			return $this->_lastNode;
		}

		public function size() {
			return $this->_count;
		}

		public function getIterator() {
			return new LinkedListIterator($this);
		}

	}

	class ListNode {

		public $data;
		public $next;
		public $previous;

		public function __construct($data) {
			$this->data = $data;
		}

	}

	class LinkedListIterator implements Iterator {

		private $list;
		private $idx;
		private $current;

		public function __construct($list) {
			$this->list = $list;
			$this->idx = 0;
			$this->current = $list->first();
		}

		public function current() {
			return $this->current->data;
		}

		public function key() {
			return $this->idx;
		}

		public function next() {
			$this->current = $this->current->next;
			$this->idx++;
			return $this->current->data;
		}

		public function rewind() {
			$this->current = $this->list->first();
			$this->idx = 0;
			return $this->current->data;
		}

		public function valid() {
			return $this->current !== null;
		}

	}
