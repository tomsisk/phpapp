<?php
	//! \file Model.class.php
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
	 * This library is distributed in the hope that it will be useful, * but WITHOUT ANY WARRANTY; without even the implied warranty of
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

	//! The current timestamp
	define('CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP');
	//! Flag indicating that the input data was submitted
	//! by a web form, and requires validation and conversion
	//! to PHP types.
	define('UPDATE_FROM_FORM', 1);
	//! Flag indicating that the input data does not include
	//! values for boolean values that are false; any missing
	//! boolean fields should default to false.
	define('UPDATE_FORCE_BOOLEAN', 2);
	//! Flag indicating that the input data was loaded from
	//! the database, and requires conversion to PHP types.
	define('UPDATE_FROM_DB', 4);

	//! Data model representing a record in the database.
	/*!
		\class Model
		Model is the base class for all of your data model definitions.
		To define a model, you must subclass this Model class.  Field
		definitions and configuration options should be handled by
		overriding Model::configure() in your subclass. When a ModelQuery
		handler is created for a Model subclass (by QueryFactory->get(),
		the model class is instantiated.  As part of the initialization,
		configure() is called to setup field definitions.

		Result sets returned by QueryFilter will be objects of the
		specified subclass type (except in the case of QueryFilter::hash()
		and QueryFilter::raw()).

		Subclass example:

		\code
class Book extends Model {

	public function configure() {
		$this->id = new IntegerField('ID', array('pk' => true));
		$this->title = new CharField('Title', 100, array('required' => true));
		$this->published = new DateField('Publication Date', array('default' => CURRENT_TIMESTAMP));
		$this->format = new CharField('Format', 20, array(
			'options' => array('H' => 'Hardcover', 'P' => 'Paperback', 'A' => 'Audiobook')
			));
		$this->setDefaultOrder('+title');
	}

	public function __toString() {
		return strval($this->title);
	}

}
		\endcode

		Implementation note: public attributes should not be accessed directly.
		Fields starting with an underscore (_) should be considered private
		by your code.

		\sa ModelQuery
		\sa ModelField
		\implements Iterator
		\implements ArrayAccess
		\implements Countable
	*/
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

		//! Create a new Model object
		/*!
			This constructor should not be called directly; all Model subclasses should
			be instantiated by ModelQuery.
			\param ModelQuery $query_ The ModelQuery object that is managing this model
			\param string $tableName_ The database table name for model persistence
			\param string $appName_ The application name from the QueryFactory
			\sa QueryFactory::get()
		*/
		public function __construct(&$query_, $tableName_ = null, $appName_ = null) {
			$this->_query =& $query_;
			$this->_appName = $appName_;
			$this->_table = $tableName_;

			// Subclass configuration
			$this->_configMode = true;
			$this->configure();
			$this->_configMode = false;
		}

		//! Clone constructor
		public function __clone() {
			$this->_rawValues = array();
			$this->_fieldValues = array();
			$this->_fieldRelationValues = array();
		}

		//! Configure this model.
		/*!
			Subclasses of Model should override this function to define
			field types and other configuration options.  To define a field,
			use the following syntax:

			$this->fieldName = new CharField('My Field', 100);

			Other model configuration options should be set from configure()
			as well, such as Model::setTable(), Model::setDefaultOrder(), etc.

			\sa ModelField
		*/
		public function configure() {
		}

		//! Add a new field definition to this Model
		/*!
			\param string $name The field name in the database
			\param ModelField $field A field definition object
		*/
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

		//! Retrieve the ModelQuery object that manages this model
		/*!
			\return ModelQuery The query manager
		*/
		public function getQuery() {
			return $this->_query;
		}

		//! Retrieve the QueryFactory associated with this model
		/*!
			\return QueryFactory The query factory
		*/
		public function getFactory() {
			return $this->_query->factory;
		}

		//! Get a list of all OrderedField definitions.
		public function orderedFields() {
			return $this->_orderedFields;
		}

		//! Set a field's value.
		/*!
			A more common way to set field values is using standard attributes
			(i.e. $model->title => $value), or via array access ($model['title']
			= $value) since this class implements ArrayAccess.  These are both
			synonymous to $model->setFieldValue('title', $value).

			\param string $name The field name
			\param mixed $value The field value
			\param bool $raw If true, this is a raw value that should not
					be type converted
			\sa Model::__set()
			\sa Model::offsetSet()
		*/
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

		//! Set a raw field value, bypassing type conversion
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

		//! Get a hash array of raw name/value pairs, as they are stored in the database
		/*!
			\return Array A hash of name/value pairs
		*/
		public function &getRawValues() {
			return $this->_rawValues;
		}

		//! Get an array of type-converted name/value pairs.
		/*!
			For relation fields, this will return their primary key
			value rather than the related object itself.
			\sa Model::toArray()
		*/
		public function &getFieldValues() {
			if (!$this->_valuesConverted) {
				// Prime the _fieldValues array
				foreach ($this->_rawValues as $field => $val)
					$this->getPrimitiveFieldValue($field);
				$this->_valuesConverted = true;
			}
			return $this->_fieldValues;
		}

		
		//! Get an array of type-converted name/value pairs.
		/*!
			Unlike Model::getFieldValues(), this returns relation
			objects instead of primary keys.
			\sa Model::getFieldValues()
		*/
		public function toArray() {
			$values = array();
			foreach ($this->_fields as $field => $def)
				$values[$field] = $this->getFieldValue($field);
			return $values;
		}

		//! Return a JSON representation of this object.
		/*!
			\return string Field/value data in JSON notation
		*/
		public function toJSON() {
			$members = array();
			foreach ($this->_fields as $field => $def)
				$members[] = '\''.$field.'\': \''.$this->getFieldValue($field).'\'';
			return '{ '.implode(",\n", $members).' }';
		}

		//! Get a field's value.
		/*!
			If the field is a RelationField, this will return the related object
			(querying the database if necessary) rather than the object's ID.

			A more common way to retrieve field values is using standard attributes
			(i.e. $model->title), or via array access ($model['title']) since this
			class implements ArrayAccess.  These are both synonymous to
			$model->getFieldValue('title').

			\param string $name The field name
			\return mixed The field value
			\sa Model::__get()
			\sa Model::offsetGet()
		*/
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

		//! Get a field's raw value, as stored in the database.
		/*!
			Unlike getFieldValue, this method does no type conversion, and
			returns related object IDs rather than the object itself.

			\param string $name The field name
			\return mixed The field value
		*/
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

		//! If a field was modified since the last save, return the old value.
		/*!
			\param string $name The field name
			\return mixed The previous value
		*/
		public function getPreviousFieldValue($name) {
			if ($name == 'pk')
				$name = $this->_idField;
			if (array_key_exists($name, $this->_versionValues))
				return $this->_versionValues[$name];
			return null;
		}

		//! If a model was modified since the last save, return the old field values as an array.
		/*!
			\return Array The previous field values
		*/
		public function getPreviousFieldValues() {
			return $this->_versionValues;
		}

		//! Check if a field is an "extra field"
		/*!
			Use of aggregates (min(), max(), etc) can result in a model
			containing field names and values that are not part of its
			original definition.
			\param string $name The field name
			\return TRUE if it is an extra field
		*/
		public function isExtraField($name) {
			return isset($this->_extraValues[$name]);
		}

		//! Check if a field has been changed since the last save.
		/*!
			\param string $name The field name, or null to check if <i>any</i>
					fields in this model have changed.
			\return bool TRUE if the field or model has changed
		*/
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

		//! Revert a field to the last saved value
		/*!
			\param string $name The field name, or null to revert <i>all</i>
					fields in this model to the last saved value.
		*/
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

		//! Set a field value.
		/*!
			If a field with the given property name exists, it will set it
			to a new value, internally calling Model::setFieldValue().

			When in configuration more (inside Model:configure()), it defines
			a field definition instead, internally calling Model::addField().

			\param string $name The field name
			\param mixed $value The field value
		*/
		public function __set($name, $value) {
			if ($name == 'pk')
				$name = $this->_idField;
			if ($this->_configMode)
				$this->addField($name, $value);
			else
				$this->setFieldValue($name, $value);
		}

		//! Get a field value.
		/*!
			If a field with the given property name exists, it will return the
			field's value, internally calling Model::getFieldValue().

			When in configuration more (inside Model:configure()), it returns
			the field definition instead, internally calling Model::getField().

			\param string $name The field name
			\return mixed The field value
		*/
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

		//! Validate the model's current field data.
		/*!
			If validation fails, errors can be retrieved from Model::getErrors()
			and Model::getFieldErrors().

			\param string $field The field to validate, or null for all fields
			\return bool TRUE if validation succeeds
		*/
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

		//! Validate an individual field
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

		//! Check if this model has been persisted to the data store.
		/*!
			This does not verify that the current data has been persisted, just
			that the object originally was loaded from the data store.  To check
			if the current field data has been persisted, use Model::isChanged().
			\return bool TRUE if the object exists in the data store
			\sa Model::isChanged()
		*/
		public function isPersistent() {
			return $this->_dbId != null;
		}

		//! Set the database table name associated with this model.
		/*!
			\param string $name_ The table name
		*/
		public function setTable($name_) {
			$this->_table = $name_;
		}

		//! Get the database table name associated with this model.
		/*!
			\return string The table name
		*/
		public function getTable() {
			return $this->_table;
		}

		//! Set the default sorting order for queries on this model.
		/*!
			This function takes an arbitrary number of arguments, each of
			which is a string containing an (optional) order specifier
			of <i>+</i> or <i>-</i> and a field name.

			i.e. $model->setDefaultOrder('+author.surname', '-published');
		*/
		public function setDefaultOrder() {
			$args = func_get_args();
			$this->_defaultOrder = $args;
		}

		//! Get the default sorting fields.
		/*!
			\return A list of fields to order queries by
			\sa Model::setDefaultOrder()
		*/
		public function getDefaultOrder() {
			return $this->_defaultOrder;
		}

		//! Add a data validator to this module.
		/*!
			In contrast to field validators, model validators are used
			to validate the model as a whole.  This is useful when one field's
			validation depends on the value of another field in the model.

			\param ModelValidator $validator The validator instance
			\param string $message The error message to display if validation fails
			\sa ModelValidator
			\sa ModelField::addValidator()
			\sa FieldValidator
		*/
		public function addValidator($validator, $message) {
			if (!$this->_validators) $this->_validators = array();
			$this->_validators[] = array($validator, $message);
		}

		//! Check if the last validation produced any field validation errors.
		/*!
			\return bool TRUE if any field validators failed.
		*/
		public function hasFieldErrors() {
			return $this->_fieldErrors && sizeof($this->_fieldErrors) > 0;
		}

		//! Clear any field validation errors from the last validation attempt.
		public function clearFieldErrors() {
			$this->_fieldErrors = array();
		}

		//! Add a new field validation error.
		/*!
			\param string $field The field name
			\param string $error The error message
		*/
		public function addFieldError($field, $error) {
			$this->addFieldErrors($field, array($error));
		}

		//! Add a set of field validation errors.
		/*!
			\param string $field The field name
			\param Array $error A list of error message
		*/
		public function addFieldErrors($field, $errors) {
			if (!$this->_fieldErrors) $this->_fieldErrors = array();
			if (!$this->_fieldErrors[$field]) $this->_fieldErrors[$field] = array();
			$this->_fieldErrors[$field] = array_merge($this->_fieldErrors[$field], $errors);
		}

		//! Get a list of field validation errors resulting from the last validate() attempt.
		/*!
			The error list returned is of the form:
\code
array('fieldName' => array(
		[0] => 'Error message #1',
		[1] => 'Error message #2'
	));
\endcode
			\return Array A list of field validation errors
		*/
		public function getFieldErrors() {
			return $this->_fieldErrors;
		}

		//! Check if the last validation produced any model validation errors.
		/*!
			\return bool TRUE if any model validators failed.
		*/
		public function hasErrors() {
			return $this->_errors && sizeof($this->_errors) > 0;
		}

		//! Clear any model validation errors from the last validation attempt.
		public function clearErrors() {
			$this->_errors = array();
		}

		//! Add a new model validation error.
		/*!
			\param string $error The error message
		*/
		public function addError($error) {
			if (!$this->_errors) $this->_errors = array();
			$this->_errors[] = $error;
		}

		//! Get a list of model validation errors resulting from the last validate() attempt.
		/*!
			\return Array(string) A list of model validation errors
		*/
		public function getErrors() {
			return $this->_errors;
		}

		//! Update the model's field values from a hash array of name/value pairs.
		/*!
			\param Array $params A hash array of field name/value pairs
			\param Array $rawvalues A list of field names to pass through withou
						type conversion on the values
			\param int $flags A bitmask of flags specifying information about the
					source of the params (UPDATE_* constants)
			\sa	UPDATE_FROM_FORM
			\sa	UPDATE_FORCE_BOOLEAN
			\sa	UPDATE_FROM_DB
		*/
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

		//! Persists the current model data to the data store.
		/*!
			Validates the current model data, and attempts to save it
			to the data store, including any dependent relations that have
			been changed.

			\param Array $skip A list of field names to ignore during
					cascading saves of related objects
			\exception ValidationException If data validation fails
		*/
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

		//! Delete this model from the persistent data store.
		/*!
			\exception DoesNotExistException if a matching model was not
					found in the data store.
		*/
		public function delete() {
			if ($this->_dbId)
				$this->_query->filter('pk', $this->_dbId)->delete();
			else
				throw new DoesNotExistException('No matching objects were found in the database.');
		}

		//! Reseting previous field value tracking and mark as fully persisted.
		private function resetChangeTracking() {
			$this->_version++;
			$this->_versionChanges = 0;
			$this->_versionValues = $this->_rawValues;
		}

		//! Identifies this class as an parent model for other models by mapping specific field values to different concrete subclasses.
		/*!
			Mapping concrete types allows you to have multiple model
			representations of data from a single database table.  It
			allows you to specify that when a field is a certain value,
			it should actually instantiate another model class (which
			subclasses the parent model) instead of this one.

			This is especially useful when:
			-# You have a relation field that can link to different
			model types depending on a field's value.
			-# You have a model with an EmbeddedModelField (which
			serializes a model into a single text field), where
			the EmbeddedModel is different depending on another field's
			value.

			Example:

			Parent class:
\code
class Page extends Model {

	public function configure() {

		$this->id = new IntegerField('ID', array('pk' => true));
		$this->type = new CharField('Page Type', 20, array(
			'options' => array('H' => 'Static HTML', 'F' => 'Web Form')
			));
		$this->content = new TextField('Page Body');

		$this->mapConcreteTypes('type', 
			array('F' => 'FormPage'));

	}

}
\endcode
			
			Subclass:

\code
class FormPage extends Page {
	
	public function configure() {
		
		parent::configure();
		$this->form = new OneToOneField('Form', 'Form', array('reverseJoin' => 'page'));
		
	}
	
}
\endcode
		
		In this case, if a Page object has a "type" field equal to "F", instead of
		instantiating a Page object from the query result, it instantiates the
		FormPage subclass.  This gives our code access to an additional relation
		field that is specific to the form page type.

		You can only map concrete types by a single field.  Calling this function
		with different parameters invalidates the first call.

		\param string $field The field name to map by
		\param Array $map A field value => model type hash array (see example above)
		*/
		public function mapConcreteTypes($field, $map) {
			$this->_subclassField = $field;
			$this->_subclassFilter = $map;
		}

		//! Create a URL-encoded query string based on this model's field names and values.
		/*!
			\return string A URL-encoded query string representation of this model
		*/
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

		//! From PHP Iterator interface
		public function rewind() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			reset($this->_fieldValues);
		}
		//! From PHP Iterator interface
		public function current() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return current($this->_fieldValues);
		}
		//! From PHP Iterator interface
		public function key() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return key($this->_fieldValues);
		}
		//! From PHP Iterator interface
		public function next() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return next($this->_fieldValues);
		}
		//! From PHP Iterator interface
		public function valid() {
			if (!count($this->_fieldValues))
				$this->getFieldValues();
			return current($this->_fieldValues) !== false;
		}

		//! From PHP ArrayAccess interface
		public function offsetUnset($index) {
			if ($index == 'pk')
				$index = $this->_idField;
			unset($this->_rawValues[$index]);
			unset($this->_fieldValues[$index]);
		}
		//! From PHP ArrayAccess interface
		public function offsetSet($index, $value) {
			$this->setFieldValue($index, $value);
		}
		//! From PHP ArrayAccess interface
		public function offsetGet($index) {
			return $this->getFieldValue($index);
		}
		//! From PHP ArrayAccess interface
		public function offsetExists($index) {
			if ($index == 'pk')
				$index = $this->_idField;
			return array_key_exists($index, $this->_rawValues);
		}

		//! From PHP Countable interface
		public function count() {
			return count($this->_rawValues);
		}

		//! Return a string representation of this model.
		/*!
			The default value is '[<classname>]'
		*/
		public function __toString() {
			return '['.get_class($this).']';
		}

	}

	//! A static (non-persistent) model designed for transient use.
	/*!
		\sa EmbeddedModelField
	*/
	class EmbeddedModel extends Model {

		//! Create a new embedded model belonging to the specified model object
		/*!
			\param Model $model The parent model
		*/
		public function __construct($model) {
			parent::__construct($model->_query);
		}

		//! Override of save function (does nothing)
		public function save() {
			return false;
		} 

		//! Override of delete function (does nothing)
		public function delete() {
			return false;
		}

	}
