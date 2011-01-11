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
	require_once('Validators.php');
	require_once('Exceptions.php');

	define('CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP');
	define('UPDATE_FROM_FORM', 1);
	define('UPDATE_FORCE_BOOLEAN', 2);
	define('UPDATE_FROM_DB', 4);

	abstract class Model implements ArrayAccess, Countable, Iterator {
		
		// Needs to be protected for __set calls, but DON'T ACCESS MANUALLY
		// Prefixing with "_" to avoid column name conflicts
		public $_idField;
		public $_fields = array();
		public $_dbFields = array();
		public $_table;
		public $_subclassField;
		public $_subclassFilter;

		protected $_rawValues = array();
		protected $_fieldValues = array();
		protected $_fieldRelationValues = array();
		protected $_extraValues = array();
		protected $_valuesConverted = false;
		protected $_orderedFields = array();
		protected $_dbId;
		protected $_defaultOrder;

		protected $_version = 1;
		protected $_versionChanges = 0;
		protected $_versionValues = array();

		protected $_validators = array();
		protected $_errors;
		protected $_fieldErrors;

		protected $_query;
		protected $_appName;
		protected $_configMode;

		public function __construct(&$query_, $tableName_ = null, $appName_ = null) {
			$this->_query =& $query_;
			$this->_appName = $appName_;
			$this->_table = $tableName_;

			// Subclass configuration
			$this->_configMode = true;
			$this->configure();
			$this->_configMode = false;
		}

		public function __clone() {
			$this->_rawValues = array();
			$this->_fieldValues = array();
			$this->_fieldRelationValues = array();
		}

		public function configure() {
		}

		protected function &addField($name, $field) {
			$field->factory =& $this->_query->factory;
			$field->query =& $this->_query;
			$field->field = $name;
			if (!($field instanceof RelationSetField) 
					&& !($field instanceof RelationField && $field->options['reverseJoin'])) {
				if ($field->options['pk'])
					$this->_idField = $name;
				if ($field instanceof OrderedField)
					$this->_orderedFields[$name] =& $field;
				if ($field->options['unique'])
					$field->addValidator(new UniqueFieldValidator($name), 'That '.strtolower($field->name).' is not available.');
				$this->_dbFields[$name] =& $field;
			}
			$this->_fields[$name] =& $field;
		}

		public function getQuery() {
			return $this->_query;
		}

		public function getFactory() {
			return $this->_query->factory;
		}

		public function orderedFields() {
			return $this->_orderedFields;
		}

		// Field setting
		public function setFieldValue($name, $value, $raw = false) {
			if ($name == 'pk')
				$name = $this->_idField;
			$field = $this->_fields[$name];
			if ($field) {
				if ($field instanceof RelationField) {
					$relid = $field->setRelation($this, $value);
					if ($value instanceof Model)
						$this->_fieldRelationValues[$name] = $value;
					if ($relid)
						$this->setPrimitiveFieldValue($name, $relid);
					else
						$this->setPrimitiveFieldValue($name, null);
				} else {
					$this->setPrimitiveFieldValue($name, $field->convertToDbValue($value), $raw);
				}
			}
		}

		public function setPrimitiveFieldValue($name, $value, $raw = false) {
			if ($name == 'page')
			if ($name == 'pk')
				$name = $this->_idField;
			$this->_rawValues[$name] = $value;
			if (isset($this->_fieldRelationValues[$name]))
				unset($this->_fieldRelationValues[$name]);
			if ($raw)
				$this->_fieldValues[$name] = $value;
			elseif ($this->_valuesConverted || array_key_exists($name, $this->_fieldValues)) {
				$field = $this->_fields[$name];
				$this->_fieldValues[$name] =
					$field->convertFromDbValue($this->_rawValues[$name]);
			}
			if (!array_key_exists($name, $this->_versionValues) || $this->_versionValues[$name] !== $value)
				$this->_versionChanges++;
		}

		public function &getRawValues() {
			return $this->_rawValues;
		}

		public function &getFieldValues() {
			if (!$this->_valuesConverted) {
				// Prime the _fieldValues array
				foreach ($this->_rawValues as $field => $val)
					$this->getPrimitiveFieldValue($field);
				$this->_valuesConverted = true;
			}
			return $this->_fieldValues;
		}

		public function toArray() {
			$values = array();
			foreach ($this->_fields as $field => $def)
				$values[$field] = $this->getFieldValue($field);
			return $values;
		}

		public function toJSON() {
			$members = array();
			foreach ($this->_fields as $field => $def)
				$members[] = '\''.$field.'\': \''.$this->getFieldValue($field).'\'';
			return '{ '.implode(",\n", $members).' }';
		}

		public function getFieldValue($name) {
			if ($name == 'pk')
				$name = $this->_idField;
			$field = $this->_fields[$name];
			if ($field) {
				if ($field instanceof RelationField) {
					if (!array_key_exists($name, $this->_fieldRelationValues))
						try {
							$this->_fieldRelationValues[$name] = $field->getRelation($this, $this->getPrimitiveFieldValue($name));
						} catch (DoesNotExistException $dne) {
							return null;
						}
					return $this->_fieldRelationValues[$name];
				} else {
					return $this->getPrimitiveFieldValue($name);
				}
			} else if (isset($this->_extraValues[$name])) {
				return $this->_extraValues[$name];
			}
			return null;
		}

		public function getPrimitiveFieldValue($name) {
			if ($name == 'pk')
				$name = $this->_idField;
			if (array_key_exists($name, $this->_fieldValues)) {
				return $this->_fieldValues[$name];
			} elseif (array_key_exists($name, $this->_rawValues)) {
				$field = $this->_fields[$name];
				$this->_fieldValues[$name] =
					$field->convertFromDbValue($this->_rawValues[$name]);
				return $this->_fieldValues[$name];
			}
			return null;
		}

		public function getPreviousFieldValue($name) {
			if ($name == 'pk')
				$name = $this->_idField;
			if (array_key_exists($name, $this->_versionValues))
				return $this->_versionValues[$name];
			return null;
		}

		public function getPreviousFieldValues() {
			return $this->_versionValues;
		}

		public function isExtraField($name) {
			return isset($this->_extraValues[$name]);
		}

		public function isChanged($name = null) {
			if ($name) {
				if ($name == 'pk')
					$name = $this->_idField;
				if (array_key_exists($name, $this->_versionValues))
					return ($this->_versionValues[$name] !== $this->_rawValues[$name]);
				else
					return array_key_exists($name, $this->_rawValues);
			} else
				return ($this->_versionChanges > 0);
		}

		public function revert($name = null) {
			if ($name) {
				if ($name == 'pk')
					$name = $this->_idField;
				if (array_key_exists($name, $this->_versionValues)) {
					$this->_rawValues[$name] = $this->_versionValues[$name];
					unset($this->_fieldValues[$name]);
					$this->_versionChanges--;
				} else if (array_key_exists($name, $this->_rawValues)) {
					unset($this->_rawValues[$name]);
					$this->_versionChanges--;
				}
			} else {
				$this->_rawValues = $this->versionValues;
				$this->resetChangeTracking();
			}
		}

		public function __set($name, $value) {
			if ($name == 'pk')
				$name = $this->_idField;
			if ($this->_configMode)
				$this->addField($name, $value);
			else
				$this->setFieldValue($name, $value);
		}

		public function __get($name) {

			if ($name{0} == '_')
				return $this->$name;
			elseif ($name == 'pk')
				$name = $this->_idField;

			if ($this->_configMode)
				return $this->_fields[$name];
			else
				return $this->getFieldValue($name);

		}

		public function validate($field = null) {

			if ($field)
				return $this->validateField($field);

			$valid = sizeof($this->_errors) == 0 && sizeof($this->_fieldErrors) == 0;
			// Check for primary key changes
			if ($this->_dbId && $this->pk && $this->_dbId != $this->pk) {
				if ($this->_query->filter('pk', $this->pk)->count() > 0) {
					$this->addFieldError($this->_idField, 'That '.strtolower($this->_fields[$this->_idField]->getName()).' is not available.');
					$valid = false;
				}
			}
			// Individual field validations
			foreach ($this->_fields as $field => $def) {
				if (($field != $this->_idField || array_key_exists($this->_idField, $this->getFieldValues())) && !$def->validate($this->getFieldValue($field), $this)) {
					$errors = $def->validationErrors();
					if ($errors && count($errors))
						$this->addFieldErrors($field, $def->validationErrors());
					$valid = false;
				}
			}
			// Full object validations (multi-field dependencies)
			foreach ($this->_validators as $validator) {
				if (!$validator[0]->validate($this)) {
					$this->addError($validator[1]);
					$valid = false;
				}
			}
			return $valid;
		}

		private function validateField($field) {

			$valid = true;

			if (isset($this->_fields[$field])) {
				$def = $this->_fields[$field];
				unset($this->_fieldErrors[$field]);
				if (($field != $this->_idField || array_key_exists($this->_idField, $this->getFieldValues())) && !$def->validate($this->getFieldValue($field), $this)) {
					$errors = $def->validationErrors();
					if ($errors && count($errors))
						$this->addFieldErrors($field, $def->validationErrors());
					$valid = false;
				}
			}

			return $valid;

		}

		public function isPersistent() {
			return $this->_dbId != null;
		}

		public function setTable($name_) {
			$this->_table = $name_;
		}

		public function getTable() {
			return $this->_table;
		}

		public function setDefaultOrder() {
			$args = func_get_args();
			$this->_defaultOrder = $args;
		}

		public function getDefaultOrder() {
			return $this->_defaultOrder;
		}

		public function addValidator($validator, $message) {
			if (!$this->_validators) $this->_validators = array();
			$this->_validators[] = array($validator, $message);
		}

		public function hasFieldErrors() {
			return $this->_fieldErrors && sizeof($this->_fieldErrors) > 0;
		}

		public function clearFieldErrors() {
			$this->_fieldErrors = array();
		}

		public function addFieldError($field, $error) {
			$this->addFieldErrors($field, array($error));
		}

		public function addFieldErrors($field, $errors) {
			if (!$this->_fieldErrors) $this->_fieldErrors = array();
			if (!$this->_fieldErrors[$field]) $this->_fieldErrors[$field] = array();
			$this->_fieldErrors[$field] = array_merge($this->_fieldErrors[$field], $errors);
		}

		public function getFieldErrors() {
			return $this->_fieldErrors;
		}

		public function hasErrors() {
			return $this->_errors && sizeof($this->_errors) > 0;
		}

		public function clearErrors() {
			$this->_errors = array();
		}

		public function addError($error) {
			if (!$this->_errors) $this->_errors = array();
			$this->_errors[] = $error;
		}

		public function getErrors() {
			return $this->_errors;
		}

		public function &updateModel($params, $rawvalues = null, $flags = 0) {
			if ($params !== null) {
				if (($flags & UPDATE_FROM_DB) && isset($params[$this->_idField]))
					$this->_dbId = $params[$this->_idField];
				foreach ($this->_fields as $field => &$def) {
					if ($def instanceof RelationSetField)
						continue;
					if (array_key_exists($field, $params)) {
						// Don't let users update readonly fields
						if ($def->options['readonly']
								&& ($flags & UPDATE_FROM_FORM)
								&& isset($params[$this->_idField]))
							continue;
						// Blank password means don't change, since we can't access the raw password
						if ($def instanceof PasswordField && !$params[$field])
							continue;
						$v = $params[$field];
						if (!($flags & UPDATE_FROM_DB))
							$v = $def->convertToDbValue($v);
						$this->setPrimitiveFieldValue($field, $v, ($rawvalues && ($rawvalues == ALLFIELDS || in_array($field, $rawvalues))));
						// Remove found fields
						unset($params[$field]);
					} elseif ($def instanceof BooleanField && ($flags & UPDATE_FORCE_BOOLEAN)) {
						$this->setPrimitiveFieldValue($field, $def->convertToDbValue(false));
					} elseif (!array_key_exists($field, $this->_rawValues) && $field != $this->_idField) {
						$v = $def->getDefaultValue();
						if ($v instanceof ModelField)
							$v = $params[$v->getField()];
						if ($v !== null)
							$this->setPrimitiveFieldValue($field, $def->convertToDbValue($v));
					}
				}
				$this->_extraValues = array_merge($this->_extraValues, $params);
				if ($flags & UPDATE_FROM_DB)
					$this->resetChangeTracking();
			} elseif (!$this->_dbId) {
				foreach ($this->_fields as $field => $def) {
					$v = $def->getDefaultValue();
					if ($v !== null)
						$this->setPrimitiveFieldValue($field, $def->convertToDbValue($v));
				}
			}
		}

		public function save($skip = null) {
			// Cascade-save all unsaved related fields first
			if (!$skip) $skip = array();
			$skip[] = $this;
			foreach ($this->_fields as $field => $def) {
				if ($def instanceof ManyToOneField) {
					if (array_key_exists($field, $this->_fieldRelationValues)
							&& !$this->getFieldValue($field)
							&& !in_array($this->_fieldRelationValues[$field], $skip)) {
						if ($this->_fieldRelationValues[$field])
							$relid = $this->_fieldRelationValues[$field]->save($skip);
						if ($relid)
							$this->setPrimitiveFieldValue($field, $relid);
					}
				}
			}

			// Don't save again if no changes were made
			if ($this->_versionChanges == 0)
				return $this->_dbId;

			if ($this->validate()) {
				if ($this->_dbId) {
					// Pulled from database originally
					// (this is the only way to change the primary key)
					$this->_query->update($this, $this->_dbId);
				} elseif ($this->pk && $this->_query->filter('pk', $this->pk)->count()) {
					// If id specified, check for existence first
					$this->_dbId = $this->_query->update($this, $this->pk);
				} else {
					$this->_dbId = $this->_query->insert($this);
					$this->setFieldValue('pk', $this->_dbId);
				}
				// Save to factory cache so we don't need to reload
				$this->_query->factory->cachePut(get_class($this).':'.$this->pk, $this);
				$this->resetChangeTracking();
				return $this->_dbId;
			} else {
				throw new ValidationException(get_class($this).' could not be saved (did you forget some required fields?)', $this);
			}
		}

		public function delete() {
			if ($this->_dbId)
				$this->_query->filter('pk', $this->_dbId)->delete();
			else
				throw new DoesNotExistException('No matching objects were found in the database.');
		}

		private function resetChangeTracking() {
			$this->_version++;
			$this->_versionChanges = 0;
			$this->_versionValues = $this->_rawValues;
		}

		public function mapConcreteTypes($field, $map) {
			$this->_subclassField = $field;
			$this->_subclassFilter = $map;
		}

		public function toQueryParams() {
			$serialized = '';
			$first = true;
			foreach ($this->_rawValues as $f => $v) {
				if (!$first)
					$serialized .= '&';
				$serialized .= $f.'='. ($v ? rawurlencode($v) : '');
				$first = false;
			}
			return $serialized;
		}

		// Iterator
		public function rewind() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			reset($this->_fieldValues);
		}
		public function current() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return current($this->_fieldValues);
		}
		public function key() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return key($this->_fieldValues);
		}
		public function next() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return next($this->_fieldValues);
		}
		public function valid() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return current($this->_fieldValues) !== false;
		}

		// ArrayAccess
		public function offsetUnset($index) {
			if ($index == 'pk')
				$index = $this->_idField;
			unset($this->_rawValues[$index]);
			unset($this->_fieldValues[$index]);
		}
		public function offsetSet($index, $value) {
			$this->setFieldValue($index, $value);
		}
		public function offsetGet($index) {
			return $this->getFieldValue($index);
		}
		public function offsetExists($index) {
			if ($index == 'pk')
				$index = $this->_idField;
			return array_key_exists($index, $this->_rawValues);
		}

		// Countable
		public function count() {
			return count($this->_rawValues);
		}

		public function __toString() {
			return '['.get_class($this).']';
		}

	}

	class EmbeddedModel extends Model{

		public function __construct($model) {
			parent::__construct($model->_query);
		}

		public function save() {
			return false;
		} 

		public function delete() {
			return false;
		}

	}
