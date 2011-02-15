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

	require_once('StockActions.class.php');

	define('CHANGE_ONLY', 'CHANGE_ONLY');

	class ModelAdmin {

		public $id;
		public $modelClass;
		public $prototype;
		public $permission;
		public $name;
		public $pluralName;
		public $icon;
		public $description;
		public $inlineOnly;
		public $readonly;
		public $previewUrl;
		public $downloadUrl;
		public $instructions;
		public $paginated = true;
		public $sortable = null;
		public $module;
		public $query;
		public $queryFilter = array();
		public $queryName;
		public $parent;
		public $boundTo;
		public $subtypeAdmins;
		public $templateContext;
		public $allowImport = true;
		public $allowExport = true;
		public $saveNew = false;
		public $saveAndAdd = false;

		public $actionHandlers = array();
		public $actionPreHooks = array();
		public $actionPostHooks = array();

		public $modelAccess = array();
		public $resolvedModelAccess = array();
		public $matchedFields = array();
		public $fieldRestrictions = array();
		public $fieldTemplates = array();
		public $displayFields;
		public $fieldNames;
		public $searchFields;
		public $filterFields;
		public $customFilters = array();
		public $fieldGroups;
		public $inlineObjects;
		public $mergeObjects;
		public $fieldOptions;
		public $deleteConfirm = false;

		public function  __construct($modelClass, $module, $id = null, $subtypes = null) {
			$this->modelClass = $modelClass;
			$this->module = $module;
			$this->queryName = $modelClass;
			$this->id = $id ? $id : strtolower($modelClass);
			$this->permission = strtoupper($modelClass);

			// Prime prototype; test for cache
			$this->getPrototype();

			$this->registerActionHandler(null, array('StockActions', 'showObjectList'));
			$this->registerActionHandler('add', array('StockActions', 'doObjectAdd'));
			$this->registerActionHandler('addpopup', array('StockActions', 'doObjectAddPopup'));
			$this->registerActionHandler('autocomplete', array('StockActions', 'doObjectAutocomplete'));
			$this->registerActionHandler('delete', array('StockActions', 'doObjectDelete'));
			$this->registerActionHandler('deleteconfirm', array('StockActions', 'showObjectDeleteConfirm'));
			$this->registerActionHandler('edit', array('StockActions', 'doObjectEdit'));
			$this->registerActionHandler('export', array('StockActions', 'exportObjectList'));
			$this->registerActionHandler('filter', array('StockActions', 'showObjectFilter'));
			$this->registerActionHandler('import', array('StockActions', 'doObjectImport'));
			$this->registerActionHandler('validateimport', array('StockActions', 'validateObjectImport'));
			$this->registerActionHandler('verifyimport', array('StockActions', 'verifyObjectImport'));
			$this->registerActionHandler('save', array('StockActions', 'doObjectSave'));
			$this->registerActionHandler('saved', array('StockActions', 'showObjectSaved'));
			$this->registerActionHandler('savejs', array('StockActions', 'doObjectSaveJSON'));
			$this->registerActionHandler('savepopup', array('StockActions', 'doObjectSavePopup'));
			$this->registerActionHandler('searchjs', array('StockActions', 'doObjectSearchJSON'));
			$this->registerActionHandler('listupdatejs', array('StockActions', 'doObjectListUpdateJSON'));
			$this->registerActionHandler('validate', array('StockActions', 'doObjectValidateJSON'));
			$this->registerActionHandler('view', array('StockActions', 'doObjectView'));

			$this->templateContext = new PhpAppTemplateContext($module->templateContext);
			if ($module->baseDir)
				$this->templateContext->resolver->addPath($this->module->baseDir.'/templates/'.$this->id);
			if ($module->extendsDir)
				$this->templateContext->resolver->addPath($this->module->extendsDir.'/templates/'.$this->id);

			$this->templateContext->context = array(
				'modeladmin' => $this,
				'section' => $this->id,
				'sectionurl' => $this->relativeUrl('')
				);

		}

		public function execute($path, $params, $method) {

			if ($this->boundTo) {
				$field = $this->getPrototype()->_fields[$this->boundTo];
				if ($field instanceof RelationField) {
					$modeladmin = $this->getAdmin()->findModelAdmin($field->getRelationModel());
					if ($modeladmin) {
						if (isset($path[1])) {
							$obj = $this->getQuery()->get($path[1]);
							$path[1] = $obj[$this->boundTo]->pk;
						}
						return $modeladmin->execute($path, $params, $method);
					}
				}
			}

			if (!$this->checkAccess()) {
				$ade = new AccessDeniedException('You are not allowed to view this section.');
				$ade->setContext($this->templateContext->context);
				throw $ade;
			}

			// Model actions
			$action = array_shift($path);
			$id = sizeof($path) == 1 ? array_shift($path) :
					(sizeof($path) ? $path : 0);
			if (array_key_exists($action, $this->actionHandlers)) {
				try {
					if (array_key_exists($action, $this->actionPreHooks))
						foreach ($this->actionPreHooks[$action] as $hook)
							call_user_func($hook, &$id, &$params, $this, $method);
					if ($result = call_user_func($this->actionHandlers[$action], &$id, &$params, $this, $method))
						if (array_key_exists($action, $this->actionPostHooks))
							foreach ($this->actionPostHooks[$action] as $hook) {
								$retval = call_user_func($hook, &$id, &$params, $this, $method);
								if (is_string($retval) || $retval === FALSE)
									$result = $retval;
							}
					if ($result && is_string($result))
						header('Location: '.$result);
				} catch (AccessDeniedException $ade) {
					$ade->setContext($this->templateContext->context);
					throw $ade;
				} catch (DoesNotExistException $dne) {
					throw new NotFoundException('The requested item could not be found.');
				} catch (Exception $e) {
					throw $e;
				}
			} else {
				throw new NotFoundException('The requested page does not exist.');
			}
		}

		public function registerActionHandler($action, $handler) {
			$this->actionHandlers[$action] = $handler;
		}

		public function registerActionPreHook($action, $hook) {
			if (!array_key_exists($action, $this->actionPreHooks))
				$this->actionPreHooks[$action] = array();
			$this->actionPreHooks[$action][] = $hook;
		}

		public function registerActionPostHook($action, $hook) {
			if (!array_key_exists($action, $this->actionPostHooks))
				$this->actionPostHooks[$action] = array();
			$this->actionPostHooks[$action][] = $hook;
		}

		public function includeScript($script) {
			$real = $this->module->admin->mediaRoot . '/modules/'
				. $this->module->id . '/' .$script;
			$this->templateContext->context['script'] = $real;
		}

		public function includeStylesheet($stylesheet) {
			$real = $this->module->admin->mediaRoot . '/modules/'
				. $this->module->id . '/' .$stylesheet;
			$this->templateContext->context['stylesheet'] = $real;
		}

		private function spacify($name) {
			$formatted = '';
			for ($i = 0; $i < strlen($name); ++$i) {
				$ascval = ord($name{$i});
				if ($i > 0 && ($ascval >= 65 && $ascval <= 90) || $ascval == 95 || $ascval == 45)
					$formatted .= ' ';
				if ($ascval != 95 && $ascval != 45)
					$formatted .= $name{$i};
			}
			return $formatted;
		}

		public function findFieldAndObject($fieldname, $object = null) {
			if (!$object) $object = $this->getPrototype();
			$field = null;
			$prefix = null;
			if (strpos($fieldname, '.') !== false) {
				$fieldpath = explode('.', $fieldname);
				for ($i = 0; $i < count($fieldpath); ++$i) {
					$fieldname = $fieldpath[$i];
					$field = $object ? $object->_fields[$fieldname] : null;
					if ($i < count($fieldpath)-1) {
						if ($field instanceof RelationSetField) {
							$object = $field->getRelationModel();
						} else {
							$object = $object->$fieldname;
							if (!$object && $field instanceof RelationField)
								$object = $field->getRelationModel();
						}
					}
				}
				$prefix = implode('.', array_slice($fieldpath, 0, count($fieldpath)-1));
			} else {
				$field = $object->_fields[$fieldname];
			}
			return array($field, $object, $prefix);
		}

		public function findField($fieldname, $object = null) {
			$fo = $this->findFieldAndObject($fieldname, $object);
			return $fo[0];
		}

		public function formatField($object, $fieldname, $forcestring = false) {
			$output = '';
			if ($object && $fieldname) {
				$fo = $this->findFieldAndObject($fieldname, $object);
				$field = $fo[0];
				$fieldname = $field->field;
				$object = $fo[1];
				if ($field instanceof RelationSetField) {
					$rset = $field->getRelation($object);
					foreach ($rset as $rel) {
						$output .= $this->getAdmin()->getApplication()->toString($rel);
						if (!$forcestring) $output .= '<br />';
						$output .= "\n";
					}
					if (!$output) {
						$output = '<i>None</i>';
						if (!$forcestring) $output .= '<br />';
						$output .= "\n";
					}
				} else {
					$ma = null;
					if ($field instanceof RelationField) {
						$ma = $this->getAdmin()->findModelAdmin($field->relationName);
						if ($ma && $ma->checkAccess()) {
							$action = $ma->checkPermission('MODIFY') ? 'edit' : 'view';
							$output .= '<a href="'.$ma->relativeUrl('/'.$action.'/'.$object->$fieldname->pk).'">';
						}
					}
					if ($forcestring) {
						$output .= $field->toString($object->$fieldname);
					} else {
						$output .= $field->toHTML($object->$fieldname);
					}
					if ($ma && $ma->checkAccess())
						$output .= '</a>';
				}
			}
			return $output;
		}

		public function checkAccess($instance = null) {
			if ($this->module->master
					&& !$this->isMaster()
					&& !$this->module->admin->getFilter($this->module->master->queryName))
				return false;
			return $this->module->checkAccess($this->permission, $instance);
		}
	
		public function checkAction($action) {
			if ($action != 'VIEW' && $this->readonly)
				return false;
			return $this->module->checkAction($action, $this->permission);
		}
	
		public function checkPermission($action, $instance = 'ALL') {
			if ($action != 'VIEW' && $this->readonly)
				return false;
			return $this->module->checkPermission($this->permission, $action, $instance);
		}
	
		public function renderTemplate($template, $context = null, $basetemplate = null) {

			$base = $this->templateContext->getTemplate(
				$basetemplate ? $basetemplate : 'base.tpl');

			if (!$context)
				$context = array();

			$context['_template'] = $template;

			$base->render($context);

		}

		public function fetchTemplate($template, $context = null) {
			$tpl = $this->templateContext->getTemplate($template);
			return $tpl->fetch($context);
		}

		public function relativeRedirect($url) {
			$this->module->relativeRedirect('/'.$this->id.$url);
		}

		public function relativeUrl($url) {
			return $this->module->relativeUrl('/'.$this->id.$url);
		}

		public function getQueryName() {
			return $this->queryName;
		}

		public function getQuery($model = null) {
			if ($model) {
				$q = $this->module->getQuery($model);
				// Force required filters
				$requiredFilter = $this->module->requiredFilter;
				if ($requiredFilter) {
					if ($filterid = $this->getAdmin()->getFilter($requiredFilter))
						foreach ($q->model->_fields as $n => $f)
							if ($f instanceof RelationField && $f->relationName == $requiredFilter)
								$q = $q->filter($n, $filterid);
				}
				return $q;
			} else {
				if (!$this->query) {
					$user = $this->getUser();
					$query = $this->getPrototype()->getQuery();
					if ($modelAccess = $this->getModelAccess())
						foreach ($modelAccess as $field => $value)
							if ($value)
								$query = $query->filter($field, $value);
					if (count($this->fieldRestrictions))
						foreach ($this->fieldRestrictions as $f => $r)
							if ($r[0] !== CHANGE_ONLY && !$this->checkPermission($r[1]))
								$query = $query->exclude($f, $r[0]);

					$master = $this->module->master;
					$requiredFilter = $this->module->requiredFilter;
					$filteredMaster = false;

					if ($master && $master->getQueryName() != $this->getQueryName()) {
						if ($master && $filterid = $this->getAdmin()->getFilter($master->getQueryName()))
							foreach ($this->getPrototype()->_fields as $n => $f)
								if ($f instanceof RelationField && $f->relationName == $master->getQueryName()) {
									$filteredMaster = true;
									$query = $query->filter($n, $filterid);
								}
					}

					if (!$filteredMaster && $requiredFilter) {
						if ($filterid = $this->getAdmin()->getFilter($requiredFilter))
							foreach ($this->getPrototype()->_fields as $n => $f)
								if ($f instanceof RelationField && $f->relationName == $requiredFilter)
									$query = $query->filter($n, $filterid);
					}

					if (count($this->queryFilter))
						foreach ($this->queryFilter as $f => $v)
							$query = $query->filter($f, $v);

					$this->query = $query;

				}
				return $this->query;
			}
		}

		public function getAndUpdate($id, $params, $flags = 0, $preloadRelations = false) {
			$model = null;
			$m2m = array();

			try {
				if ($id) {
					$query = $this->getQuery();
					if ($pl = $this->getMergeFields()) {
						foreach ($pl as $pf)
							$query = $query->preload($pf);
					}
					$model = $query->get($id);
				} else {
					$model = $this->getQuery()->queryhandler->create($params, null, $flags);
				}
			} catch(DoesNotExistException $dne) {
				$model = $this->getQuery()->queryhandler->create($params, null, $flags);
			}

			// Enforce access
			$modelAccess = $this->getModelAccess();
			$master = $this->module->master;
			$requiredFilter = $this->module->requiredFilter;
			$filteredMaster = false;

			foreach ($model->_fields as $field => $def) {
				if ($access = $this->getMatchedFields($field)) {
					$path = explode('.', $access);
					$relation = $this->getUser();
					foreach ($path as $rel)
						$relation = $relation->$rel;
					if ($relation) {
						if (!$id)
							$model->$field = $relation->pk;
						$def->options['editable'] = false;
					}
				}

				if (isset($this->fieldRestrictions[$field]) && $restricted = $this->fieldRestrictions[$field]) {
					if (!$this->checkPermission($restricted[1])) { 
						unset($params[$field]);
						$def->options['editable'] = false;
					}
				} elseif (isset($modelAccess[$field])) {
					$params[$field] = $modelAccess[$field];
					$def->options['editable'] = false;
				}

				if ($master != $this) {
					if ($def instanceof RelationField && $master && $def->relationName == $master->getQueryName()) {
						$filteredMaster = true;
						if ($filterid = $this->getAdmin()->getFilter($master->getQueryName())) {
							if ($def instanceof ManyToOneField) {
								$params[$field] = $filterid;
								$def->options['editable'] = false;
							} elseif ($def instanceof ManyToManyField) {
								$rq = $this->getAdmin()->findModelAdmin($def->relationName)->getQuery();
								$ro = $rq->get($filterid);
								$m2m[$field] = $ro;
							}
						}
					}
				}

				if (!$filteredMaster && $requiredFilter) {
					if ($def instanceof ManyToOneField && $def->relationName == $requiredFilter) {
						if ($filterid = $this->getAdmin()->getFilter($requiredFilter)) {
							$params[$field] = $filterid;
							$def->options['editable'] = false;
						}
					}
				}

				// Set default value for base filter fields
				if ($baseFilter = $this->getAdmin()->getBaseFilter()) {
					if ($def instanceof ManyToOneField && $def->relationName == $baseFilter->getQueryName()) {
						if ($filterid = $this->getAdmin()->getFilter($baseFilter->getQueryName())) {
							$params[$field] = $filterid;
						}
					}
				}

				if (count($this->queryFilter)) {
					foreach ($this->queryFilter as $f => $v)
						if ($f == $field) {
							$params[$f] = $v;
							$def->options['editable'] = false;
						}
				}

			}

			// Prevent boolean fields from getting reset if they aren't
			// in the current form view
			if ($flags & UPDATE_FORCE_BOOLEAN) {
				$displayedFields = $this->getFlattenedFieldGroups();
				foreach ($model->_fields as $n => $f)
					if ($f instanceof BooleanField)
						if (!in_array($n, $displayedFields)) {
							if ($model->pk)
								$params[$n] = $model->$n;
							else
								$params[$n] = $f->getDefaultValue();
						}
			}

			$model->updateModel($params, null, $flags);
			if (count($m2m) && $preloadRelations) {
				// Pre-add many-to-many fields that are active filters
				if (!$model->pk)
					foreach ($m2m as $f => $v)
						$model->$f->preload(array($v));
			}
			return $model;
		}

		public function getRelatedQuery($field, $object) {
			$relation = $field->relationName;
			$targetadmin = $this->getAdmin()->findModelAdmin($relation);
			$query = $targetadmin ? $targetadmin->getQuery() : $field->getRelationModel()->getQuery();
			if (isset($field->options['filter']) && $filter = $field->options['filter']) {
				$final = array();
				foreach ($filter as $name => $value) {
					if ($value instanceof ModelField)
						$value = $object[$value->field];
					$final[$name] = $value;
				}
				$query = $query->filter($final);
			}
			return $query;
		}

		public function getManyToManyList($field, $object) {

			$available = array();
			$idField = $field->getRelationModel()->_idField;

			$selQuery = $field->getJoinModel()->getQuery();
			$selected = $selQuery->filter($field->joinField, $object->pk)->values($field->targetField);
			//$selected = $object[$field->field]->values($idField);
			$selected = $selected[$field->targetField];

			$query = $this->getQuery($field->relationName);
			if (isset($field->options['filter']) && ($filter = $field->options['filter'])) {
				$final = array();
				foreach ($filter as $name => $value) {
					if ($value instanceof ModelField)
						$value = $object[$value->field];
					$final[$name] = $value;
				}
				$query = $query->filter($final);
			}

			if (isset($this->fieldOptions[$field->field]['groupby']))
				$query = $query->order($this->fieldOptions[$field->field]['groupby']);

			if (isset($field->options['allowNull']))
				$available[] = array('object' => $query->queryhandler->create(array(
					'name' => isset($this->fieldOptions[$field->field]['nullLabel'])
						? $this->fieldOptions[$field->field]['nullLabel']
						: 'None'
					)), 'selected' => in_array(null, (array)$selected)
						|| (!$object->pk && $this->fieldOptions[$field->field]['nullDefault']));
			$all = $query->cursor();
			if ($all->count())
				foreach ($all as $rel)
					$available[] = array('object' => $rel, 'selected' => in_array($rel->pk, (array)$selected));
			return $available;
		}

		public function getAdmin() {
			return $this->module->admin;
		}

		public function getDependencyHTML($field, $popup) {
			$html = '';
			if ($field instanceof RelationField) {
				$targetadmin = $this->getAdmin()->findModelAdmin($field->relationName);
				if ($targetadmin) {
					if ($targetadmin->checkPermission('CREATE') && !$popup) {
						$url = $targetadmin->relativeUrl('/addpopup/');
						$html = '<a title="Add new '.strtolower($field->name).'" href="" onclick="popupField=\''.$field->field.'\';popup(\''.$url.'\', 700, 700); return false;"><img src="'.$this->getAdmin()->getMediaRoot().'/images/blue_add.gif"/></a>';
					}
					$html .= $this->getRelatedJavascript($field, $targetadmin);
				}
			}
			return $html;
		}
		public function getRelatedJavascript($field, $modeladmin) {
			$script = '';
			if ($modeladmin && isset($field->options['filter']) && $filter = $field->options['filter']) {
				foreach ($filter as $name => $value) {
					if ($value instanceof ModelField) {
						if (!$script) $script = '<script language="javascript">';
						$script .= 'addFieldNotifier(\''.$value->field.'\', fieldListCallback(\''.$modeladmin->relativeUrl('/listupdatejs').'\', \''.$name.'\', \''.$field->field.'\'));';
					}
				}
				if ($script) $script .= '</script>';
			}
			return $script;
		}

		public function getDisplayJavascript($fieldName) {

			$script = '';

			if (isset($this->fieldOptions[$fieldName])
					&& isset($this->fieldOptions[$fieldName]['displayConditions'])) {

				$script = '<script language="javascript">'."\n";

				$first = true;
				$values = '[';
				foreach ($this->fieldOptions[$fieldName]['displayConditions'] as $cfld => $cval) {
					if (!is_array($cval))
						$cval = array($cval);
					foreach ($cval as $val) {
						if (!$first)
							$values .= ',';
						$values .= '[\''.$cfld.'\', \''.$val.'\']';
						$first = false;
					}
				}
				$values .= ']';

				foreach ($this->fieldOptions[$fieldName]['displayConditions'] as $cfld => $cval) {
					if (!is_array($cval))
						$cval = array($cval);
					$script .= 'addFieldNotifier(\''.$cfld.'\', function(name, value) { ';
					$script .= 'checkField(\''.str_replace('.', '__', $fieldName).'\', '.$values.');';
					$script .= " });\n";
				}

				$script .= '</script>';

			}

			return $script;

		}

		public function checkDisplayCondition($object, $fieldName) {
			if (isset($this->fieldOptions[$fieldName])
					&& isset($this->fieldOptions[$fieldName]['displayConditions'])) {
				// TODO: need and/or conditions
				$display = false;
				foreach ($this->fieldOptions[$fieldName]['displayConditions'] as $cfld => $cval) {
					if (!is_array($cval))
						$cval = array($cval);
					foreach ($cval as $val) {
						list($rfld, $robj, $pfx) = $this->findFieldAndObject($cfld, $object);
						if ($robj[$rfld->field] == $val)
							$display = true;
					}
				}
				return $display;
			}
			return true;
		}

		public function getUser() {
			return $this->module->getUser();
		}

		public function getId() {
			return $this->id;
		}

		public function getName() {
			if (!$this->name)
				$this->setName($this->spacify($this->modelClass));
			return $this->name;
		}

		public function getPlural() {
			if (!$this->pluralName)
				$this->pluralName = $this->getAdmin()->getApplication()->pluralize($this->getName());
			return $this->pluralName;
		}

		public function getPaginated() {
			return $this->paginated;
		}

		public function getModule() {
			return $this->module;
		}

		public function isFilter() {
			return $this->isMaster() || $this->getAdmin()->getBaseFilter() == $this;
		}

		public function isMaster() {
			return $this->module && $this->module->master == $this;
		}

		public function matchesMaster() {
			$master = $this->module->master;
			return $master && $master->getQueryName() == $this->getQueryName();
		}

		public function getPermission() {
			return $this->permission;
		}

		public function getPrototype() {
			if (!$this->prototype)
				$this->prototype = $this->getAdmin()->getApplication()->query->get($this->modelClass)->model;
			return $this->prototype;
		}

		public function setId($id) {
			$this->id = $id;
		}

		public function setName($name, $plural = null) {
			$this->name = $name;
			if ($plural)
				$this->pluralName = $plural;
			else
				$this->pluralName = $this->getAdmin()->getApplication()->pluralize($name);
		}

		public function setPlural($name) {
			$this->pluralName = $name;
		}

		public function setPaginated($paginated = true) {
			$this->paginated = $paginated;
		}

		public function setSortable($field) {
			$this->sortable = $field;
			$this->paginated = false;
		}

		public function setDescription($description) {
			$this->description = $description;
		}

		public function setPreviewUrl($url) {
			$this->previewUrl = $url;
		}

		public function setDownloadUrl($url) {
			$this->downloadUrl = $url;
		}

		public function setInstructions($text) {
			$this->instructions = $text;
		}

		public function setReadOnly($readonly) {
			$this->readonly = $readonly;
		}

		public function setInlineOnly($inlineOnly) {
			$this->inlineOnly = $inlineOnly;
		}

		public function setIcon($icon) {
			if ($icon{0} == '/')
				$this->icon = $icon;
			else
				$this->icon = $this->getAdmin()->baseUrl.$icon;
		}

		public function addContext($name, $value) {
			$this->templateContext->context[$name] = $value;
		}

		public function addQueryFilter($field, $value) {
			$this->queryFilter[$field] = $value;
		}

		public function addFieldRestriction($field, $value, $permission) {
			$this->fieldRestrictions[$field] = array($value, $permission);
			//if (!$this->checkPermission($permission))
				//$this->getPrototype()->_fields[$field]->options['editable'] = false;
		}

		public function setFieldTemplate($field, $template, $context = null) {
			$this->fieldTemplates[$field] = array($template, $context);
		}

		public function getFieldTemplate($field) {
			if (array_key_exists($field, $this->fieldTemplates))
				return $this->fieldTemplates[$field][0];
			return null;
		}

		public function getFieldContext($field) {
			if (array_key_exists($field, $this->fieldTemplates))
				return $this->fieldTemplates[$field][1];
			return null;
		}

		public function setDisplayFields($fields) {
			$this->displayFields = $fields;
		}

		public function setFieldName($field, $name) {
			$this->fieldNames[$field] = $name;
		}

		public function getDisplayFields() {
			if ($this->displayFields) {
				$master = $this->module->master;
				$skip = array();
				// Always omit master filter field if specified
				if ($master) {
					foreach ($this->displayFields as $f) {
						$field = $this->findField($f);
						if ($field instanceof RelationField) {
							$r = $field->getRelationModel();
							if ($r === $master->getPrototype()) {
								$skip[] = $f;
								break;
							}
						}
					}
				}
				return array_diff($this->displayFields, $skip);
			}
			return null;
		}

		public function setSearchFields($fields) {
			$this->searchFields = $fields;
		}

		public function getSearchFields() {
			return $this->searchFields;
		}

		public function setFilterFields($fields) {
			$this->filterFields = $fields;
		}

		public function getFilterFields() {
			return $this->filterFields;
		}

		public function setCustomFilter($field, $filter) {
			$this->customFilters[$field] = $filter;
		}

		/**
		 * Configures form field grouping and display options for
		 * field groups.
		 *
		 * Example of the format of the $groups parameter:
		 * <code>
		 * array(
		 * 	array('name' => null,
		 *		'fields' => array('title', 'content', 'type')),
		 * 	array('name' => 'Form Action',
		 *		'fields' => array('action'),
		 *		'displayConditions' => array('type' => 'FORM')),
		 * 	array('name' => 'SEO Fields',
		 *		'fields' => array('meta_keywords', 'meta_description'),
		 *		'collapse' => true),
		 *	)
		 * </code>
		 *
		 * Group options:
		 * <ul>
		 * 	<li><i>name</i>: The group heading, or null for no heading.</li>
		 * 	<li><i>fields</i>: An array of field names that belong to
		 *		the group.</li>
		 * 	<li><i>collapse</i>: Displays the field group as a single line
		 * 		initially, that can be expanded with a click.</li>
		 * 	<li><i>displayConditions</i>: Defines rules that determine when
		 *		this field group should be displayed.  The value of this
		 *		option is a <i>field => value</i> array.  When the current
		 *		form value for <i>field</i> is equal to <i>value</i>, the
		 *		field will be displayed; otherwise it will be hidden.  This
		 *		behavior is dynamic; fields will be shown and hidden via
		 *		Javascript as form values change.</li>
		 * </ul>
		 *
		 * @param $groups The field grouping definitions (see above).
		 */
		public function setFieldGroups($groups) {
			$this->fieldGroups = $groups;
			$idx = 1;
			foreach ($groups as $g) {
				if (isset($g['displayConditions']))
					$this->setFieldOption('_group'.$idx, 'displayConditions', $g['displayConditions']);
				$idx++;
			}
		}

		public function getFieldGroups() {
			if (!$this->fieldGroups) {
				$groups = array(array('name' => null, 'fields' => array()));
				foreach ($this->getPrototype()->_fields as $name =>$field)
					if (!$this->inlineObjects || !array_key_exists($name, $this->inlineObjects))
						$groups[0]['fields'][] = $name;
				return $groups;
			}
			return $this->fieldGroups;
		}

		public function getFlattenedFieldGroups() {
			$flattened = array();
			$groups = $this->getFieldGroups();
			foreach ($groups as $group)
				$flattened = array_merge($flattened, $group['fields']);
			return $flattened;
		}

		public function isFieldDisplayed($field) {
			$groups = $this->getFieldGroups();
			foreach ($groups as $group)
				foreach ($group['fields'] as $f)
					if ($f == $field && !$this->isAccessFiltered($f))
						return true;
			return false;
		}

		public function setModelAccess($field, $userpath = null) {
			$this->modelAccess[$field] = $userpath;
		}

		public function getModelAccess() {

			if (!$this->resolvedModelAccess) {
				$access = array();
				$user = $this->getUser();
				foreach ($this->modelAccess as $field => $userpath) {
					$relation = $user;
					$relmodel = null;
					if ($userpath) {
						$path = explode('.', $userpath);
						foreach ($path as $rel) {
							$relmodel = $relation->_fields[$rel]->relationName;
							$relation = $relation->$rel;
						}
					}
					if ($relmodel == $this->module->requiredFilter)
						$access[$field] = $this->module->admin->getFilter($relmodel);
					elseif ($relation && !$this->checkPermission('ADMIN'))
						$access[$field] = $relation->pk;
				}
				$this->resolvedModelAccess = $access;
			}

			return $this->resolvedModelAccess;

		}

		public function isAccessFiltered($field) {

			$access = $this->getModelAccess();
			return isset($access[$field]);

		}

		public function setMatchedField($field, $usermatch) {
			$this->matchedFields[$field] = $usermatch;
		}

		public function getMatchedFields($field) {
			if (array_key_exists($field, $this->matchedFields))
				return $this->matchedFields[$field];
			return null;
		}

		public function mergeWith($field, $options = null) {
			if (!$this->mergeObjects)
				$this->mergeObjects = array();
			if ($this->getPrototype()->_fields[$field] instanceof OneToOneField)
				$this->mergeObjects[$field] = $options;
			else
				throw new InvalidParametersException('mergeWith(): Field "'.$field.'" is not a OneToOneField.');
		}

		public function getMergeFields() {
			return array_keys((array)$this->mergeObjects);
		}

		public function getPreloadFields() {
			return array_unique(array_merge(
				array_keys((array)$this->mergeObjects),
				array_keys((array)$this->inlineObjects)
				));
		}

		public function addInlineObject($name, $options = null) {
			if (!$this->inlineObjects)
				$this->inlineObjects = array();
			$this->inlineObjects[$name] = $options;
		}

		public function isInlineObject($field) {
			return ($this->inlineObjects && array_key_exists($field, $this->inlineObjects));
		}

		public function getOrderField($model) {
			foreach ($model->_fields as $f) {
				if ($f instanceof OrderedField && sizeof($f->groupBy) == 1) {
					$gb = $f->groupBy;
					$gf = $model->_fields[$gb[0]];
					if ($gf && $gf instanceof RelationField && $gf->getRelationModel() === $this->getPrototype())
						return $f;
				}
			}
			return null;
		}

		public function setFieldOption($field, $option, $value) {
			if (!is_array($this->fieldOptions))
				$this->fieldOptions = array();
			if (!array_key_exists($field, $this->fieldOptions))
				$this->fieldOptions[$field] = array();
			$this->fieldOptions[$field][$option] = $value;
		}

		public function setFieldOptions($name, $options) {
			if (!is_array($this->fieldOptions))
				$this->fieldOptions = array();
			$this->fieldOptions[$name] = $options;
		}

		public function setDeleteConfirm($confirm) {
			$this->deleteConfirm = $confirm;
		}

		public function getFieldName($field) {

			if (isset($this->fieldNames[$field]))
				return $this->fieldNames[$field];

			$fieldobj = $this->findField($field);
			if ($fieldobj)
				return $fieldobj->name;

			return null;

		}

		public function getFieldValueHtml($object, $field, $format = 'html', $link = false, $default = null) {

			if (!is_object($field) && $object->isExtraField($field)) {
				return $object->getFieldValue($field);
			}

			$targetObject = $object;
			$targetField = $field;

			if (!is_object($field))
				list($targetField, $targetObject, $prefix) = $this->findFieldAndObject($field, $object);

			$fieldName = $targetField->field;

			$value = '';
			if ($link) {
				$value = '<a href="';
				if ($this->checkPermission('MODIFY'))
					$value .= $this->relativeUrl('/edit/');
				else
					$value .= $this->relativeUrl('/view/');
				$value .= $targetObject->pk . '">';
			}

			if (!$targetObject[$fieldName]) {
				if (isset($targetField->options['options'])
						&& isset($targetField->options['options'][null]))
					$value .= $targetField->options['options'][null];
				elseif ($targetField instanceof BooleanField)
					$value .= 'No';
				else
					$value .= $default;
			} else
				$value .= $this->formatField($targetObject, $fieldName, $format == 'text');

			if ($link)
				$value .= '</a>';

			return $value;

		}

		public function getFieldInputHtml($object, $field, $name = null, $cssClass = null, $title = null, $inline = null, $position = 0) {

			$targetObject = $object;
			$targetField = $field;

			if (!is_object($field))
				list($targetField, $targetObject, $prefix) = $this->findFieldAndObject($field, $object);

			$fieldName = $name ? $name : $targetField->field;

			$inputName = str_replace('.', '__', $fieldName);
			$template = $this->getFieldTemplate($fieldName);
			if (!$template)
				$template = $targetField->type;

			return $this->fetchTemplate('fields/'.$template.'.tpl', array(
					'object' => $targetObject,
					'objectPos' => $position,
					'field' => $targetField,
					'inputTitle' => $title,
					'inputClass' => $cssClass,
					'inlineField' => $inline,
					'fieldName' => $inputName,
					'fieldRef' => $fieldName,
					'fieldContext' => $this->getFieldContext($fieldName)
				));
		}

		public function getFieldRowHtml($object, $field, $name = null, $parent = null, $pk = null) {

			$modeladmin = $this;
			$orig = $object;

			if (!is_object($field))
				list($field, $object, $prefix) = $this->findFieldAndObject($field, $object);
			
			$fieldName = $field->field;
			if ($orig !== $object)
				$modeladmin = $this->getAdmin()->findModelAdmin($object);

			if ((isset($field->options['editable']) && $field->options['editable'])
					|| (isset($field->options['readonly']) && $field->options['readonly']))
				return $this->fetchTemplate('object_field.tpl', array(
						'modeladmin' => $modeladmin,
						'object' => $object,
						'parent' => $parent ? $parent : $object,
						'field' => $field,
						'fieldErrors' => $object->getFieldErrors(),
						'fieldName' => $name ? $name : $fieldName
					));

			return null;

		}

		public function addTypeAdmin($modelClass, $admin) {

			if (!$this->subtypeAdmins)
				$this->subtypeAdmins = array();

			$this->subtypeAdmins[$modelClass] = $admin;

		}

		public function getTypeAdmin($model) {

			if ($model && $this->subtypeAdmins && count($this->subtypeAdmins) > 0
					&& isset($this->subtypeAdmins[get_class($model)])) {
				$typeAdmin = $this->subtypeAdmins[get_class($model)];
				if ($typeAdmin)
					return $typeAdmin;
			}

			return $this;

		}

		public function flush() {
			$this->query = null;
			$this->resolvedModelAccess = null;
		}

	}
