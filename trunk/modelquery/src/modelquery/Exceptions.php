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
	 * Raised if an expected object is not found.
	 */
	class DoesNotExistException extends Exception { }
	/**
	 * Raised if too many objects were found.
	 */
	class TooManyResultsException extends Exception { }
	/**
	 * Raised if invalid parameters were specified.
	 */
	class InvalidParametersException extends Exception { }
	/**
	 * Raised if an unsupported operation was attempted.
	 */
	class InvalidUsageException extends Exception { }
	/**
	 * Raised if a field definition would conflict with an internal
	 * field name.
	 */
	class ReservedFieldException extends Exception { }
	/** 
	 * Raised if a database query fails.
	 */
	class SQLException extends Exception { }
	/**
	 * Raised if a transaction is in an invalid state for
	 * the attempted operation.
	 */
	class TransactionException extends Exception { }

	/** 
	 * Raised if a model's data does not validate correctly.
	 */
	class ValidationException extends Exception {

		private $_model;

		/**
		 * Create a new ValidationException.
		 *
		 * @param string $msg The error message
		 * @param Model $model The source model that caused this error
		 */
		public function __construct($msg, $model = null) {
			parent::__construct($msg);
			if ($model) $this->_model = $model;
		}

		/**
		 * Get the model that raised the validation error.
		 * @return Model The source of the error
		 */
		public function getModel() {
			return $this->_model;
		}

		/**
		 * Get a list of model errors encountered during validation>
		 * @return Array An array of error messages
		 * @see Model::getErrors()
		 */
		public function getErrors() {
			return $this->_model->getErrors();
		}

		/**
		 * Get a list of field errors encountered during validation>
		 * @return Array An array of field error messages
		 * @see Model::getFieldErrors()
		 */
		public function getFieldErrors() {
			return $this->_model->getFieldErrors();
		}

	}
