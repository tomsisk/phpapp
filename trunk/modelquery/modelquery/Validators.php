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

	interface ModelValidator {
		public function validate($model);
	}

	class ConditionalRequiredStringValidator implements ModelValidator {
		private $validated;
		private $condition;
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

	class FieldsMatchValidator implements ModelValidator {
		private $field1;
		private $field2;
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

	class OneOrMoreValidator implements ModelValidator {
		private $fields;
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

	class UniqueFieldsValidator implements ModelValidator {
		private $fields;
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

	interface FieldValidator {
		public function validate($value, $model);
	}

	class RequiredFieldValidator implements FieldValidator{
		public function validate($value, $model) {
			if (is_array($value))
				return count($value) > 0;
			return $value !== null;
		}
	}

	class MinLengthValidator implements FieldValidator {
		private $length;
		public function __construct($length_) {
			$this->length = $length_;
		}
		public function validate($value, $model) {
			return $value ? strlen($value) >= $this->length : true;
		}
	}

	class MaxLengthValidator implements FieldValidator {
		private $length;
		public function __construct($length_) {
			$this->length = $length_;
		}
		public function validate($value, $model) {
			return $value ? strlen($value) <= $this->length : true;
		}
	}

	class UniqueFieldValidator implements FieldValidator {
		private $field;
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

	class NumericValidator implements FieldValidator {

		public function validate($value, $model) {
			if ($value)
				return is_numeric($value);
			return true;
		}

	}

	class PasswordValidator implements FieldValidator {

		private $numrequired;
		private $symrequired;
		private $charpattern;

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

	class NonEmptyArrayValidator implements FieldValidator {
		public function validate($value, $model) {
			return is_array($value) && count($value) > 0;
		}
	}

	class DateValidator implements FieldValidator {
		public function validate($value, $model) {
			return $value !== false;
		}
	}

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

	class ConditionalValidator implements ModelValidator {

		private $validated;
		private $condition;
		private $validator;

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

