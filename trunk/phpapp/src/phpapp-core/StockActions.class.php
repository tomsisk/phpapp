<?php
	/**
	 * @package phpapp-core
	 * @filesource
	 */

	/*
	 * PHPApp - a PHP application framework
	 * Copyright (C) 2004 Jeremy Jongsma.  All rights reserved.
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

	class StockActions {

		public static function showObjectList($id, $params, $modeladmin, $method) {

			if ($modeladmin->matchesMaster()) {
				if ($filterobj = $modeladmin->getAdmin()->getFilterObject($modeladmin)) {
					if ($modeladmin->checkPermission('MODIFY'))
						$modeladmin->execute(array('edit', $filterobj->pk), $params, 'get');
					else
						$modeladmin->execute(array('view', $filterobj->pk), $params, 'get');
					return true;
				}
			}

			$page = array_key_exists('_p', $params) ? $params['_p'] : 1;
			$sort = array_key_exists('_s', $params) ? $params['_s'] : null;
			$search = array_key_exists('_q', $params) ? $params['_q'] : null;
			$pagesize = array_key_exists('_r', $params) ? intval($params['_r']) : 25;

			$filterFields = $modeladmin->getFilterFields();
			$filters = array();
			$orfilters = array();
			$filteredFields = array();
			if ($filterFields)
				foreach ($filterFields as $ff)
					if (array_key_exists($ff, $params)) {
						if (isset($modeladmin->customFilters[$ff])) {
							$filterDef = $modeladmin->customFilters[$ff];
							if (isset($filterDef[$params[$ff]])) {
								$fd = $filterDef[$params[$ff]];
								if ($fd[2] == 'or')
									$orfilters = array_merge($orfilters, $fd[1]);
								else
									$filters = array_merge($filters, $fd[1]);
							} else {
								$filters[$ff] = $params[$ff];
							}
						} else {
							if ($params[$ff] === '') {
								$mf = $modeladmin->getPrototype()->_fields[$ff];
								if ($mf->options['blankisnull'])
									$filters[$ff.':isnull'] = true;
								else
									$filters[$ff] = $params[$ff];
							} else {
								$filters[$ff] = $params[$ff];
							}
						}
						$filteredFields[$ff] = $params[$ff];
					}

			if (!$modeladmin->checkPermission('VIEW'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$query = $modeladmin->getQuery();
			$basequery = $query->filter($filters);
			if (count($orfilters))
				$basequery = $basequery->filteror($orfilters);

			if ($sort)
				$basequery = $basequery->order($sort);
			$searchFields = $modeladmin->getSearchFields();
			if ($search && count($searchFields)) {
				$args = array();
				foreach($searchFields as $sf) {
					$args[] = $sf.':contains';
					$args[] = $search;
				}
				$basequery = call_user_func_array(array($basequery, 'filteror'), $args);
			}

			// Load objects
			$count = $basequery->count();

			// Add aggregates
			if ($modeladmin->displayFields)
				foreach ($modeladmin->displayFields as $df) {
					if (strpos($df, '(') !== FALSE) {
						$aggFn = substr($df, 0, strpos($df, '('));
						$aggField = substr($df, strpos($df, '(') + 1, -1);
						$basequery = $basequery->aggregate($df, $aggField, $aggFn);
					}
				}

			// If filters were added and current page is non-existent, go to last valid page
			if (($page-1)*$pagesize > $count) {
				$page = ceil($count / $pagesize);
				if (!$page) $page = 1;
			}
			if ($pl = $modeladmin->getMergeFields()) {
				foreach ($pl as $pf)
					$basequery = $basequery->preload($pf);
			}
			$objects = $modeladmin->getPaginated()
				? $basequery->slice($pagesize, ($page-1)*$pagesize)
				: $basequery;

			if (!$pagesize)
				$pagesize = $count;

			// If there is a single exact match on the primary search field, send to edit page
			if ($count == 1 && $search && $searchFields && $objects[0][$searchFields[0]] == $search)
				return $modeladmin->relativeUrl('/edit/'.$objects[0]->pk);

			// Else, show list
			$availfilters = array();
			if (count($filterFields)) {
				foreach ($filterFields as $field) {
					$filtered = false;
					$custom = isset($modeladmin->customFilters[$field])
						? $modeladmin->customFilters[$field]
						: null;
					list($fieldobj, $modelobj) = $modeladmin->findFieldAndObject($field);
					$fieldname = $fieldobj->field;

					// Restrict to in-use values, unless a limited selection is
					// available (i.e. BooleanField, fields with "options" option)
					$inuse = null;
					if ($custom)
						$inuse = array($field => array_keys($custom));
					elseif (isset($fieldobj->options['options'])
							&& $fieldoptions = $fieldobj->options['options'])
						$inuse = array($field => array_keys($fieldoptions));
					elseif ($fieldobj instanceof BooleanField)
						$inuse = array($field => array(true, false));
					elseif ($fieldobj instanceof ManyToManyField) {
						$modelobj = $fieldobj->getRelationModel();
						$fieldname = $modelobj->_idField;
					}
					if (!$inuse || count($inuse[$field]) > 10) {
						if (strpos($field, '.') !== FALSE) {
							$filteradmin = $modeladmin->getQuery()->extra(array($field => $field));
							$inuse = $filteradmin->distinct()->values($field);
						} else {
							$filteradmin = $modeladmin->getQuery(get_class($modelobj));
							$values = $filteradmin->distinct()->values($fieldname);
							$inuse[$field] = $values[$fieldname];
						}
					}

					$filter = array('name' => $fieldobj->name, 'objects' => array());
					if ($custom) {

						foreach ($custom as $val => $def) {
							$filter['objects'][] = array($val, $def[0], isset($filteredFields[$field]) && ($filteredFields[$field] === strval($val)));
							$filtered = $filtered || (isset($filteredFields[$field]) && $filteredFields[$field] === strval($val));
						}

					} elseif ($fieldobj instanceof RelationField) {

						$fma = $modeladmin->getAdmin()->findModelAdmin($fieldobj->relationName);

						if ($modeladmin->module->master == $fma && $modeladmin->getAdmin()->getFilter($fma->getQueryName()))
							continue;

						if ($modeladmin->isAccessFiltered($fieldname))
							continue;

						$fq = $fma->getQuery();
						if (isset($inuse[$field]) && count($inuse[$field])) {
							$related = $fq->filter('pk:in', $inuse[$field]);
							foreach($related as $r) {
								$fo = array($r->pk, $modeladmin->getAdmin()->getApplication()->toString($r),
										(array_key_exists($field, $filteredFields) && $fieldobj->convertValue($filters[$field]) === $r->pk)
									);
								$filter['objects'][] = $fo;
								$filtered = $filtered || $fo[2];
							}
						}
					} else {
						foreach ($inuse[$field] as $val) {
							$fo = array($fieldobj->serialize($val), $fieldobj->toString($val),
								(array_key_exists($field, $filteredFields)
									&& $fieldobj->convertValue($filteredFields[$field]) === $fieldobj->convertValue($val))
							);
							$filter['objects'][] = $fo;
							$filtered = $filtered || $fo[2];
						}
					}
					$filter['empty'] = !$filtered;
					$availfilters[str_replace('.', '__', $field)] = $filter;
				}
			}
			$sortdir = '+';
			if ($sort) {
				$first = substr($sort, 0, 1);
				if ($first == '+' || $first == '-') {
					$sortdir = $first;
					$sort = substr($sort, 1);
				}
			}

			$modeladmin->renderTemplate('object_list.tpl', array('objects' => $objects->all(),
					'page' => $page,
					'pages' => ceil($count / $pagesize),
					'pagestart' => ($page - 1) * $pagesize + 1,
					'pageend' => $page * $pagesize > $count ? $count : $page * $pagesize,
					'objectcount' => $count,
					'sort' => $sort,
					'sortdir' => $sortdir,
					'search' => $search,
					'prototype' => $modeladmin->getPrototype(),
					'displayFields' => $modeladmin->getDisplayFields(),
					'searchFields' => $searchFields,
					'filterFields' => $filterFields,
					'filters' => $filters,
					'filterstr' => StockActions::getFilterString($modeladmin->getPrototype(), $modeladmin, $params),
					'availfilters' => $availfilters)
				);
			return true;
		}

		public static function showObjectFilter($id, $params, $modeladmin, $method) {

			if ($id) {
				$modeladmin->getAdmin()->addFilter($modeladmin->getQueryName(), $id);
				print '<script language="javascript">window.opener.location.reload(true); self.close();</script>';
				return true;
			}

			$page = array_key_exists('_p', $params) ? $params['_p'] : 1;
			$sort = array_key_exists('_s', $params) ? $params['_s'] : null;
			$search = array_key_exists('_q', $params) ? $params['_q'] : null;
			$pagesize = array_key_exists('_r', $params) ? $params['_r'] : 25;
			$filters = $params;

			if (!$modeladmin->checkPermission('VIEW'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$query = $modeladmin->getQuery();
			$basequery = $query->filter($filters);
			if ($sort)
				$basequery = $basequery->order($sort);
			$searchFields = $modeladmin->getSearchFields();
			if ($search && count($searchFields)) {
				$args = array();
				foreach($searchFields as $sf) {
					$args[] = $sf.':contains';
					$args[] = $search;
				}
				$basequery = call_user_func_array(array($basequery, 'filteror'), $args);
			}

			// Load objects
			$count = $basequery->count();
			// If filters were added and current page is non-existent, go to last valid page
			if (($page-1)*$pagesize > $count) {
				$page = ceil($count / $pagesize);
				if (!$page) $page = 1;
			}
			$objects = $basequery->slice($pagesize, ($page-1)*$pagesize);

			// If there is a single exact match on the primary search field, send to edit page
			if ($count == 1 && $search && $searchFields && $objects[0][$searchFields[0]] == $search)
				return $modeladmin->relativeUrl('/filter/'.$objects[0]->pk);

			// Else, show list
			$availfilters = array();
			$filterFields = $modeladmin->getFilterFields();
			if (count($filterFields)) {
				foreach ($filterFields as $field) {
					$filtered = false;
					$fieldobj = $modeladmin->getPrototype()->_fields[$field];

					// Restrict to in-use values, unless a limited selection is
					// available (i.e. BooleanField, fields with "options" option)
					$inuse = null;
					if (isset($fieldobj->options['options']) && $fieldoptions = $fieldobj->options['options'])
						$inuse = array($field => array_keys($fieldoptions));
					elseif ($fieldobj instanceof BooleanField)
						$inuse = array($field => array(true, false));
					if (!$inuse || count($inuse[$field]) > 10)
						$inuse = $query->distinct()->values($field);

					$filter = array('name' => $fieldobj->name, 'objects' => array());
					if ($fieldobj instanceof RelationField) {
						$fq = $fieldobj->getRelationModel()->getQuery();
						if (count($inuse[$field])) {
							$related = $fq->filter('pk:in', $inuse[$field]);
							foreach($related as $r) {
								$fo = array($r->pk, $modeladmin->getAdmin()->getApplication()->toString($r),
										(array_key_exists($field, $filters) && $fieldobj->convertValue($filters[$field]) === $r->pk)
									);
								$filter['objects'][] = $fo;
								$filtered = $filtered || $fo[2];
							}
						}
					} else {
						foreach ($inuse[$field] as $val) {
							$fo = array($val, $fieldobj->toString($val),
								(array_key_exists($field, $filters) && $fieldobj->convertValue($filters[$field]) === $val)
							);
							$filter['objects'][] = $fo;
							$filtered = $filtered || $fo[2];
						}
					}
					$filter['empty'] = !$filtered;
					$availfilters[$field] = $filter;
				}
			}
			$sortdir = '+';
			if ($sort) {
				$first = substr($sort, 0, 1);
				if ($first == '+' || $first == '-') {
					$sortdir = $first;
					$sort = substr($sort, 1);
				}
			}

			$modeladmin->renderTemplate('object_filter.tpl', array('objects' => $objects->all(),
					'page' => $page,
					'pages' => ceil($count / $pagesize),
					'pagestart' => ($page - 1) * $pagesize + 1,
					'pageend' => $page * $pagesize > $count ? $count : $page * $pagesize,
					'objectcount' => $count,
					'sort' => $sort,
					'sortdir' => $sortdir,
					'search' => $search,
					'prototype' => $modeladmin->getPrototype(),
					'displayFields' => $modeladmin->getDisplayFields(),
					'searchFields' => $searchFields,
					'filterFields' => $filterFields,
					'filters' => $filters,
					'filterstr' => StockActions::getFilterString($modeladmin->getPrototype(), $modeladmin, $params),
					'availfilters' => $availfilters),
					'popup.tpl'
				);
			return true;
		}

		public static function exportObjectList($id, $params, $modeladmin, $method) {
			$sort = array_key_exists('_s', $params) ? $params['_s'] : null;
			$search = array_key_exists('_q', $params) ? $params['_q'] : null;

			$filters = array();
			$orfilters = array();

			foreach ($params as $k => $v) {
				if (isset($modeladmin->customFilters[$k])) {
					$filterDef = $modeladmin->customFilters[$k];
					if (isset($filterDef[$params[$k]])) {
						$fd = $filterDef[$params[$k]];
						if ($fd[2] == 'or')
							$orfilters = array_merge($orfilters, $fd[1]);
						else
							$filters = array_merge($filters, $fd[1]);
					} else {
						$filters[$k] = $params[$k];
					}
				} else {
					$filters[$k] = $v;
				}
			}

			if (!$modeladmin->checkPermission('VIEW'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$query = $modeladmin->getQuery();
			$basequery = $query->filter($filters);
			if (count($orfilters) > 0)
				$basequery = $query->filteror($orfilters);

			if ($sort)
				$basequery = $basequery->order($sort);
			$searchFields = $modeladmin->getSearchFields();
			if ($search && count($searchFields)) {
				$args = array();
				foreach($searchFields as $sf) {
					$args[] = $sf.':contains';
					$args[] = $search;
				}
				$basequery = call_user_func_array(array($basequery, 'filteror'), $args);
			}

			// No time limit, exports can take awhile
			set_time_limit(0);

			// Load objects
			$count = $basequery->count();
			$objects = $basequery->cursor();

			$sortdir = '+';
			if ($sort) {
				$first = substr($sort, 0, 1);
				if ($first == '+' || $first == '-') {
					$sortdir = $first;
					$sort = substr($sort, 1);
				}
			}

			$expfile = strtolower($modeladmin->getAdmin()->getApplication()->pluralize(str_replace(' ', '_', $modeladmin->getName())));
			if ($params['exporttype'] == 'xml')
				StockActions::doXMLExport($objects, $modeladmin->getPrototype(), $expfile);
			else
				StockActions::doCSVExport($objects, $modeladmin->getPrototype(), $expfile);

			return true;
		}

		private static function doCSVExport($objects, $prototype, $filename) {
			// CSV type
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename='.$filename.'.csv');

			$fields = array();
			$first = true;
			foreach ($prototype->_fields as $name => $field) {
				if ((!$field->options['editable'] &&
						!$field->options['readonly']) ||
						$field instanceof RelationSetField)
					continue;
				if (!$first) echo ',';
				echo '"'.str_replace('"', '\\"', $field->name).'"';
				$first = false;
				$fields[] = $name;
			}
			foreach ($objects as $obj) {
				echo "\n";
				$first = true;
				foreach ($fields as $field) {
					if (!$first) echo ',';
					$escaped = str_replace('"', '\\"', $obj->_fields[$field]->serialize(($obj->$field)));
					echo '"'.$escaped.'"';
					$first = false;
				}
			}
		}

		private static function doXMLExport($objects, $prototype, $filename) {
			// XML type
			header('Content-Type: text/xml');
			header('Content-Disposition: attachment; filename='.$filename.'.xml');

			$fields = array();
			foreach ($prototype->_fields as $name => $field) {
				if ((!$field->options['editable'] &&
						!$field->options['readonly']) ||
						$field instanceof RelationSetField)
					continue;
				$fields[] = $name;
			}

			$tag = strtolower(get_class($prototype));
			echo "<?xml version=\"1.0\"?>\n";
			echo "<$filename>\n";
			foreach ($objects as $obj) {
				echo "	<$tag>\n";
				foreach ($fields as $field) {
					$escaped = $obj->_fields[$field]->serialize($obj->$field);
					echo "		<$field>$escaped</$field>\n";
				}
				echo "	</$tag>\n";
			}
			echo "</$filename>\n";
		}

		public static function verifyObjectImport($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('CREATE'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$modeladmin->renderTemplate('object_import.tpl',
				StockActions::getImportValidationContext($params, $modeladmin));

			return true;
		}

		public static function validateObjectImport($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('CREATE'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$context = StockActions::getImportValidationContext($params, $modeladmin, true);

			if (count($context['fieldErrors']) == 0)
				$modeladmin->renderTemplate('object_importvalidate.tpl', $context);
			else
				$modeladmin->renderTemplate('object_import.tpl', $context);

			return true;
		}

		public static function doObjectImport($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('CREATE'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$context = StockActions::getImportValidationContext($params, $modeladmin, true, true);

			$modeladmin->renderTemplate('object_imported.tpl', $context);

			return true;
		}

		private static function getImportValidationContext($params, $modeladmin, $validate = false, $save = false) {

			// Get uploaded file
			if (array_key_exists('importfile', $params))
				$importfile = '/tmp/'.basename($params['importfile']);
			elseif (!array_key_exists('upload', $params) || !file_exists($_FILES['upload']['tmp_name']))
				throw new Exception('No file was selected.');
			else {
				$filename = $_FILES['upload']['tmp_name'];
				$importfile = tempnam('/tmp', 'import_');
				rename($filename, $importfile);
			}

			// Check that all required fields are specified
			if ($fh = fopen($importfile, 'r')) {
				$fieldErrors = array();
				$validationErrors = array();
				$header = null;
				$sample = null;

				if ($params['hasheader'])
					$header = fgetcsv($fh);
				else {
					$header = array();
					foreach ($sample as $field)
						$header[] = 'Field '.(sizeof($header)+1);
				}

				$fields = array();
				$forcevalues = array();
				foreach ($modeladmin->getPrototype()->_fields as $name => $field) {
					if ($master != $modeladmin) {
						if ($field instanceof ManyToOneField && $master && $field->relationName == $master->getQueryName())
							if ($filterid = $modeladmin->getAdmin()->getFilter($master->getQueryName())) {
								$forcevalues[$name] = $filterid;
								$field->options['editable'] = false;
							}
					} elseif ($requiredFilter) {
						if ($field instanceof ManyToOneField && $field->relationName == $requiredFilter)
							if ($filterid = $modeladmin->getAdmin()->getFilter($requiredFilter)) {
								$forcevalues[$name] = $filterid;
								$field->options['editable'] = false;
							}
					}
					if ($field instanceof RelationSetField || (!$field->options['editable'] && !$field->options['readonly']))
						continue;
					$hasvalue = false;
					$match = -1;
					if (array_key_exists('_fieldsource_'.$name, $params)) {
						if ($params['_fieldsource_'.$name] == 'csv') {
							$match = $params['_csv_'.$name];
							$hasvalue = $match > -1;
						} else {
							$hasvalue = array_key_exists($name, $params) && $params[$name] !== '';
						}
					} else {
						$match = StockActions::getFieldMatchIndex($field, $header);
					}
					$fields[$name] = array('field' => $field,
									'required' => $field->options['required'] && !$field->options['default'] && !($field instanceof BooleanField),
									'match' => $match);
					if ($validate && $fields[$name]['required'] && !$hasvalue)
						$fieldErrors[$name] = 'This field is required';
				}

				$lineno = 1;
				$errorCount = 0;
				$recordCount = 0;
				$overrides = array();
				while ($record = fgetcsv($fh)) {
					if ($lineno == 1)
						$sample = $record;
					if ($validate) {
						$obj = $modeladmin->getAndUpdate(null, $params, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
						foreach ($fields as $f => $d) {
							$override = '_override_'.$lineno.'_'.$f;
							if (array_key_exists($override, $params) && $params[$override]) {
								$obj->$f = $params[$override];
								$overrides[$override] = $params[$override];
							} elseif ($d['match'] > -1) {
								$obj->$f = $record[$d['match']];
							}
						}
						$obj->updateModel($forcevalues);
						try {
							if ($save)
								$obj->save();
							elseif (!$obj->validate())
								throw new ValidationException('Validation failed');
							$recordCount++;
						} catch (ValidationException $ve) {
							if (count($validationErrors) < 10) {
								$errors = array('object' => $obj,
										'errors' => $obj->getErrors(),
										'fieldErrors' => $obj->getFieldErrors(),
										'line' => $record);
								$validationErrors[$lineno] = $errors;
								if ($errors['fieldErrors'])
									foreach ($errors['fieldErrors'] as $fn => $fe) {
										$override = '_override_'.$lineno.'_'.$fn;
										if (array_key_exists($override, $overrides))
											unset($overrides[$override]);
									}
							}
							$errorCount++;
						}
					}
					$lineno++;
				}

				fclose($fh);

				return array('header' => $header,
						'sample' => $sample,
						'hasheader' => $params['hasheader'],
						'importfile' => basename($importfile),
						'prototype' => $modeladmin->getAndUpdate(null, $params),
						'fields' => $fields,
						'overrides' => $overrides,
						'fieldErrors' => $fieldErrors,
						'validationErrors' => $validationErrors,
						'errorCount' => $errorCount,
						'recordCount' => $recordCount,
						'lineCount' => $lineno - 1);
			} else {
				throw new Exception('Import data file was not found on the server.');
			}
			return false;
		}

		private static function getFieldMatchIndex($field, $matches) {
			for ($i = 0; $i < count($matches); ++$i) {
				if (strtolower($matches[$i]) == strtolower($field->name)
						 || strtolower($matches[$i]) == strtolower($field->field))
					return $i;
			}
			return -1;
		}

		public static function doObjectView($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('VIEW', $id))
				throw new AccessDeniedException('You are not allowed to view this item.');

			$object = $modeladmin->getQuery()->get($id);
			$context = array('object' => $object,
							'filterstr' => StockActions::getFilterString($object, $modeladmin, $params));
			$modeladmin->renderTemplate('object_detail.tpl', $context);
			return true;
		}

		public static function doObjectEdit($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('MODIFY', $id))
				throw new AccessDeniedException('You are not allowed to edit this item.');

			$params = StockActions::groupEmbeddedParams($params, $modeladmin); 
			$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM);
			$modeladmin = $modeladmin->getTypeAdmin($object);

			$context = array('pk' => $id,
							'object' => $object,
							'filterstr' => StockActions::getFilterString($object, $modeladmin, $params));
			$modeladmin->renderTemplate('object_form.tpl', $context);
			return true;
		}

		public static function doObjectAdd($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('CREATE'))
				throw new AccessDeniedException('You are not allowed to create a new item.');

			$params = StockActions::groupEmbeddedParams($params, $modeladmin); 
			$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM, true);
			$modeladmin = $modeladmin->getTypeAdmin($object);

			if ($mfs = $modeladmin->getMergeFields()) {
				foreach ($mfs as $mf) {
					$field = $object->_fields[$mf];
					$query = $modeladmin->getQuery($field->relationName);
					$object->setFieldValue($mf, $query->create(array(), null, UPDATE_FROM_FORM));
				}
			}
			$context = array('pk' => $id,
							'object' => $object,
							'filterstr' => StockActions::getFilterString($object, $modeladmin, $params));
			$modeladmin->renderTemplate('object_form.tpl', $context);
			return true;
		}

		public static function doObjectAddPopup($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('CREATE'))
				throw new AccessDeniedException('You are not allowed to create a new item.');

			$params = StockActions::groupEmbeddedParams($params, $modeladmin); 
			$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM, true);
			$modeladmin = $modeladmin->getTypeAdmin($object);

			$context = array('pk' => $id,
							'object' => $object,
							'popup' => true,
							'filterstr' => StockActions::getFilterString($object, $modeladmin, $params));
			$modeladmin->renderTemplate('object_form.tpl', $context, 'popup.tpl');
			return true;
		}

		public static function doObjectAutocomplete($id, $params, $modeladmin, $method) {
			$search = $params['search'];
			$limit = 10;

			if (!$modeladmin->checkPermission('VIEW'))
				throw new AccessDeniedException('You are not allowed to view this section.');

			$searchFields = $modeladmin->getSearchFields();
			if (count($searchFields)) {
				$query = $modeladmin->getQuery()->filter($searchFields[0].':contains', $search);
				foreach ($params as $k => $v)
					if ($k{0} != '_')
						$query = $query->filter($k, $v);
				$objects = $query->slice($limit, $offset);
				$list = '<ul class="autocomplete">';
				foreach ($objects as $o) {
					$list .= '<li>'.($modeladmin->getAdmin()->getApplication()->toString($o)).'</li>';
				}
				$list .= '</ul>';
				echo $list;
			}
			return true;
		}

		public static function showObjectDeleteConfirm($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('DELETE', $id))
				throw new AccessDeniedException('You are not allowed to delete this item.');

			$object = $modeladmin->getQuery()->get($id);
			$context = array('object' => $object,
							'filters' => $params,
							'filterstr' => StockActions::getFilterString($object, $modeladmin, $params));
			$modeladmin->renderTemplate('object_delete.tpl', $context);
			return true;
		}

		public static function doObjectDelete($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('DELETE', $id))
				throw new AccessDeniedException('You are not allowed to delete this item.');

			$object = $modeladmin->getQuery()->get($id);
			$filterstr = StockActions::getFilterString($object, $modeladmin, $params);
			$isFilter = $modeladmin->getAdmin()->getFilter($modeladmin->getQueryName()) && true;
			try {
				$object->delete();
				$admin = $modeladmin->getAdmin();
				if ($admin->getFilter($modeladmin->getQueryName()))
				if ($isFilter)
					$modeladmin->getAdmin()->addFilter($modeladmin->getQueryName(), null);

				if ($modeladmin->parent && $modeladmin->getInlineOnly()) {
					$parent = $object[$modeladmin->parent];
					$ma = $modeladmin->getAdmin()->findModelAdmin($parent);
					return $ma->relativeUrl('/edit/'.$parent->pk);
				} elseif ($isFilter)
					return $modeladmin->getModule()->relativeUrl('/');
				else
					return $modeladmin->relativeUrl('/'.($filterstr?'?'.$filterstr:''));
			} catch(Exception $e) {
				$modeladmin->renderTemplate('object_delete_failed.tpl', array('object' => $object, 'filterstr' => $filterstr, 'error' => $e));
				return false;
			}
		}

		public static function processFileUploads($id, &$params, $modeladmin, $method) {

			$config = $modeladmin->getAdmin()->getApplication()->config;
			$uploadDir = isset($config['file_upload_directory']) ? $config['file_upload_directory'] : null;
			$removeFiles = array();

			// Check for file uploads
			foreach ($modeladmin->getPrototype()->_fields as $f => $def) {

				if ($def instanceof FileField) {

					if (!$uploadDir)
						throw new Exception('File uploads are not configured correctly on this server (no "file_upload_directory" configuration directive found.)');

					if (isset($params[$f])
							&& is_array($params[$f])
							&& isset($params[$f]['tmp_name'])
							&& $params[$f]['tmp_name']) {

						$filename = $params[$f]['name'];
						if (file_exists($uploadDir.'/'.$filename)) {
							$ext = null;
							if (strpos($filename, '.') !== FALSE) {
								$ext = substr($filename, strrpos($filename, '.') + 1);
								$filename = substr($filename, 0, strrpos($filename, '.'));
							}

							$ct = 0;
							$dest = $filename.'_'.($ct++).'.'.$ext;
							while (file_exists($uploadDir.'/'.$dest))
								$dest = $filename.'_'.($ct++).'.'.$ext;

							$filename = $dest;
						}

						rename($params[$f]['tmp_name'], $uploadDir.'/'.$filename);
						$params[$f] = $filename;

					} elseif (isset($params['_remove_'.$f])) {

						unset($params['_remove_'.$f]);
						$params[$f] = null;
						$removeFiles[] = $f;

					} else {

						unset($params[$f]);

					}

				}
			}

			// Clean up removed file uploads
			if ($id && $uploadDir && count($removeFiles) > 0) {
				$object = $modeladmin->getQuery()->get($id);
				foreach ($removeFiles as $f) {
					if (file_exists($uploadDir.'/'.$object->$f))
						unlink($uploadDir.'/'.$object->$f);
				}
			}

		}

		public static function doObjectSave(&$id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission(($id ? 'MODIFY' : 'CREATE'), ($id ? $id : 'ALL')))
				throw new AccessDeniedException('You are not allowed to modify this item.');

			$relatedErrors = array();
			$relatedFieldErrors = array();
			$inlineObjects = array();
			$errors = array();
			$fieldErrors = array();

			try {
				// Process uploads first since it changes param values after
				// moving temp files
				StockActions::processFileUploads($id, $params, $modeladmin, $method);
			} catch(Exception $e) {
				$errors[] = $e->getMessage();
			}

			try {

				$params = StockActions::groupEmbeddedParams($params, $modeladmin);
				$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
				// Reuse IDs generated by failed transactions
				if ($id) $object->pk = $id;

				// Reload real admin object in case of subclassing
				$modeladmin = $modeladmin->getTypeAdmin($object);

				// Add errors from file uploads
				if (count($errors)) {
					foreach ($errors as $e) {
						$object->addError($e);
					}
					$errors = array();
				}

				// Check for one-to-one fields edited inline and update them
				if ($mfs = $modeladmin->getMergeFields()) {
					foreach ($mfs as $mf) {
						$rparams = StockActions::filterParams($params, $mf.'.', true);
						$robject = null;
						$mfobj = $modeladmin->findField($mf);
						$rname = $mfobj->relationName;
						$radmin = $modeladmin->getAdmin()->findModelAdmin($rname);
						if ($id && $object->$mf)
							$robject = $radmin->getAndUpdate($object->$mf->pk, $rparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
						else
							$robject = $radmin->getAndUpdate(null, $rparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
						try {
							if ($mfobj->options['reverseJoin']) {
								$object->save();
								$robject[$mfobj->options['reverseJoin']] = $object;
								$robject->save();
							} else {
								$robject->save();
								$object->$mf = $robject;
							}
						} catch (ValidationException $ve) {
							if (count($robject->getErrors()))
								$errors = array_merge((array)$errors, $robject->getErrors());
							if (count($robject->getFieldErrors()))
								foreach ($robject->getFieldErrors() as $ef => $em)
									$fieldErrors[$mf.'__'.$ef] = $em;
							throw $ve;
						}
					}
				}

				if (isset($params['_savenew'])) {
					// Save a copy - need to recreate object to avoid using cached database ID
					$values = $object->getRawValues();
					unset($values[$object->_idField]);
					$object = $modeladmin->getAndUpdate(null, $values);
				}

				$object->save();
				$id = $object->pk;
				if ($modeladmin->isFilter())
					$modeladmin->getAdmin()->addFilter($modeladmin->getQueryName(), $id);

				// Update relations
				foreach ($object->_fields as $name => $field) {
					if ($field instanceof RelationSetField) {
						$relations = array();
						$validationFailed = false;
						if ($modeladmin->isInlineObject($field->field)) {
							$inlineObjects[$name] = array();
							if (array_key_exists('_'.$name.'_pk', $params)) {
								$relation = $field->getRelationModel();
								for ($i = 0; $i < count($params['_'.$name.'_pk']); ++$i) {
									$pk = $params['_'.$name.'_pk'][$i];
									$hasData = false;
									$relparams = array();
									foreach ($relation->_fields as $rname => $rfield)
										if (array_key_exists('_'.$name.'_'.$rname, $params)) {
											$relparams[$rname] = $params['_'.$name.'_'.$rname][$i];
											if ($rname != $field->joinField)
												$hasData = $hasData || ($relparams[$rname] !== '' && $relparams[$rname] !== null);
										}
									if (!$hasData)
										continue;
									$related = null;
									try {
										if ($pk) {
											$relations[] = $pk;
											$related = $relation->getQuery()->getAndUpdate($pk, $relparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
											$inlineObjects[$name][$i] = $related;
											$related->save();
										} else {
											if ($field instanceof OneToManyField)
												$relparams[$field->joinField] = $object->pk;
											$related = $relation->getQuery()->create($relparams, null, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
											$inlineObjects[$name][$i] = $related;
											$object->$name->add($related);
											$relations[] = $related->pk;
										}
									} catch (ValidationException $ve) {	
										// Add errors and throw exception
										if ($related->getErrors()) {
											if (!array_key_exists($name, $relatedErrors)) $relatedErrors[$name] = array();
											$relatedErrors[$name][$i] = $related->getErrors();
										}
										if ($related->getFieldErrors()) {
											if (!array_key_exists($name, $relatedFieldErrors)) $relatedFieldErrors[$name] = array();
											$relatedFieldErrors[$name][$i] = $related->getFieldErrors();
										}
										$validationFailed = true;
									}
									if (isset($modeladmin->inlineObjects[$field->field])) {
										$selector = $modeladmin->inlineObjects[$field->field]['selector'];
										if ($selector) {
											$selected = $params['~'.$selector[1]];
											if ($selected !== null && $selected == $i) {
												$object[$selector[1]] = $related[$selector[2]];
												try {
													$object->save();
												} catch (Exception $e) {
												}
											}
										}
									}
								}
							}
						} else {
							$visible = false;
							if (!($field instanceof OneToManyField))
								foreach ($modeladmin->getFieldGroups() as $group)
									if (in_array($name, $group['fields'])) {
										if (isset($params[$name]))
											$relations = $params[$name];
										$visible = true;
										break;
									}
							if (!$visible)
								// Don't delete relations that aren't visible
								// on the edit form
								continue;
						}

						$add = $relations;

						// Some m2m have null values, so we can't use relation set
						// TODO: find a way for RelationSet to handle null members
						if ($field instanceof ManyToManyField && isset($field->options['allowNull']) && $field->options['allowNull']) {

							$jq = $field->getJoinModel()->getQuery();
							$current = $jq->filter($field->joinField, $object->pk);

							foreach($current as $c) {
								if (!in_array($c[$field->targetField], $relations)) {
									$c->delete();
								} else {
									$add = array_diff($add, array($c[$field->targetField]));
								}
							}

							foreach ($add as $ar) {
								$newrel = $jq->create(array(
									$field->joinField => $object->pk,
									$field->targetField => $ar ? $ar : null));
								$newrel->save();
							}

						} else {

							$add = $relations;
							$remove = array();
							foreach($object->$name as $rel)
								if (!in_array($rel->pk, $relations))
									$remove[] = $rel;
								else
									$add = array_diff($add, array($rel->pk));

							try {
								foreach ($remove as $rel)
									$object->$name->remove($rel);
								foreach ($add as $rel)
									$object->$name->add($rel);
							} catch (Exception $e) {
							}

						}

						if ($validationFailed)
							throw new ValidationException($object);
					}
				}

				if (count($fieldErrors) > 0 || count($errors) > 0)
					throw new ValidationException($object);

				$filterstr = isset($params['_filterstr']) ? $params['_filterstr'] : null;
				if (isset($params['_return'])) {
					return $params['_return'];
				} elseif ($modeladmin->matchesMaster()) {
					return $modeladmin->getModule()->relativeUrl('/');
				} else if (isset($params['_saveadd'])) {
					return $modeladmin->relativeUrl('/add/'.($filterstr?'?'.$filterstr:''));
				} else if (isset($params['_savecontinue'])) {
					return $modeladmin->relativeUrl('/edit/'.$object->pk.($filterstr?'?'.$filterstr:''));
				} else if ($modeladmin->parent && $modeladmin->getInlineOnly()) {
					$parent = $object[$modeladmin->parent];
					$ma = $modeladmin->getAdmin()->findModelAdmin($parent);
					return $ma->relativeUrl('/edit/'.$parent->pk.($filterstr?'?'.$filterstr:''));
				} else {
					return $modeladmin->relativeUrl('/'.($filterstr?'?'.$filterstr:''));
				}
			} catch (ValidationException $ve) {
				$errors = array_merge((array)$object->getErrors(), $errors);
				$fieldErrors = array_merge((array)$object->getFieldErrors(), $fieldErrors);
				$qf = $modeladmin->getQuery()->factory;
				$qf->fail();

				$context = array('pk' => $id,
								'object' => $object,
								'errors' => $errors,
								'fieldErrors' => $fieldErrors,
								'relatedObjects' => $inlineObjects,
								'relatedErrors' => $relatedErrors,
								'relatedFieldErrors' => $relatedFieldErrors,
								'filterstr' => $params['_filterstr']);
				$modeladmin->renderTemplate('object_form.tpl', $context);
			}
			return false;
		}

		private static function groupEmbeddedParams($params, $modeladmin) {
			$proto = $modeladmin->getPrototype();
			foreach ($params as $n => $v) {
				if (($p = strpos($n, '.')) !== FALSE) {
					$prefix = substr($n, 0, $p);
					if (isset($proto->_fields[$prefix])
							&& $proto->_fields[$prefix] instanceof EmbeddedModelField) {
						if (!isset($params[$prefix]))
							$params[$prefix] = array();
						$params[$prefix][substr($n, $p+1)] = $v;
						unset($params[$n]);
					}
				}
			}
			return $params;
		}

		private static function filterParams($params, $prefix, $remove = true) {
			$filtered = array();
			foreach ($params as $k => $v) {
				if (strpos($k, $prefix) === 0) {
					if ($remove) $k = substr($k, strlen($prefix));
					$filtered[$k] = $v;
				}
			}
			return $filtered;
		}

		public static function doObjectSavePopup(&$id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission(($id ? 'MODIFY' : 'CREATE'), ($id ? $id : 'ALL')))
				throw new AccessDeniedException('You are not allowed to modify this item.');

			try {
				$params = StockActions::groupEmbeddedParams($params, $modeladmin); 
				$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
				// Reuse IDs generated by failed transactions
				if ($id) $object->pk = $id;

				// Load real admin object in case of subclassing
				$modeladmin = $modeladmin->getTypeAdmin($object);

				// Check for one-to-one fields edited inline and update them
				if ($mfs = $modeladmin->getMergeFields()) {
					foreach ($mfs as $mf) {
						$rparams = StockActions::filterParams($params, $mf.'.', true);
						$robject = null;
						$mfobj = $modeladmin->findField($mf);
						$rname = $mfobj->relationName;
						$radmin = $modeladmin->getAdmin()->findModelAdmin($rname);
						if ($id && $object->$mf)
							$robject = $radmin->getAndUpdate($object->$mf->pk, $rparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
						else
							$robject = $radmin->getAndUpdate(null, $rparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
						try {
							// Triggers save() internally
							$object->$mf = $robject;
						} catch (ValidationException $ve) {
							if (count($robject->getErrors()))
								$errors = array_merge((array)$errors, $robject->getErrors());
							if (count($robject->getFieldErrors()))
								foreach ($robject->getFieldErrors() as $ef => $em)
									$fieldErrors[$mf.'__'.$ef] = $em;
							throw $ve;
						}
					}
				}

				$object->save();
				$id = $object->pk;

				// Update relations
				foreach ($object->_fields as $name => $field) {
					if ($field instanceof RelationSetField) {
						$relations = array_key_exists($name, $params) ? $params[$name] : array();
						$add = $relations;
						$remove = array();

						foreach($object->$name as $rel)
							if (!in_array($rel->pk, $relations))
								$remove[] = $rel;
							else
								$add = array_diff($add, array($rel->pk));

						try {
							foreach ($remove as $rel)
								$object->$name->remove($rel);
							foreach ($add as $rel)
								$object->$name->add($rel);
						} catch (Exception $e) {
						}

					}
				}

				echo '<script language="javascript">self.opener.addPopupValue(\''.$object->pk.'\', \''.$modeladmin->getAdmin()->getApplication()->toString($object).'\');self.close();</script>';
				return true;
			} catch (ValidationException $ve) {
				$context = array('pk' => $id,
								'object' => $object,
								'errors' => $object->getErrors(),
								'popup' => true,
								'fieldErrors' => $object->getFieldErrors(),
								'filterstr' => $params['_filterstr']);
				$modeladmin->renderTemplate('object_form.tpl', $context, 'popup.tpl');
			}
			return false;
		}

		public static function doObjectSaveJSON($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission(($id ? 'MODIFY' : 'CREATE'), ($id ? $id : 'ALL'))) {
				echo 'false';
				return false;
			}
			try {
				$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
				$object->save();
				echo 'true';
			} catch (ValidationException $ve) {
				echo 'false';
			}
			return true;
		}

		public static function doObjectValidateJSON($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission(($id ? 'MODIFY' : 'CREATE'), ($id ? $id : 'ALL'))) {
				echo 'false';
				return false;
			}

			$errors = null;
			$fieldErrors = null;
			$relatedErrors = null;
			$relatedFieldErrors = null;

			$params = StockActions::groupEmbeddedParams($params, $modeladmin); 
			$object = $modeladmin->getAndUpdate($id, $params, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);

			// Check for one-to-one fields edited inline and update them
			if ($mfs = $modeladmin->getMergeFields()) {
				foreach ($mfs as $mf) {
					$rparams = StockActions::filterParams($params, $mf.'.', true);
					$robject = null;
					$mfobj = $modeladmin->findField($mf);
					$rname = $mfobj->relationName;
					$radmin = $modeladmin->getAdmin()->findModelAdmin($rname);
					if ($id && $object->$mf)
						$robject = $radmin->getAndUpdate($object->$mf->pk, $rparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
					else
						$robject = $radmin->getAndUpdate(null, $rparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
					if (!$robject->validate()) {
						if (count($robject->getErrors()))
							$errors = array_merge((array)$errors, $robject->getErrors());
						if (count($robject->getFieldErrors()))
							foreach ($robject->getFieldErrors() as $ef => $em)
								$fieldErrors[$mf.'__'.$ef] = $em;
					}
				}
			}

			if (!$object->validate()) {
				$errors = array_merge((array)$errors, (array)$object->getErrors());
				$fieldErrors = array_merge((array)$fieldErrors, (array)$object->getFieldErrors());
			}

			// Validate any inline objects
			foreach ($object->_fields as $name => $field) {
				if ($modeladmin->isInlineObject($field->field)) {
					if (array_key_exists('_'.$name.'_pk', $params)) {
						$relation = $field->getRelationModel();
						for ($i = 0; $i < count($params['_'.$name.'_pk']); ++$i) {
							$pk = $params['_'.$name.'_pk'][$i];
							$hasData = false;
							$relparams = array($field->joinField => $object->pk);
							foreach ($relation->_fields as $rname => $rfield)
								if (array_key_exists('_'.$name.'_'.$rname, $params)) {
									$relparams[$rname] = $params['_'.$name.'_'.$rname][$i];
									if ($rname != $field->joinField)
										$hasData = $hasData || ($relparams[$rname] !== '' && $relparams[$rname] !== null);
								}
							if (!$hasData)
								continue;
							$related = $pk
								? $relation->getQuery()->getAndUpdate($pk, $relparams, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN)
								: $relation->getQuery()->create($relparams, null, UPDATE_FROM_FORM|UPDATE_FORCE_BOOLEAN);
							if (!$related->validate()) {
								// Add errors and throw exception
								if ($related->getErrors()) {
									if (!$relatedErrors)
										$relatedErrors = array();
									if (!array_key_exists($name, $relatedErrors))
										$relatedErrors[$name] = array();
									$relatedErrors[$name][$i] = $related->getErrors();
								}
								if ($related->getFieldErrors()) {
									if (!$relatedFieldErrors)
										$relatedFieldErrors = array();
									if (!array_key_exists($name, $relatedFieldErrors))
										$relatedFieldErrors[$name] = array();
									// Yuck.  JSON lib can't assign arbitrary numeric indexes
									$relatedFieldErrors[$name]['_'.$i] = $related->getFieldErrors();
								}
							}
						}
					}
				}
			}

			$errors = array('errors' => $errors,
						'fieldErrors' => $fieldErrors,
						'relatedErrors' => $relatedErrors,
						'relatedFieldErrors' => $relatedFieldErrors);
			if (function_exists('json_encode'))
				echo json_encode($errors);
			else {
				require_once('lib/JSON.php');
				$json = new PHPAPP_JSON(PHPAPP_JSON_LOOSE_TYPE);
				echo $json->encode($errors);
			}

			return true;
		}

		public static function doObjectListUpdateJSON($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('VIEW')) {
				echo 'false';
				return false;
			}
			$objects = $modeladmin->getQuery()->filter($params)->cursor();
			$list = array();
			foreach($objects as $object)
				$list[] = array('pk' => $object->pk, 'name' => $modeladmin->getAdmin()->getApplication()->toString($object));
			if (count($list)) {
				if (function_exists('json_encode'))
					echo json_encode($list);
				else {
					require_once('lib/JSON.php');
					$json = new PHPAPP_JSON(PHPAPP_JSON_LOOSE_TYPE);
					echo $json->encode($list);
				}
			} else {
				echo 'false';
			}
			return true;
		}

		public static function doObjectSearchJSON($id, $params, $modeladmin, $method) {
			if (!$modeladmin->checkPermission('VIEW')) {
				echo 'false';
				return false;
			}
			$objects = $modeladmin->getQuery()->filter($params)->hash('pk');
			if (count($objects)) {
				if (function_exists('json_encode'))
					echo json_encode($objects);
				else {
					require_once('lib/JSON.php');
					$json = new PHPAPP_JSON(PHPAPP_JSON_LOOSE_TYPE);
					echo $json->encode($objects);
				}
			} else {
				echo 'false';
			}
			return true;
		}

		public static function showObjectSaved($id, $params, $modeladmin, $method) {
			$context = array('object' => $modeladmin->getQuery()->get($id));
			$this->renderTemplate('object_saved.tpl', $context);
			return true;
		}

		public static function getFilterString($object, $modeladmin, $params) {
			$filterstr = '';

			$filterFields = $modeladmin->getFilterFields();
			foreach($params as $key => $value)
				if (!is_array($value) && (($filterFields && in_array($key, $filterFields)) || $key{0} == '_'))
					$filterstr .= ($filterstr?'&':'').rawurlencode(str_replace('.', '__', $key)).'='.rawurlencode($value);

			return $filterstr;
		}

	}
