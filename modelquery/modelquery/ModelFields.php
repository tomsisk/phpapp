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

	require_once('Validators.php');
	require_once('Exceptions.php');
	require_once('RelationSet.class.php');

	setlocale(LC_MONETARY, 'en_US');

	abstract class ModelField implements ArrayAccess {

		public $options;
		public $factory;
		public $query;
		public $field;
		public $name;
		public $type;

		protected $validators;
		protected $errors;

		public function __construct($name, $options_ = null, $validators_ = null) {
			$this->name = $name;
			$this->type = get_class($this);
			if ($options_ && array_key_exists('pk', $options_)) {
				$options_ = array_merge(array(
						'required' => true,
						'editable' => false,
						'blankisnull' => true,
						'blank' => false), $options_);
			}
			$this->options = array_merge(array('editable' => true, 'blankisnull' => true), (array)$options_);
			$defaultValidators = array();
			if ($options_) {
				if ($this->options['required'])
					$defaultValidators[] = array(new RequiredFieldValidator(), 'This field is required.');
				if ($length = $this->options['maxlength'])
					$defaultValidators[] = array(new MaxLengthValidator($length), 'Field value is too long.  Maximum length is '.$length.' characters.');
				if ($length = $this->options['minlength'])
					$defaultValidators[] = array(new MinLengthValidator($length), 'Field value is too short.  Minimum length is '.$length.' characters.');
			}
			$this->validators = array_merge($defaultValidators, (array)$validators_);
		}

		public function addValidator($validator, $message) {
			$this->validators[] = array($validator, $message);
		}

		public function validate($value, $model) {
			$this->errors = array();
			$valid = true;
			if (array_key_exists('options', $this->options)) {
				$validvals = array_keys($this->options['options']);
				$valueList = is_array($value) ? $value : array($value);
				foreach ($valueList as $v) {
					if ($v && !in_array($v, $validvals)) {
						$this->errors[] = 'Invalid value.';
						$valid = false;
						break;
					}
				}
			}
			foreach ($this->validators as $validator) {
				if (!$validator[0]->validate($value, $model)) {
					$this->errors[] = $validator[1];
					$valid = false;
				}
			}
			return $valid;
		}

		public function validationErrors() {
			if (count($this->errors))
				return $this->errors;
			return null;
		}

		// Tries to converts variable input into the correct data type
		abstract public function convertValue($value);

		// Converts from PHP type (result of convertValue()) to database type
		// In many cases these will just be convertValue()
		public function convertToDbValue($value) {
			return $this->convertValue($value);
		}

		// Converts from database type to PHP type
		// In many cases this will just be convertValue()
		public function convertFromDbValue($value) {
			return $this->convertValue($value);
		}

		// Return the default value for this field
		public function getDefaultValue() {
			if (isset($this->options['default']))
				return $this->options['default'];
			return null;
		}

		public function toString($value) {
			if (is_object($value) && method_exists($value, '__toString'))
				return $value->__toString();
			if (array_key_exists('options', $this->options))
				return strval($this->options['options'][$value]);
			return strval($value);
		}

		public function serialize($value) {
			if (is_object($value) && method_exists($value, '__toString'))
				return $value->__toString();
			return strval($value);
		}

		public function toHTML($value) {
			return $this->toString($value);
		}

		public function offsetSet($index, $value) {
			return false;
		}
		public function offsetUnset($index) {
			return false;
		}
		public function offsetGet($index) {
			if (property_exists($this, $index))
				return $this->$index;
			$method = 'get'.ucfirst($index);
			if (method_exists($this, $method))
				return call_user_func(array($this, $method));
			return null;
		}
		public function offsetExists($index) {
			return method_exists($this, 'get'.ucfirst($index));
		}

		public function __toString() {
			return $this->type;
		}

	}

	abstract class RelationField extends ModelField {

		public $relationName;

		protected $fldType;
		protected $relModel;

		public function __construct($name_, $relation_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->relationName = $relation_;
		}

		abstract function getRelation($model, $primitive = null);
		abstract function setRelation($model, $value);

		public function getRelationModel() {
			if (!$this->relModel && $this->factory)
				$this->relModel = $this->factory->get($this->relationName)->model;
			return $this->relModel;
		}

		public function getFieldType() {
			if (!$this->fldType) {
				$model = $this->getRelationModel();
				$this->fldType = $model->_fields[$model->_idField];
			}
			return $this->fldType;
		}

		public function convertToDbValue($value) {
			$fieldType = $this->getFieldType();
			if ($fieldType)
				return $fieldType->convertToDbValue($value);
			return $value;
		}

		public function convertFromDbValue($value) {
			$fieldType = $this->getFieldType();
			if ($fieldType)
				return $fieldType->convertFromDbValue($value);
			return $value;
		}

		public function convertValue($value) {
			$fieldType = $this->getFieldType();
			if ($fieldType)
				return $fieldType->convertValue($value);
			return $value;
		}

	}

	class ManyToOneField extends RelationField {
		
		public function getRelation($model, $primitive = null) {
			$mdef = $this->getRelationModel();
			if ($primitive)
				return $mdef->getQuery()->get($primitive);
			return null;
		}

		public function setRelation($model, $value) {
			if ($value instanceof Model) {
				//if (!$value->pk)
					//$value->save();
				return $value->pk;
			}
			return $value;
		}

	}

	class OneToOneField extends ManyToOneField {

		// Same as many-to-one except for field uniqueness
		public function __construct($name_, $relation_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $relation_, $options_, $validators_);
			$this->options['unique'] = true;
			$this->options['cascadeDelete'] = true;
		}

		public function getRelation($model, $primitive = null) {
			if ($this->options['reverseJoin']) {
				$mdef = $this->getRelationModel();
				if ($model)
					return $mdef->getQuery()->filter(
						$this->options['reverseJoin'], $model->pk)->any();
				return null;
			} else
				return parent::getRelation($model, $primitive);
		}

		public function setRelation($model, $value) {
			if ($this->options['reverseJoin']) {
				if ($value instanceof Model) {
					$value[$this->options['reverseJoin']] = $model->pk;
					return $value->pk;
				} else
					return $value;
			}
			return parent::setRelation($model, $value);
		}

	}

	abstract class RelationSetField extends RelationField {

		protected $joinField;

		public function __construct($name_, $relation_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $relation_, $options_, $validators_);
			$this->joinField = $this->options['joinField'];
		}
		
		public function __get($name) {
			if ($name == 'joinField' && !$this->joinField)
				$this->joinField = strtolower(get_class($this->query->model));
			return $this->$name;
		}

		public function setRelation($model, $value) {
			$set = $this->getRelation($model);
			$set->set($value);
		}

	}

	class OneToManyField extends RelationSetField {

		public function getRelation($model, $primitive = null) {
			return new OneToManyRelationSet($model, $this);
		}

	}

	class ManyToManyField extends RelationSetField {

		public $joinModel;
		public $targetField;

		protected $joinModelObj;

		public function __construct($name_, $relation_, $joinModel_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $relation_, $options_, $validators_);
			$this->joinModel = $joinModel_;
			$this->targetField = $this->options['targetField']
				? $this->options['targetField']
				: strtolower($relation_);
		}

		public function getJoinModel() {
			if (!$this->joinModelObj)
				$this->joinModelObj = $this->factory->get($this->joinModel)->model;
			return $this->joinModelObj;
		}

		public function getRelation($model, $primitive = null) {
			return new ManyToManyRelationSet($model, $this);
		}

	}

	class IntegerField extends ModelField {

		public function __construct($name_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->validators[] = array(new NumericValidator(), 'Invalid number format.');
		}

		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			return intval($value);
		}

	}

	class OrderedField extends IntegerField {

		public $groupBy;

		public function __construct($name_, $groupBy_ = null, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options, $validators_);
			$this->options['editable'] = false;
			$this->groupBy = $groupBy_;
		}

	}

	class FloatField extends ModelField {

		public function __construct($name_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->validators[] = array(new NumericValidator(), 'Invalid number format.');
		}

		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			return floatval($value);
		}

	}

	class CurrencyField extends FloatField {
		public function convertValue($value) {
			if (is_string($value))
				$value = str_replace(array(',', '$'), '', $value);
			return parent::convertValue($value);
		}

		public function toString($value) {
			return money_format('$%n', $value);
		}
	}

	class TextField extends ModelField {

		public function __construct($name_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			if ($this->options['blank'] === false)
				$this->validators[] = array(new RequiredStringValidator(), 'Field cannot be blank.');
		}

		public function convertFromDbValue($value) {
			if ($value !== null
					&& isset($this->options['blankisnull'])
					&& $this->options['blankisnull']
					&& strlen($value) == 0) {
				return null;
			}
			return $value;
		}

		public function convertValue($value) {
			if ($value !== null && !is_string($value))
				$value = strval($value);
			if (isset($this->options['blankisnull'])
					&& $this->options['blankisnull']
					&& strlen($value) == 0)
				$value = null;
			if (isset($this->options['case'])) {
				if ($this->options['case'] == 'upper')
					$value = strtoupper($value);
				elseif ($this->options['case'] == 'lower')
					$value = strtolower($value);
				elseif ($this->options['case'] == 'capitalize')
					$value = ucfirst($value);
				elseif ($this->options['case'] == 'title')
					$value = ucwords($value);
			}
			return $value;
		}

		public function toHTML($value) {
			return '<span style="white-space: pre-wrap;">'.$this->toString($value).'</span>';
		}

	}

	class HTMLField extends TextField {

		public function convertValue($value) {
			if ($value !== null && !is_string($value))
				$value = strval($value);
			if (isset($this->options['blankisnull'])
					&& $this->options['blankisnull']
					&& preg_replace('/<br *\/*>/i', '', trim($value)) === '')
				return null;
			return $value;
		}

	}

	class CharField extends TextField {

		public $length;

		public function __construct($name_, $length_ = 0, $options_ = null, $validators_ = null) {
			$this->length = $length_;
			parent::__construct($name_, $options_, $validators_);
			if ($length_)
				$this->validators[] = array(new MaxLengthValidator($length_), 'Field value is too long.  Maximum length is '.$length_.'.');
		}

	}

	class PasswordField extends CharField {

		public function convertToDbValue($value) {
			$converted = $this->convertValue($value);
			// Only encode non-hashed strings (<32 chars). Hackish.
			if ($converted && !(strlen($converted) == 32 && preg_match('/^[A-Za-z0-9]*$/', $value)))
				$converted = md5($converted);
			return $converted;
		}

		public function toString($value) {
			return '********';
		}

	}

	class ColorField extends CharField {

		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 7, $options_, $validators_);
		}

		public function toHTML($value) {
			// Todo: display color swatch (background?)
			return $this->toString($value);
		}

	}

	class ImageField extends CharField {

		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 200, $options_, $validators_);
		}

		public function toHTML($value) {
			return '<img src="'.$value.'" />';
		}

	}

	class EmailField extends CharField {

		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 150, $options_, $validators_);
			$this->validators[] = array(new EmailValidator(), 'Please enter a valid e-mail address.');
		}

		public function toHTML($value) {
			return '<a href="mailto:'.$value.'">'.$value.'</a>';
		}

	}

	class BooleanField extends ModelField {
		
		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			return ($value && !in_array(strtolower($value), array('0', 'false', 'f', 'no', 'n')));
		}

		public function convertToDbValue($value) {
			$value = $this->convertValue($value);
			if ($value === null)
				return null;
			return $value ? $this->factory->getConnection()->true : $this->factory->getConnection()->false;
		}

		public function convertFromDbValue($value) {
			if ($value === null)
				return null;
			return $value === $this->factory->getConnection()->true ? true : false;
		}

		public function toString($value) {
			if (array_key_exists('options', $this->options))
				return strval($this->options['options'][$value]);
			return $value ? 'Yes' : 'No';
		}

		public function serialize($value) {
			return $value ? '1' : '0';
		}

	}

	class DateTimeField extends ModelField {

		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, $options_, $validators_);
			$this->validators[] = array(new DateValidator(), 'Invalid date/time format');
		}

		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			if ($value !== null && is_string($value) && ($t = strtotime($value)))
				return $t;
			return intval($value);
		}

		public function convertToDbValue($value) {
			if ($this->options['autoupdate'])
				$value = time();
			else
				$value = $this->convertValue($value);
			if ($value !== null)
				return $this->factory->getConnection()->BindTimestamp($value);
			return $value;
		}

		public function convertFromDbValue($value) {
			if ($value !== null)
				return $this->factory->getConnection()->UnixTimeStamp($value);
			return $value;
		}

		public function getDefaultValue() {
			if (array_key_exists('default', $this->options)) {
				if ($this->options['default'] == CURRENT_TIMESTAMP)
					return time();
				else
					return $this->options['default'];
			}
			return null;
		}

		public function toString($value) {
			return strftime('%Y-%m-%d %H:%M:%S', $value);
		}

	}

	class DateField extends DateTimeField {

		public function convertToDbValue($value) {
			$value = $this->convertValue($value);
			if ($value !== null)
				return $this->factory->getConnection()->BindDate($value);
			return $value;
		}

		public function toString($value) {
			return strftime('%Y-%m-%d', $value);
		}

	}

	class LongDateTimeField extends FloatField {

		public function convertValue($value) {
			if (is_numeric($value))
				$value = floatval($value);
			elseif (is_string($value))
				$value = strtotime($value);
			if ($value < 2147483647)
				$value *= 1000;
			return $value;
		}

		public function toString($value) {
			return strftime('%Y-%m-%d', $value/1000);
		}

	}

	class EmbeddedModelField extends ModelField {

		private $embedded;

		public function __construct($name_, $model_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->embedded = $model_;
		}

		public function validate($value, $model) {

			$parentValid = parent::validate($value, $model);

			$selfValid = $value->validate();

			if (!$selfValid) {
				if ($value->hasFieldErrors()) {
					$fe = $value->getFieldErrors();
					$value->clearFieldErrors();
					foreach ($fe as $f => $e) {
						$value->addFieldErrors($this->field.'.'.$f, $e);
						$model->addFieldErrors($this->field.'.'.$f, $e);
					}
				}
				if ($value->hasErrors()) {
					foreach ($value->getErrors() as $error)
						$model->addError($error);
				}
			}

			return $parentValid && $selfValid;

		}

		public function convertValue($value) {

			if (is_string($value))
				return $this->convertFromDbValue($value);
			
			$model = clone $this->embedded;
			$model->updateModel($value, null, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);

			return $model;

		}

		public function convertToDbValue($value) {

			if (is_string($value))
				return $value;

			if (is_array($value))
				$value = $this->convertValue($value);

			return $value->toQueryParams();

		}

		public function convertFromDbValue($value) {

			$params = array();
			$pairs = explode('&', $value);

			$model = clone $this->embedded;

			if ($pairs && count($pairs) > 0) {
				foreach ($pairs as $p) {
					list($n, $v) = explode('=', $p);
					if (substr($n, -2) == '[]')
						$n = substr($n, 0, -2);
					$params[$n] = rawurldecode($v);
				}

				$model->updateModel($params, null, UPDATE_FROM_DB);
			}

			return $model;

		}

		public function getDefaultValue() {

			$model = clone $this->embedded;

			// Force all fields to default
			$model->updateModel(array());

			return $model;

		}

	}

	class ArrayField extends ModelField {

		public function convertValue($value) {

			if (!$value)
				return null;

			if (is_array($value))
				return $value;

			return $this->convertFromDbValue($value);

		}

		public function convertFromDbValue($value) {

			if (!$value)
				return null;

			$vals = explode(',', $value);
			foreach ($vals as $i => $v)
				$vals[$i] = rawurldecode($v);

			return $vals;

		}

		public function convertToDbValue($value) {

			if (!$value)
				return null;

			if (is_string($value))
				return $value;

			$dbvals = array();
			foreach ($value as $v)
				$dbvals[] = rawurlencode($v);

			return implode(',', $dbvals);

		}

	}
