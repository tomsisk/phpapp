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

	require_once('Validators.php');
	require_once('Exceptions.php');
	require_once('RelationSet.class.php');

	setlocale(LC_MONETARY, 'en_US');

	/**
	 * Defines a field specification for mapping to the data store.
	 *
	 * <p>Custom field types must subclass ModelField.  Model fields
	 * are configurated in Model::configure().</p>
	 *
	 * <p>Example:</p>
	 *
	 * <p><code>
	 * class Book extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->title = new CharField('Title', 100, array('required' => true));
	 *         $this->published = new DateField('Publication Date', array(
	 *             'default' => CURRENT_TIMESTAMP));
	 *         $this->format = new CharField('Format', 20, array(
	 *             'options' => array('H' => 'Hardcover', 'P' => 'Paperback', 'A' => 'Audiobook')
	 *             ));
	 *         $this->setDefaultOrder('+title');
	 *     }
	 * }
	 * </code></p>
	 *
	 * <p>All model fields require a descriptive field name (i.e. "Publication
	 * Date") and an array of field options.  There are several standard
	 * options common to all fields, which are boolean unless otherwise
	 * specified:</p>
	 *
	 * <i>required</i>: The field cannot be null<br />
	 * <i>editable</i>: The field cannot be modified, and should
	 *		be hidden from display on editing interfaces<br />
	 * <i>readonly</i>: The field cannot be modified, but should
	 *		be displayed on editing interfaces<br />
	 * <i>blankisnull</i>: A blank string should be interpreted as
	 *		equivalent to NULL when saving to the data store<br />
	 * <i>options</i>: An array of "value" => "display name" pairs
	 *		specifying the valid values for this field<br />
	 * <i>default</i>: The default field value, if none is given<br />
	 * <i>unique</i>: The field's value must be unique among all
	 *		all models<br />
	 * <i>pk</i>: The field is the model's primary key.  Implies the
	 *		following options: required=true, editable=false,
	 *		blankisnull=true, unique=true
	 *
	 * @see Model
	 * @see Model::configure()
	 */
	abstract class ModelField implements ArrayAccess {

		public $options;
		public $factory;
		public $query;
		public $field;
		public $name;
		public $type;

		public $validators;
		public $errors;

		/**
		 * Configure a new ModelField instance.
		 *
		 * @param string $name A descriptive (human-readable) name
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 */
		public function __construct($name, $options_ = null, $validators_ = null) {
			$this->name = $name;
			$this->type = get_class($this);
			if ($options_ && isset($options_['pk'])) {
				$options_ = array_merge(array(
						'required' => true,
						'editable' => false,
						'blankisnull' => true,
						'blank' => false), $options_);
			}
			$this->options = array_merge(array(
					'options' => null,
					'maxlength' => null,
					'minlength' => null,
					'default' => null,
					'required' => false,
					'readonly' => false,
					'editable' => true,
					'blankisnull' => true),
				$options_ ? $options_ : array());
			$defaultValidators = array();
			if ($options_) {
				if (isset($this->options['required']) && $this->options['required'])
					$defaultValidators[] = array(new RequiredFieldValidator(), 'This field is required.');
				if (isset($this->options['maxlength']) && $length = $this->options['maxlength'])
					$defaultValidators[] = array(new MaxLengthValidator($length), 'Field value is too long.  Maximum length is '.$length.' characters.');
				if (isset($this->options['minlength']) && $length = $this->options['minlength'])
					$defaultValidators[] = array(new MinLengthValidator($length), 'Field value is too short.  Minimum length is '.$length.' characters.');
			}
			$this->validators = array_merge($defaultValidators, (array)$validators_);
		}

		/**
		 * Add a validator to this field.
		 * @param FieldValidator $validator A field validator object
		 * @param string $message The error message to display if validation
		 *		fails
		 */
		public function addValidator($validator, $message) {
			$this->validators[] = array($validator, $message);
		}

		/**
		 * Check the given value against this field's validators.
		 *
		 * @param mixed $value The field value
		 * @param Model $model The model instance the value belongs to
		 * @return bool TRUE if the field value validates
		 */
		public function validate($value, $model) {
			$this->errors = array();
			$valid = true;
			if (isset($this->options['options'])
					&& (!isset($this->options['allowCustomValue'])
						|| !$this->options['allowCustomValue'])) {
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

		/**
		 * Return a list of errors generated from the last validation.
		 * @return Array A list of error messages, or null if none
		 */
		public function validationErrors() {
			if (count($this->errors))
				return $this->errors;
			return null;
		}

		/**
		 * Convert a value into the expected PHP data type.
		 *
		 * @param mixed $value The input value
		 * @param mixed $value The converted value
		 */
		abstract public function convertValue($value);

		/**
		 * Convert a value into the data store's expected type.
		 *
		 * @param mixed $value The input value
		 * @param mixed $value The converted value
		 */
		public function convertToDbValue($value) {
			return $this->convertValue($value);
		}

		/**
		 * Convert a value from the data store's data type into
		 * the correct PHP type.
		 *
		 * @param mixed $value The input value
		 * @param mixed $value The converted value
		 */
		public function convertFromDbValue($value) {
			return $this->convertValue($value);
		}

		/**
		 * Get the default value for this field.
		 * @return mixed The default field value
		 */
		public function getDefaultValue() {
			if (isset($this->options['default']))
				return $this->options['default'];
			return null;
		}

		/**
		 * Return a human-readable description of a field value.
		 *
		 * If the value is a related object, it will display the results
		 * of that objects __toString() method.  If this field has an
		 * "options" array, if will lookup the description useing the
		 * given value as the key.
		 *
		 * @param mixed $value The field value
		 * @return string A string representation of the value
		 */
		public function toString($value) {
			if (is_object($value) && method_exists($value, '__toString'))
				return $value->__toString();
			if (isset($this->options['options']))
				return strval($this->options['options'][$value]);
			return strval($value);
		}

		/**
		 * Serialize the field value to a string.
		 *
		 * This method differs from toString in that it preserves the
		 * value, rather than converting to a human-readable form in the
		 * case of an "options" mapping.
		 *
		 * @param mixed $value The field value
		 * @return string A string representation of the value
		 */
		public function serialize($value) {
			if (is_object($value) && method_exists($value, '__toString'))
				return $value->__toString();
			return strval($value);
		}

		/**
		 * Convert the field value to an HTML representation.
		 *
		 * Behavior is defined by the subclass.  By default, simply calls
		 * toString().
		 *
		 * @param mixed $value The field value
		 * @return string An HTML representation of the value
		 */
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

		/**
		 * Return a string description of this field.  Defaults to
		 * the class name.
		 */
		public function __toString() {
			return $this->type;
		}

	}

	/**
	 * Defines a field that links a model instance to an instance
	 * (or set of instances) of another model.
	 *
	 * Relation fields are <i>lazy-loading</i>: they only load
	 * related objects from the database on first access, so you
	 * aren't making excessive queries on objects with relation
	 * fields.  Lazy-loading is invisible to your code; you can
	 * refer to these fields as if the object is always availeble.
	 * See ManyToOneField for an example.
	 *
	 * @see ManyToOneField
	 * @see ManyToManyField
	 * @see OneToOneField
	 * @see OneToManyField
	 */
	abstract class RelationField extends ModelField {

		public $relationName;

		protected $fldType;
		protected $relModel;

		/**
		 * Configure a new RelationField.
		 *
		 * @param string $name_ A descriptive (human-readable) name
		 * @param string $relation_ The name of the Model this field is
		 *		linked to
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 */
		public function __construct($name_, $relation_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->relationName = $relation_;
		}

		/**
		 * Get the Model instance that this field links to.
		 */
		abstract function getRelation($model, $primitive = null);

		/**
		 * Set the Model instance that this field links to.
		 */
		abstract function setRelation($model, $value);

		/**
		 * Get the Model prototype that defines this field's target relation.
		 *
		 * Although this returns a Model object, it contains no useful
		 * field data.  It is only useful for determining metadata about
		 * the linked Model.
		 */
		public function getRelationModel() {
			if (!$this->relModel && $this->factory)
				$this->relModel = $this->factory->get($this->relationName)->model;
			return $this->relModel;
		}

		/**
		 * Get the field definition object (a ModelField) of the field
		 * of the related object that this field is linked to.
		 *
		 * This is usually the primary key of the related object.
		 *
		 * @return ModelField The field definition
		 */
		public function getFieldType() {
			if (!$this->fldType) {
				$model = $this->getRelationModel();
				$this->fldType = $model->_fields[$model->_idField];
			}
			return $this->fldType;
		}

		/**
		 * @see ModelField::convertToDbValue()
		 */
		public function convertToDbValue($value) {
			$fieldType = $this->getFieldType();
			if ($fieldType)
				return $fieldType->convertToDbValue($value);
			return $value;
		}

		/**
		 * @see ModelField::convertFromDbValue()
		 */
		public function convertFromDbValue($value) {
			$fieldType = $this->getFieldType();
			if ($fieldType)
				return $fieldType->convertFromDbValue($value);
			return $value;
		}

		/**
		 * @see ModelField::convertValue()
		 */
		public function convertValue($value) {
			$fieldType = $this->getFieldType();
			if ($fieldType)
				return $fieldType->convertValue($value);
			return $value;
		}

	}

	/**
	 * Defines a many-to-one relation between two models.
	 *
	 * In SQL terminology, this is a FOREIGN KEY field.
	 *
	 * Example:
	 * <code>
	 * class Book extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->author = new OneToManyField('Author', 'Author');
	 *     }
	 * }
	 * 
	 * class Author extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->name = new CharField('Name', 100);
	 *     }
	 * }
	 *
	 * // Author object is not loaded yet; we only made a single query
	 * $book = $bq->one();
	 * // Author is loaded as soon as we call the ->author field,
	 * // and we can access its fields like normal
	 * $authorname = $book->author->name;
	 * </code>
	 *
	 * Because relation fields are <i>lazy-loading</i>, you avoid
	 * excessive queries while retaining the benefit of transparent
	 * access to the related objects in your code.
	 */
	class ManyToOneField extends RelationField {
		
		/**
		 * Get the Model instance that this field links to.
		 *
		 * @param Model $model The Model instance that we want to fetch
		 *		the related object for
		 * @param mixed $primitive The primary key of the related object
		 * @see RelationField::getRelation()
		 */
		public function getRelation($model, $primitive = null) {
			$mdef = $this->getRelationModel();
			if ($primitive)
				return $mdef->getQuery()->get($primitive);
			return null;
		}

		/**
		 * Set the object that a model instance is related to, returning
		 * the primitive value of the primary key.
		 * @param Model $model The Model to set the related object for
		 * @param mixed $value The value to set, as either a Model object
		 *		or primitive primary key value
		 * @see RelationField::setRelation()
		 */
		public function setRelation($model, $value) {
			if ($value instanceof Model)
				return $value->pk;
			return $value;
		}

	}

	/**
	 * Creates a one-to-one relation between two models.
	 *
	 * <p>By default this functions essentially the same as a ManyToOneField,
	 * except it directs that the related object should also be deleted
	 * if the current model instance is deleted.</p>
	 *
	 * <p>OneToOneFields are unique by definition; you cannot link the target
	 * object to multiple source models.</p>
	 *
	 * <p>This field has several custom options:</p>
	 * <i>cascadeDelete</i>: delete the related object when the source object
	 *		is deleted.  Defaults to TRUE.<br />
	 * <i>reverseJoin</i>: specifies that the two models are linked by the
	 *		specified field (as a string) in the target model rather than the
	 *		source model. This then becomes a <i>synthetic field</i> that has
	 *		no real mapping to the database.
	 *
	 * Example:
	 * <code>
	 * class Book extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->review = new OneToManyField('Review', 'Review');
	 *     }
	 * }
	 * 
	 * class Review extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->rating = new IntegerField('Rating');
	 *     }
	 * }
	 *
	 * // Review object is not loaded yet; we only made a single query
	 * $book = $bq->one();
	 * // Review is loaded as soon as we call the ->review field,
	 * // and we can access its fields like normal
	 * $rating = $book->review->rating;
	 * </code>
	 */
	class OneToOneField extends ManyToOneField {

		/*
		 * Configure a new one-to-one field.
		 *
		 * <p>This field has several custom options:</p>
		 * <i>cascadeDelete</i>: delete the related object when the source object
		 *		is deleted.  Defaults to TRUE.<br />
		 * <i>reverseJoin</i>: specifies that the two models are linked by the
		 *		specified field (as a string) in the target model rather than the
		 *		source model. This then becomes a <i>synthetic field</i> that has
		 *		no real mapping to the database.
		 *
		 * @param string $name_ A descriptive (human-readable) name
		 * @param string $relation_ The name of the Model this field is
		 *		linked to
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 */
		public function __construct($name_, $relation_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $relation_, $options_, $validators_);
			$this->options['unique'] = true;
			$this->options['cascadeDelete'] = true;
		}

		/**
		 * @see ManyToOneField::getRelation()
		 */
		public function getRelation($model, $primitive = null) {
			if (isset($this->options['reverseJoin']) && $this->options['reverseJoin']) {
				$mdef = $this->getRelationModel();
				if ($model)
					return $mdef->getQuery()->filter(
						$this->options['reverseJoin'], $model->pk)->any();
				return null;
			} else
				return parent::getRelation($model, $primitive);
		}

		/**
		 * @see ManyToOneField::setRelation()
		 */
		public function setRelation($model, $value) {
			if (isset($this->options['reverseJoin']) && $this->options['reverseJoin']) {
				if ($value instanceof Model) {
					$value[$this->options['reverseJoin']] = $model->pk;
					return $value->pk;
				} else
					return $value;
			}
			return parent::setRelation($model, $value);
		}

	}

	/**
	 * A relation field that returns a set of related models, rather
	 * than the single model instance that ManyToOneField and OneToOneField
	 * return.
	 *
	 * <p>This field has a custom option:</p>
	 * <i>joinField</i>: The name of the field on the target model that
	 *		links back to this model.  Similar to "reverseJoin" for
	 *		OneToOneField.
	 */
	abstract class RelationSetField extends RelationField {

		protected $joinField;

		/*
		 * Configure a new one-to-one field.
		 *
		 * <p>This field has a custom option:</p>
		 * <i>joinField</i>: The name of the field on the target model that
		 *		links back to this model.  Similar to "reverseJoin" for
		 *		OneToOneField.
		 *
		 * @param string $name_ A descriptive (human-readable) name
		 * @param string $relation_ The name of the Model this field is
		 *		linked to
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 */
		public function __construct($name_, $relation_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $relation_, $options_, $validators_);
			$this->joinField = isset($this->options['joinField']) ? $this->options['joinField'] : null;
		}
		
		/**
		 * Getter to provide access to RelationSetField::$joinField that
		 * lazy-loads the target model definition on first access.
		 */
		public function __get($name) {
			if ($name == 'joinField' && !$this->joinField)
				$this->joinField = strtolower(get_class($this->query->model));
			return $this->$name;
		}

		/**
		 * @see RelationField::setRelation()
		 */
		public function setRelation($model, $value) {
			$set = $this->getRelation($model);
			$set->set($value);
		}

	}

	/**
	 * A field that returns a set of models related to this one, by
	 * a join field on the target model that points to this model's
	 * primary key.
	 *
	 * <p>This field has a custom option:</p>
	 * <i>joinField</i>: The name of the field on the target model that
	 *		links back to this model.  Defaults to the source model's
	 *		name, lower-cased.
	 *
	 * Example:
	 * <code>
	 * class Author extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->books = new OneToManyField('Books', 'Book'):
	 *     }
	 * }
	 *
	 * class Book extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->title = new CharField('Title', 100);
	 *     }
	 * }
	 * 
	 * // Book list is not loaded yet
	 * $author = $aq->one();
	 * // Book list is loaded as soon as we call the ->books field,
	 * foreach ($author->books as $book) {
	 *     echo $book->title;
	 * }
	 * </code>
	 *
	 * @see RelationSetField
	 * @see RelationSet
	 */
	class OneToManyField extends RelationSetField {

		/**
		 * Get a list of model instances that are related to this model
		 * instance.
		 *
		 * @param Model $model The model instance to fetch relations for
		 * @param mixed $primitive The key value the related objects
		 *		are linked to (usually the current model's primary key)
		 * @return RelationSet An iterable RelationSet object
		 * @see RelationSet
		 */
		public function getRelation($model, $primitive = null) {
			return new OneToManyRelationSet($model, $this);
		}

	}

	/**
	 * A field that returns a set of models related to this one, using
	 * a third intermediary Model object to create a many-to-many mapping.
	 *
	 * <p>This field has several custom options:</p>
	 * <i>joinField</i>: The name of the field on the intermediary model that
	 *		links to the source model's primary key.  Defaults to the source
	 *		model's name, lower-cased.
	 * <i>targetField</i>: The name of the field on the intermediary model that
	 *		links to the target model's primary key.  Defaults to the target
	 *		model's name, lower-cased.
	 *
	 * Example:
	 * <code>
	 * class Author extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->genres = new ManyToManyField('Genres', 'Genre', 'AuthorGenre'):
	 *     }
	 * }
	 *
	 * class AuthorGenre extends Model {
	 *     public function configure() {
	 *         $this->author = new ManyToOneField('Author');
	 *         $this->genre = new ManyToOneField('Genre');
	 *     }
	 * }
	 * class Genre extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->name = new CharField('Name', 100);
	 *     }
	 * }
	 * 
	 * // Genre list is not loaded yet
	 * $author = $aq->one();
	 * // Genre list is loaded as soon as we call the ->genres field,
	 * foreach ($author->genres as $genre) {
	 *     echo $genre->name;
	 * }
	 *
	 * // Now let's add a new genre to an author
	 * $genre = $gq->filter('name', 'Science Fiction')->one();
	 * $author->genres->add($genre);
	 * $author->save();
	 * </code>
	 *
	 * Although you need the intermediary field definition to manage the
	 * relationships, you should usually not need to access it directly,
	 * unless you need to store additional information about the relation.
	 * ModelQuery adds and removes records of that model as needed to
	 * maintain the relationship links.
	 *
	 * @see RelationSetField
	 * @see RelationSet
	 */
	class ManyToManyField extends RelationSetField {

		public $joinModel;
		public $targetField;

		protected $joinModelObj;

		/*
		 * Configure a new many-to-many field.
		 *
		 * <p>This field has several custom options:</p>
		 * <i>joinField</i>: The name of the field on the intermediary model that
		 *		links to the source model's primary key.  Defaults to the source
		 *		model's name, lower-cased.
		 * <i>targetField</i>: The name of the field on the intermediary model that
		 *		links to the target model's primary key.  Defaults to the target
		 *		model's name, lower-cased.
		 *
		 * @param string $name_ A descriptive (human-readable) name
		 * @param string $relation_ The name of the Model this field is
		 *		linked to
		 * @param string $joinModel_ The name of the intermediary model that
		 *		links the source and target models
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 */
		public function __construct($name_, $relation_, $joinModel_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $relation_, $options_, $validators_);
			$this->joinModel = $joinModel_;
			$this->targetField = isset($this->options['targetField'])
				? $this->options['targetField']
				: strtolower($relation_);
		}

		/**
		 * Get the Model prototype object for the intermediary joining model.
		 */
		public function getJoinModel() {
			if (!$this->joinModelObj)
				$this->joinModelObj = $this->factory->get($this->joinModel)->model;
			return $this->joinModelObj;
		}

		/**
		 * Get a list of model instances that are related to this model
		 * instance.
		 *
		 * @param Model $model The model instance to fetch relations for
		 * @param mixed $primitive The key value the related objects
		 *		are linked to (usually the current model's primary key)
		 * @return RelationSet An iterable RelationSet object
		 * @see RelationSet
		 */
		public function getRelation($model, $primitive = null) {
			return new ManyToManyRelationSet($model, $this);
		}

	}

	/**
	 * Field contains an integer.
	 */
	class IntegerField extends ModelField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->validators[] = array(new NumericValidator(), 'Invalid number format.');
		}

		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			return intval($value);
		}

		public function convertToDbValue($value) {
			// MySQL returns all fields as strings in the driver, so
			// we need to return strings for version comparisons
			return strval($this->convertValue($value));
		}

	}

	/**
	 * Field contains an ordered integer.
	 *
	 * OrderedField fields get special handling, because ModelQuery needs
	 * to manage the ordering to ensure there are no gaps or duplicates
	 * in the data store.
	 *
	 * Example:
	 * <code>
	 * class Portfolio extends Model {
	 *     public function configure() {
	 *         $this->id = new IntegerField('ID', array('pk' => true));
	 *         $this->user = new ManyToOneField('User', 'User');
	 *         $this->sort_order = new OrderedField('Sort Order', array('user'));
	 *         $this->name = new CharField('ID', 100);
	 *     }
	 * }
	 * 
	 * // Start with a user
	 * $user1 = $uq->get(1);
	 *
	 * // Create a few portofolios.  As we add them, ModelQuery assigns
	 * // the sort_order field automatically.
	 * $up1 = $pq->create(array('user' => $user1, 'title' = 'U1P1'));
	 * $up1->save();
	 * // $up1->sort_order is now "1"
	 * $up2 = $pq->create(array('user' => $user1, 'title' = 'U1P2'));
	 * $up2->save();
	 * // $up2->sort_order is now "2"
	 * $up3 = $pq->create(array('user' => $user1, 'title' = 'U1P3'));
	 * $up3->save();
	 * // $up3->sort_order is now "3"
	 *
	 * // What happens if we rearrange?
	 * $up2->sort_order = 3;
	 * $up2->save();
	 * // $up2->sort_order is now "3"
	 * // $up3->sort_order has been automatically updated to be "2"
	 *
	 * // ModelQuery fills in the gaps on delete
	 * $up3->delete();
	 * // $up2->sort_order is now "2" again
	 *
	 * // That groupBy field?  That tells ModelQuery how to determine
	 * // unique ordering groups
	 * // Let's look at a different user:
	 * $user2 = $uq->get(2);
	 * $up4 = $pq->create(array('user' => $user2, 'title' = 'U2P1'));
	 * $up4->save();
	 * // $up4->sort_order is now "1"
	 * // $up1->sort_order is also still "1", because they belong to
	 * // different users (and "user" is the "groupBy" parameter in the
	 * // constructor)
	 * </code>
	 */
	class OrderedField extends IntegerField {

		public $groupBy;

		/**
		 * Configure a new OrderedField.
		 *
		 * @param string $name_ A descriptive (human-readable) name
		 * @param Array $groupBy_ A list of fields to group objects by when 
		 *		determining ordering values
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 */
		public function __construct($name_, $groupBy_ = null, $options_ = null, $validators_ = null) {
			// Skip required validator, since ModelQuery automatically
			// sets this value on save (after validation)
			if (isset($options_['required']))
				unset($options_['required']);
			parent::__construct($name_, $options_, $validators_);
			$this->options['editable'] = false;
			$this->groupBy = $groupBy_;
		}

	}

	/**
	 * Field contains a floating point decimal.
	 */
	class FloatField extends ModelField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			$this->validators[] = array(new NumericValidator(), 'Invalid number format.');
		}

		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			return floatval($value);
		}

		public function convertToDbValue($value) {
			return strval($this->convertValue($value));
		}

	}

	/**
	 * Field contains a currency amount.
	 */
	class CurrencyField extends FloatField {

		/**
		 * @see ModelField::__construct()
		 */
		public function convertValue($value) {
			if (is_string($value))
				$value = str_replace(array(',', '$'), '', $value);
			return parent::convertValue($value);
		}

		public function convertToDbValue($value) {
			if (is_string($value))
				$value = str_replace(array(',', '$'), '', $value);
			// MySQL returns all fields as strings in the driver, so
			// we need to return strings for version comparisons
			return parent::convertToDbValue($value);
		}

		public function toString($value) {
			return money_format('$%n', $value);
		}

	}

	/**
	 * Field contains textual data.
	 *
	 * >TextField has several custom option:</p>
	 * <i>blank</i>: If false, blank values are not allowed.<br />
	 * <i>case</i>: Specifies that the input text should be transformed
	 *		before saving to the data store.  Possible values: "upper"
	 *		(convert to uppercase), "lower" (convert to lowercase),
	 *		"capitalize" (capitalize the first letter), "title"
	 *		(capitalize the first letter of every word)
	 */
	class TextField extends ModelField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			if (isset($this->options['blank']) && $this->options['blank'] === false)
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

	/**
	 * Field contains HTML-formatted data
	 */
	class HTMLField extends TextField {

		/**
		 * @see ModelField::__construct()
		 */
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

	/**
	 * Field contains limited-length textual data.
	 */
	class CharField extends TextField {

		public $length;

		/**
		 * Configure a new CharField instance.
		 *
		 * @param string $name A descriptive (human-readable) name
		 * @param int $length_ The maximum string length
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 * @see ModelField::__construct()
		 */
		public function __construct($name_, $length_ = 0, $options_ = null, $validators_ = null) {
			$this->length = $length_;
			parent::__construct($name_, $options_, $validators_);
			if ($length_)
				$this->validators[] = array(new MaxLengthValidator($length_), 'Field value is too long.  Maximum length is '.$length_.'.');
		}

	}

	/**
	 * Field contains string data limited to specific values.
	 */
	class EnumField extends TextField {

		/**
		 * Configure a new EnumField instance.
		 *
		 * @param string $name A descripticontainingve (human-readable) name
		 * @param Array $values_ An array of valid values for this field.
		 *		The array must be a map of 'value' => 'description'.
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 * @see ModelField::__construct()
		 */
		public function __construct($name_, $values_, $options_ = null, $validators_ = null) {
			parent::__construct($name_, $options_, $validators_);
			if ($values_ == null || !count($values_))
				throw new Exception('EnumField requires at least one valid value.');
			$this->options['options'] = $values_;
		}

	}

	/**
	 * Field contains an MD5-encoded password.
	 *
	 * If the value passed in is exactly 32 characters long and consists
	 * of only alphanumeric characters, it will assume it is already an
	 * MD5 encoded string.  Otherwise, if will perform an MD5 hash on the
	 * value before saving to the dataabse.
	 */
	class PasswordField extends CharField {

		/**
		 * @see ModelField::__construct()
		 */
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

	/**
	 * Field contains sensitive data and should be masked before display
	 * in the browser.
	 *
	 * This field is useful for displaying items like credit card numbers.
	 */
	class MaskedField extends CharField {

		public function getMasked($value) {
			if ($value) {
				$masked = '';
				$maskLength = strlen($value) > 8 ? strlen($value) - 4 : strlen($value);
				for ($i = 0; $i < $maskLength; $i++)
					$masked .= '*';
				$masked .= substr($value, $maskLength);
				return $masked;
			}
			return $value;
		}

	}

	/**
	 * Field contains an HTML color code, such as "#EEEEEE".
	 */
	class ColorField extends CharField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 7, $options_, $validators_);
		}

		public function toHTML($value) {
			// Todo: display color swatch (background?)
			return $this->toString($value);
		}

	}

	/**
	 * Field contains a file path.
	 */
	class FileField extends CharField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 200, $options_, $validators_);
		}

	}

	/**
	 * Field contains an image URL.
	 */
	class ImageField extends CharField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 200, $options_, $validators_);
		}

		/**
		 * Displays an image tag.
		 */
		public function toHTML($value) {
			return '<img src="'.$value.'" />';
		}

	}

	/**
	 * Field contains an email address.
	 */
	class EmailField extends CharField {

		/**
		 * @see ModelField::__construct()
		 */
		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, 150, $options_, $validators_);
			$this->validators[] = array(new EmailValidator(), 'Please enter a valid e-mail address.');
		}

		/**
		 * Displays a mailto: link.
		 */
		public function toHTML($value) {
			return '<a href="mailto:'.$value.'">'.$value.'</a>';
		}

	}

	/**
	 * Field contains a boolean value.
	 */
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
			if (isset($this->options['options']))
				return strval($this->options['options'][$value]);
			return $value ? 'Yes' : 'No';
		}

		public function serialize($value) {
			return $value ? '1' : '0';
		}

	}

	/**
	 * Field contains date and time data.
	 */
	class DateTimeField extends ModelField {

		public function __construct($name, $options_ = null, $validators_ = null) {
			parent::__construct($name, $options_, $validators_);
			$this->validators[] = array(new DateValidator(), 'Invalid date/time format');
		}

		public function convertValue($value) {
			if ($value === '' || $value === null)
				return null;
			if (is_numeric($value))
				return intval($value);
			return strtotime($value);
		}

		public function convertToDbValue($value) {
			if (isset($this->options['autoupdate']) && $this->options['autoupdate'])
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
			if (isset($this->options['default'])) {
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

	/**
	 * Field contains date-only data.
	 */
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

	/**
	 * Field contains date and time data, in milliseconds precision instead
	 * of seconds.
	 */
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

		public function convertToDbValue($value) {
			// MySQL returns all fields as strings in the driver, so
			// we need to return strings for version comparisons
			return parent::convertToDbValue($this->convertValue($value));
		}

		public function toString($value) {
			return strftime('%Y-%m-%d', $value/1000);
		}

	}

	/**
	 * Field is actually an embedded model that is serialized
	 * to a string field in the data store.
	 *
	 * @see EmbeddedModel
	 */
	class EmbeddedModelField extends ModelField {

		private $embedded;

		/**
		 * Configure a new EmbeddedModelField instance.
		 *
		 * @param string $name_ A descriptive (human-readable) name
		 * @param string $model_ The embedded model prototype
		 * @param Array $options_ An array of options
		 * @param Array $validators_ An array of FieldValidator objects
		 * @see EmbeddedModel
		 */
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
					$field = explode('=', $p);
					$n = $field[0];
					$v = isset($field[1]) ? $field[1] : null;
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

	/**
	 * Field contains an array of values, serialized to a string field
	 * in the data store as a comma-separated list.
	 */
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

		public function serialize($value) {
			return rawurldecode($this->convertToDbValue($value));
		}

	}
