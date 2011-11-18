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
	 * A validator that validates the entire model as a whole, rather
	 * than limited to individual fields.
	 */
	interface ModelValidator {

		/** 
		 * Validate the specified model.
		 * @param Model $model The model to validate
		 */
		public function validate($model);

	}

	/** 
	 * Requires that if one field has a non-empty value, another field must
	 * also have a non-empty value.
	 */
	class ConditionalRequiredStringValidator implements ModelValidator {

		private $validated;
		private $condition;

		/**
		 * Create a new validator.
		 * @param string $validated The dependent field to validate
		 * @param string $condition The field that determines if validation
		 *		is necessary
		 */
		public function __construct($validated, $condition) {
			$this->validated = $validated;
			$this->condition = $condition;
		}

		public function validate($object) {
			if (!$object[$this->condition]) {
				$value = $object[$this->validated];
				if ($value) {
					// Also check for blank HTML strings
					$value = trim($value);
					$value = preg_replace('/<br *\/*>/i', '', $value);
				}
				return $value && $value !== '';
			}
			return true;
		}

	}

	/**
	 * Requires that two fields have matching values.
	 */
	class FieldsMatchValidator implements ModelValidator {

		private $field1;
		private $field2;

		/**
		 * Create a new validator.
		 * @param string $field1 The first field to check
		 * @param string $field2 The second field to check
		 */
		public function __construct($field1, $field2) {
			$this->field1 = $field1;
			$this->field2 = $field2;
		}

		public function validate($object) {
			return (array_key_exists($this->field1, $object)
					&& array_key_exists($this->field1, $object)
					&& ($object[$this->field1] == $object[$this->field2]));
		}

	}

	/**
	 * Require that one or more of the listed fields have non-empty
	 * values.
	 */
	class OneOrMoreValidator implements ModelValidator {

		private $fields;

		/**
		 * Create a new validator.
		 * @param Array $fields A list of fields to check
		 */
		public function __construct($fields) {
			$this->fields = $fields;
		}

		public function validate($object) {
			foreach ($this->fields as $field) {
				if (isset($object[$field]) && $object[$field] !== FALSE)
					return true;
			}
		}

	}

	/**
	 * Require that a set of fields be unique within all model instances.
	 */
	class UniqueFieldsValidator implements ModelValidator {

		private $fields;

		/**
		 * Create a new validator.
		 * @param Array $fields The combination of fields that must be unique
		 */
		public function __construct($fields) {
			$this->fields = $fields;
		}

		public function validate($object) {
			$query = $object->getQuery();
			if ($object->pk)
				$query = $query->exclude('pk', $object->pk);
			foreach ($this->fields as $field) {
				if ($object->$field === null)
					$query = $query->filter($field.':isnull', true);
				else
					$query = $query->filter($field, $object->$field);
			}
			$usedct = $query->count();
			return $usedct == 0;
		}

	}

	/** 
	 * A validator that checks individual field values.
	 */
	interface FieldValidator {

		/**
		 * Check the specified field value in the context of the
		 * given model.
		 * @param mixed $value The value to check
		 * @param Model $model The field's parent model 
		 */
		public function validate($value, $model);
	}

	/** 
	 * Required that a field have a non-null value.
	 */
	class RequiredFieldValidator implements FieldValidator{

		public function validate($value, $model) {
			if (is_array($value))
				return count($value) > 0;
			return $value !== null;
		}

	}

	/** 
	 * Required that a text field have a minimum length.
	 */
	class MinLengthValidator implements FieldValidator {

		private $length;

		/** 
		 * Create a new validator.
		 * @param int $length_ The minimum character length.
		 */
		public function __construct($length_) {
			$this->length = $length_;
		}

		public function validate($value, $model) {
			return $value ? strlen($value) >= $this->length : true;
		}

	}

	/** 
	 * Required that a text field be only alphnumeric characters
	 */
	class AlphaNumericValidator implements FieldValidator {

		public function validate($value, $model) {
			return ctype_alnum($value);
		}

	}

	/** 
	 * Required that a text field have a maximum length.
	 */
	class MaxLengthValidator implements FieldValidator {

		private $length;

		/** 
		 * Create a new validator.
		 * @param int $length_ The maximum character length.
		 */
		public function __construct($length_) {
			$this->length = $length_;
		}

		public function validate($value, $model) {
			return $value ? strlen($value) <= $this->length : true;
		}

	}

	/**
	 * Require that a field value be unique among all model instances.
	 */
	class UniqueFieldValidator implements FieldValidator {

		private $field;

		/**
		 * Create a new validator.
		 * @param string $field_ The unique field name
		 */
		public function __construct($field_) {
			$this->field = $field_;
		}

		public function validate($value, $model) {
			$query = $model->getQuery();
			// Exclude current item if committed to database
			if ($model->isPersistent())
				$query = $query->exclude('pk', $model->pk);
			$usedct = $query->filter($this->field, $value)->count();
			return $usedct == 0;
		}

	}

	/**
	 * Require that a string have a non-empty value.  This differs
	 * from RequiredFieldValidator in that while RequiredFieldValidator
	 * will accept an empty string as a non-null value, this validator
	 * will not.
	 */
	class RequiredStringValidator implements FieldValidator {

		public function validate($value, $model) {
			if ($value) {
				// Also check for blank HTML strings
				$value = trim($value);
				$value = preg_replace('/<br *\/*>/i', '', $value);
			}
			return $value !== null && $value !== '';
		}

	}

	/**
	 * Require that a field value be numeric.
	 */
	class NumericValidator implements FieldValidator {

		public function validate($value, $model) {
			if ($value)
				return is_numeric($value);
			return true;
		}

	}

	/** 
	 * Imposes certain character requirements on password fields.
	 */
	class PasswordValidator implements FieldValidator {

		private $numrequired;
		private $symrequired;
		private $charpattern;

		/** 
		 * Create a new validator.
		 * @param bool $numrequired Whether a numeric character is required
		 *		in the password
		 * @param bool $symrequired Whether a non-alphanumeric character is
		 *		required in the password
		 * @param string $charpattern A regular expression to test the password
		 *		against (advanced usage)
		 */
		public function __construct($numrequired = false, $symrequired = false, $charpattern = null) {
			$this->numrequired = $numrequired;
			$this->symrequired = $symrequired;
			$this->charpattern = $charpattern;
		}

		public function validate($value, $model) {
			if (!$value)
				return false;

			// Only validate non-hashed strings (<32 chars). Hackish.
			if ($value && strlen($value) == 32 && preg_match('/^[A-Za-z0-9]*$/', $value))
				return true;

			$hassym = false;
			$hasnum = false;

			if ($this->charpattern && !preg_match('/^'.$this->charpattern.'*$/', $value))
				return false;

			for ($i = 0; $i < strlen($value); ++$i) {
				$o = ord($value{$i});
				if ($o >= 48 && $o <= 57)
					$hasnum = true;
				elseif (($o >= 32 && $o <= 47)
						|| ($o >= 58 && $o <= 64)
						|| ($o >= 91 && $o <= 96)
						|| ($o >= 123 && $o <= 126))
					$hassym = true;
			}

			if (($this->numrequired && !$hasnum) || ($this->symrequired && !$hassym))
				return false;

			return true;
		}
	}

	/** 
	 * Require that an array field have a least one item in it.
	 */
	class NonEmptyArrayValidator implements FieldValidator {
		public function validate($value, $model) {
			return is_array($value) && count($value) > 0;
		}
	}

	/**
	 * Required a valid date.
	 */
	class DateValidator implements FieldValidator {
		public function validate($value, $model) {
			return $value !== false;
		}
	}

	/**
	 * Validate by regular expression pattern
	 */
	class PatternValidator implements FieldValidator {

		private $pattern;

		public function __construct($pattern = null) {
			$this->pattern = $pattern;
		}

		public function validate($value, $model) {
			return !$this->pattern || preg_match($this->pattern, $value) > 0;
		}

	}

	/**
	 * Require a valid email address format.
	 */
	class EmailValidator implements FieldValidator {

		# Whether to contact the specified server to try and verify the address
		# Currently disabled
		private $contactServer = false;

		public function __construct($contactServer_ = false) {
			$this->contactServer = $contactServer_;
		}

		public function validate($email, $model) {
			// Not necessarily a required field, so only validate if there's text entered
			if (!$email)
				return true;

			// First, we check that there's one @ symbol, and that the lengths are right
			if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email)) {
				// Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
				return false;
			}
			// Split it into sections to make life easier
			$email_array = explode("@", $email);
			$local_array = explode(".", $email_array[0]);
			for ($i = 0; $i < sizeof($local_array); $i++) {
				if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i])) {
					return false;
				}
			}  
			if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1])) { // Check if domain is IP. If not, it should be valid domain name
				$domain_array = explode(".", $email_array[1]);
				if (sizeof($domain_array) < 2) {
					return false; // Not enough parts to domain
				}
				for ($i = 0; $i < sizeof($domain_array); $i++) {
					if (!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i])) {
						return false;
					}
				}
			}
			return true;
		}

	}

	/**
	 * Conditionally includes a model validator depending on 
	 * another field value.  If the specified field matches
	 * the required value, then the additional validator will
	 * be added to the validation stack.
	 */
	class ConditionalValidator implements ModelValidator {

		private $validated;
		private $condition;
		private $validator;

		/** 
		 * Create a new validator.
		 * @param string $validated The field the check
		 * @param mixed $condition The value to match against
		 * @param ModelValidator $validator The additional validator to call
		 */
		public function __construct($validated, $condition, $validator) {
			$this->validated = $validated;
			$this->condition = $condition;
			$this->validator = $validator;
		}

		public function validate($object) {
			if ($object[$this->validated] == $this->condition)
				return $this->validator->validate($object);
			return true;
		}

	}

	/**
	 * Validate whether a credit card number is potentially valid.
	 * Just checks the number pattern and checksum, does not actually
	 * validate the account.
	 */
	class CreditCardValidator implements FieldValidator {

		public function validate($value, $model) {

			// Ignore empty fields
			if (!$value)
				return true;

			$card_type = null;
			$card_regexes = array(
					'/^4\d{12}(\d\d\d){0,1}$/' => 'visa',
					'/^5[12345]\d{14}$/'       => 'mastercard',
					'/^3[47]\d{13}$/'          => 'amex',
					'/^6011\d{12}$/'           => 'discover',
					'/^30[012345]\d{11}$/'     => 'diners',
					'/^3[68]\d{12}$/'          => 'diners',
					);

			foreach ($card_regexes as $regex => $type) {
				if (preg_match($regex, $value)) {
					$card_type = $type;
					break;
				}
			}

			if ($card_type) {

				/* Mod 10 checksum algorithm */
				$revcode = strrev($value);
				$checksum = 0; 

				for ($i = 0; $i < strlen($revcode); $i++) {
					$current_num = intval($revcode[$i]);  
					if($i & 1) {  /* Odd  position */
						$current_num *= 2;
					}
					/* Split digits and add. */
					$checksum += $current_num % 10; if
						($current_num >  9) {
							$checksum += 1;
						}
				}

				if ($checksum % 10 == 0)
					//return $card_type;
					return true;

			}

			return false;

		}

	}

