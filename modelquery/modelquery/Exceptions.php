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

	class DoesNotExistException extends Exception { }
	class TooManyResultsException extends Exception { }
	class InvalidParametersException extends Exception { }
	class InvalidUsageException extends Exception { }
	class ReservedFieldException extends Exception { }
	class SQLException extends Exception { }
	class TransactionException extends Exception { }

	class ValidationException extends Exception {

		private $_model;

		public function __construct($msg, $model = null) {
			parent::__construct($msg);
			if ($model) $this->_model = $model;
		}

		public function getModel() {
			return $this->_model;
		}

		public function getErrors() {
			return $this->_model->getErrors();
		}

		public function getFieldErrors() {
			return $this->_model->getFieldErrors();
		}

	}
