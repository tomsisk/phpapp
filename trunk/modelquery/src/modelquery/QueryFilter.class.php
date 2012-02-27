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

	require_once('Exceptions.php');
	require_once('CursorIterator.class.php');

	/** Special value representing all fields in a model */
	define('ALLFIELDS', 'ALLFIELDS');

	/**
	 * Core class for query creation in ModelQuery.
	 *
	 * All queries constructed with ModelQuery use a chain of one or
	 * more QueryFilter objects.
	 * 
	 * <b>Basic Usage Examples</b>
	 * <code>
	 * // Get the User QueryHandler object (which is also a QueryFilter) from our QueryFactory
	 * $uq = $qf->get('User');
	 * 
	 * // Get all users
	 * $users = $uq->all();
	 * 
	 * // Get all users, sorted by username
	 * $users = $uq->order('username');
	 * 
	 * // Get all verified users
	 * $users = $uq->filter('verified', true);
	 * 
	 * // Delete all unverified users created before Jan 1
	 * $uq->filter('verified', false, 'created:lte', mktime(0,0,0,1,1,2008))->delete();
	 * 
	 * // Get an array of emails for all unverified users with non-null email addresses
	 * $values = $uq->filter('verified', true, 'email:isnull', false)->values('email');
	 * 
	 * // Get a list of all users with gmail.com email addresses
	 * $users = $uq->filter('email:endswith', '@gmail.com');
	 * 
	 * // Get all unverified users except those in group 5
	 * $values = $uq->filter('verified', false)->exclude('group', 5);
	 * 
	 * // Set all users in group 5 to verified
	 * $uq->filter('group', 5)->update(array('verified', true));
	 * 
	 * // Get the current user count
	 * $count = $uq->count();
	 * </code>
	 * 
	 * <b>Filter Chaining</b>
	 * 
	 * You will notice that except for methods that return immediate
	 * query results (hash(), select(), raw(), delete(), get(), etc),
	 * most filtering methods in this class (filter(), exclude(),
	 * slice(), order(), etc) return a new QueryFilter object that
	 * chains to the one it was called from, without altering its
	 * original state or executing a query against the database.
	 * Chaining filters in this way allows you to branch in several
	 * different directions with a query without having to start
	 * over again.
	 * 
	 * <code>
	 * // Base query is limited to only users in group 5 
	 * $query = $uq->filter('group', 5);
	 * 
	 * // Get total number of users in group 5
	 * $total = $query->count();
	 * 
	 * // ...but only load the first 10
	 * $users = $query->slice(10);
	 * </code>
	 * 
	 * <b>Accessing the Result Set</b>
	 * 
	 * As mentioned above, most of the filter methods return a QueryFilter object,
	 * but no query is actually executed against the database yet. So how do we get
	 * the actual query results?
	 * 
	 * Just start accessing it as a regular PHP array. As soon as you call it
	 * in an array context (either as part of a foreach loop, or by trying to directly
	 * access an array index on it), the underlying query is executed on the database
	 * and the result set is created.
	 * 
	 * <code>
	 * // Filter applied, but no query execution yet
	 * $users = $uq->filter('group', 5);
	 * 
	 * // foreach loop forces query execution
	 * foreach ($users as $user) {
	 * 
	 *     // Like QueryFilter, Model instances can also be accessed as objects or arrays:
	 *     echo $user->username;
	 *     // ... is equivalent to:
	 *     echo $user['username'];
	 * 
	 * }
	 * </code>
	 * 
	 * <b>Cursors</b>
	 * 
	 * If you have too many rows to load in memory, you can also use a database
	 * cursor:
	 * 
	 * <code>
	 * // Get a CursorIterator object for this query
	 * $users = $uq->filter('group', 5)->cursor();
	 * 
	 * // CursorIterator can also be used normally as an array, but it keeps the database
	 * // connection open and loads rows as needed:
	 * foreach ($users as $user) {
	 *     // Do something
	 * }
	 * 
	 * // Manually close down the cursor
	 * $users->close();
	 * </code>
	 * 
	 * @package modelquery
	 * @see QueryHandler
	 * @see CursorIterator
	*/
	class QueryFilter implements ArrayAccess, Iterator, Countable {

		/** The QueryHandler object that created this query */
		public $queryhandler;
		/** Backwards-compatible reference  to queryhandler - deprecated */
		public $modelquery;
		/** The Model prototype to clone for creating results */
		public $model;
		/** The QueryFactory associated with this query */
		public $factory;
		/** The main database table to query against */
		public $tableName;

		/** The next filter up the filter chain */
		protected $parentFilter;
		/** Filter details for this chain of the query */
		protected $query;
		/**
		 * Merged filter details from all filters above this one in the
		 * filter chain. Cached on first call to QueryFilter::mergedQuery().
		 */
		protected $fullQuery;

		private $models = null;

		/**
		 * Create a new QueryFilter object.
		 * 
		 * This should not be called by non-ModelQuery code; it is called
		 * internally for creating filter chains.
		 * 
		 * @param QueryHandler &$queryhandler_ The QueryHandler that created this query
		 * @param QueryFilter &$parentFilter_ The QueryFilter object that created this filter
		 */
		public function __construct(&$queryhandler_, &$parentFilter_ = null) {
			$this->queryhandler =& $queryhandler_;
			// Backwards compatibility
			$this->modelquery =& $queryhandler_;
			// Cache some QueryHandler properties
			$this->model =& $queryhandler_->model;
			$this->factory =& $queryhandler_->factory;
			$this->tableName =& $queryhandler_->model->_table;

			$this->parentFilter =& $parentFilter_;

			$this->query = array('distinct' => false,
								'select' => array(),
								'filters' => array(),
								'aggregates' => array(),
								'insert' => array(),
								'where' => array(),
								'tables' => array(),
								'order' => null,
								'limit' => 0,
								'offset' => 0,
								'preload' => array(),
								'groupby' => array(),
								'converters' => array(),
								'debug' => false);
		}

		/**
		 * Passthrough filter that returns all models.
		 * 
		 * Useful for calling on the root QueryHandler object to retrieve all
		 * models, since you cannot call select() or hash() on it directly.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &all() {
			$filter = new QueryFilter($this->queryhandler, $this);
			return $filter;
		}

		/**
		 * Filter models by a field value.
		 * 
		 * <p>This method takes an arbitrary number of parameters, but they
		 * must come in pairs, such as:</p>
		 *
		 * <p><code>
		 * $uq->filter("username:exact", "jjongsma")
		 * $uq->filter("verified", true, "created:gte", mktime(0,0,0,1,1,2008));
		 * </code></p>
		 * 
		 * <p>The first parameter of a pair consists of a field name, and an
		 * optional modifier separated by a colon (:).  The second parameter
		 * of a pair is the field value to filter by.  The type of the second
		 * parameter depends on the field type and the modifier used.  For
		 * example, for a date field, you would pass in a numeric timestamp.
		 * For an :in modifier (see below), you would pass in an array of values
		 * as the second parameter.  If no modifier is specified, "exact" is
		 * assumed.</p>
		 * 
		 * <p>If multiple pairs of parameters are given, records must match
		 * ALL of the filters specified.</p>
		 * 
		 * <p><b>Available modifiers:</b><br />
		 * <i>exact</i>: Match records exactly against the given field value<br />
		 * <i>iexact</i>: Match records exactly against the given field value, case-insensitive<br />
		 * <i>contains</i>: Match records that contain the given value anywhere in the field<br />
		 * <i>icontains</i>: Match records that contain the given value anywhere in the field, case-insensitive<br />
		 * <i>gt</i>: Match records that have a value greater than the given value<br />
		 * <i>gte</i>: Match records that have a value greater than or equal to the given value<br />
		 * <i>lt</i>: Match records that have a value less than the given value<br />
		 * <i>lte</i>: Match records that have a value less than or equal to the given value<br />
		 * <i>in</i>: Match records that have a value that matches any value in the given array<br />
		 * <i>startswith</i>: Match records with a value that starts with the given string<br />
		 * <i>istartswith</i>: Match records with a value that starts with the given string, case-insensitive<br />
		 * <i>endswith</i>: Match records with a value that ends with the given string<br />
		 * <i>iendswith</i>: Match records with a value that ends with the given string, case-insensitive<br />
		 * <i>range</i>: Match records within the specified range (given as a 2-item array), inclusive<br />
		 * <i>isnull</i>: Match records with null values (if parameter is TRUE) or non-null values (if FALSE)</p>
		 * 
		 * <p>In addition to filtering by fields that are part of the original model,
		 * you can also filter by related objects.  For example, if the User model
		 * has a ManyToOneField called "preferences", you could filter on:</p>
		 * 
		 * <code>
		 * $uq->filter('preferences.homepage', '/news/finance.php');
		 * </code>
		 * 
		 * <p>This would filter on the "homepage" field belonging to the Preferences model
		 * linked to each User record.  You can follow relations as far as they go using
		 * this dot notation, not just a single level; QueryFilter will add all the
		 * necessary joins.</p>
		 * 
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &filter() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$args = func_get_args();
			$filter->applyFilter($this->getFilters($args));
			return $filter;
		}

		/**
		 * Filter models by a field value.
		 *
		 * Behaves like QueryFilter::filter(), but if multiple pairs of parameters
		 * are given, records only have to match ONE of the filters specified.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::filter()
		 */

		public function &filteror() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$args = func_get_args();
			$filter->applyFilterOr($this->getFilters($args));
			return $filter;
		}

		/**
		 * Filter models by excluding a field value.
		 * 
		 * Behaves like QueryFilter::filter(), except instead of matching
		 * against the given field values, it excludes any records that match
		 * the field value.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::filter()
		 */
		public function &exclude() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$args = func_get_args();
			$filter->applyExclude($this->getFilters($args));
			return $filter;
		}

		/**
		 * Filter models by excluding a field value.
		 * 
		 * Behaves like QueryFilter::exclude(), but if multiple pairs of parameters
		 * are given, records only have to match ONE of the filters specified to
		 * be excluded.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::filter()
		 */
		public function &excludeor() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$args = func_get_args();
			$filter->applyExcludeOr($this->getFilters($args));
			return $filter;
		}

		/** Parse function argument list to split into field/value filter pairs. */
		private function getFilters($args) {
			if (sizeof($args) == 1 && is_array($args[0])) {
				$filters = array();
				$keys = array_keys($args[0]);
				foreach ($keys as $key) {
					$filters[] = $key;
					$filters[] = $args[0][$key];
				}
				return $filters;
			} else
				return $args;
		}

		/**
		 * Adds extra fields from related tables to the results
		 * of this query, to reduce additional queries.
		 *
		 * The parameter is of the form "reference_name" => "field_name",
		 * where reference_name is the name of the field as it will be
		 * named in the query result object, and field_name is the
		 * original field name (or path, if you are pulling in a field
		 * from a related object).
		 *
		 * <code>
		 * $user = $uq->extra(array('homepage' => 'preferences.homepage'));
		 * echo $user->homepage:
		 * </code>
		 *
		 * While the original user object did not have a "homepage"
		 * field, the query results now include the value of that
		 * field from the related "preferences" object.  This value
		 * could also be retrieved with $user->preferences->homepage,
		 * but it would require an additional database query since
		 * the entire preferences object would be loaded on demand.
		 *
		 * @param Array $select A hash array of additional fields to include.
		 * @param string $where <i>Deprecated, use QueryFilter::condition()</i>.
		 * @param Array $params <i>Deprecated, use QueryFilter::condition()</i>.
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &extra($select = null, $where = null, $params = null) {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyExtra($select, $where, $params);
			return $filter;
		}

		/**
		 * Add a more complex conditional expression to the query that
		 * is not limited to one field.
		 *
		 * <code>
		 * $query->condition('close > open');
		 * </code>
		 *
		 * The expression is parsed to evaluate dot-notation paths to
		 * related objects, so conditions are not just limited to fields
		 * available on the current model.
		 *
		 * <code>
		 * $query->condition('current.price > previous.price');
		 * </code>
		 *
		 * If the condition contains variable parameters that may required
		 * escaping, use the "?" token as a placeholder, and pass the
		 * associated variable values as an array in the second parameter.
		 *
		 * <code>
		 * $query->condition('price > earnings * ?', array($peratio));
		 * </code>
		 *
		 * Simple ternary expressions can also be used, in the form
		 * test ! value-if-true : value-if-false:
		 *
		 * <code>
		 * $query->condition('price > earnings ! 1 : 0')
		 * </code>
		 * 
		 * @param string $expression The conditional expression.
		 * @param Array $params An array of parameters to bind to the query.
		 *		if any are specified in the $expression parameter (using the ? token).
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function condition($expression, $params = null) {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyCondition($expression, $params);
			return $filter;
		}


		/**
		 * Manually join tables to this query.
		 *
		 * This is only intended for internal ModelQuery usage, or
		 * as a last resort for custom `condition()` clauses that
		 * work with fields not defined in a Model.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &join($joins) {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyJoin($joins);
			return $filter;
		}

		/**
		 * Sort the results of this query randomly.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &random() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyRandom();
			return $filter;
		}

		/**
		 * Sort the results of this query by the given fields.
		 *
		 * This function takes an arbitrary number of arguments, each of
		 * which is a string containing an (optional) order specifier
		 * of <i>+</i> or <i>-</i> and a field name.
		 *
		 * i.e. $query->order('+author.surname', '-published');
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &order() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$args = func_get_args();
			call_user_func_array(array($filter, 'applyOrder'), $args);
			return $filter;
		}

		/**
		 * Return only a subset of the total matching records.
		 *
		 * @param int $limit The number of records to return
		 * @param int $offset The record number to start at
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &slice($limit = 0, $offset = 0) {
			if ($offset < 0) $offset = 0;
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applySlice($limit, $offset);
			return $filter;
		}

		/**
		 * Only return unique records.
		 *
		 * Useful for situations where complicated query joins would
		 * otherwise return duplicate objects.
		 *
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &distinct() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyDistinct();
			return $filter;
		}

		/**
		 * Add a group clause to the query.
		 *
		 * @deprecated 2.0 group() is unnecessary with the addition
		 *		of aggregate methods (min(), max(), etc).
		 * @param Array $groupby A list of fields to group results by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function group($groupby) {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyGrouping($groupby);
			return $filter;
		}

		/**
		 * Request to pre-emptively load related objects in a single
		 * query.
		 *
		 * If you know you will be needing to access a related
		 * object from all models in this result set, you can load it
		 * as part of this query, avoiding an extra query for each
		 * Model that usually would occur with lazy reference loading.
		 *
		 * Important notes:
		 * <ol>
		 * <li>You may preload as many ManyToOne fields as you like,
		 *     but you may only preload a single ManyToMany/OneToMany
		 *     due to the way joining works in SQL</li>
		 * <li>Applying this filter for a ManyToMany/OneToMany field
		 *     will result in inaccurate sqlcount() calls</li>
		 * </ol>
		 *
		 * @param string $field The field name linking the related object
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &preload($field) {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyPreload($field);
			return $filter;
		}

		/**
		 * Output debug info for this query.
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function &debug() {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->applyDebug();
			return $filter;
		}

		/**
		 * Add a maximum field to the query.
		 *
		 * Groups the query records together by the given $groupby fields, and
		 * then returns a maximum value of the field specified for each group.
		 *
		 * @param string $name The new field name
		 * @param string $field The field to aggregate
		 * @param Array $groupby The list of fields to group by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::aggregate()
		 */
		public function maxfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'MAX', $groupby);
		}

		/**
		 * Add a minimum field to the query.
		 *
		 * Groups the query records together by the given $groupby fields, and
		 * then returns a minimum value of the field specified for each group.
		 *
		 * @param string $name The new field name
		 * @param string $field The field to aggregate
		 * @param Array $groupby The list of fields to group by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::aggregate()
		 */
		public function minfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'MIN', $groupby);
		}

		/**
		 * Add a average field to the query.
		 *
		 * Groups the query records together by the given $groupby fields, and
		 * then returns a average value of the field specified for each group.
		 *
		 * @param string $name The new field name
		 * @param string $field The field to aggregate (must be numeric)
		 * @param Array $groupby The list of fields to group by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::aggregate()
		 */
		public function avgfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'AVG', $groupby);
		}

		/**
		 * Add a sum field to the query.
		 *
		 * Groups the query records together by the given $groupby fields, and
		 * then returns a sum of all field values contained in each group.
		 *
		 * @param string $name The new field name
		 * @param string $field The field to aggregate (must be numeric)
		 * @param Array $groupby The list of fields to group by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::aggregate()
		 */
		public function sumfield($name, $field, $groupby = null) {
			return $this->aggregate($name, $field, 'SUM', $groupby);
		}

		/**
		 * Add a count field to the query.
		 *
		 * Groups the query records together by the given $groupby fields, and
		 * then returns the count of all records contained in each group.
		 *
		 * @param string $name The new field name
		 * @param string $field The field to aggregate
		 * @param Array $groupby The list of fields to group by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 * @see QueryFilter::aggregate()
		 */
		public function countfield($name = null, $field = null, $groupby = null) {
			return $this->aggregate($name, $field, 'COUNT', $groupby);
		}

		/**
		 * Add an aggregate field to the query.
		 *
		 * This groups the query records together by the given $groupby fields,
		 * and then returns models with a SQL aggregate value of the field
		 * specified for each group (defined by $fn - MIN, MAX, AVG, etc).
		 *
		 * The following example, querying a table of employees, would
		 * add an artificial field "total_employees" to the results that
		 * represents the number of employees from each state:
		 *
		 * <code>
		 * $query->aggregate('total_employees', 'id', 'COUNT', array('state'));
		 * </code>
		 *
		 * The models returned are artificial; they are read-only and cannot
		 * be saved to the database.
		 *
		 * @param string $name The model field name the aggregate result will use
		 * @param string $field The real field to compute an aggregate value for
		 * @param Array $groupby A list of fields to group the aggregate by
		 * @return QueryFilter The new QueryFilter at the end of the filter chain
		 */
		public function aggregate($name, $field, $fn, $groupby = null) {
			$filter = new QueryFilter($this->queryhandler, $this);
			$filter->addAggregate($name, $field, $fn, $groupby);
			return $filter;
		}

		/**
		 * Get a maximum field value and return the result immediately.
		 *
		 * This call is identical to:
		 *
		 * <code>
		 * $query->returnAggregate($field, $field, 'MAX', $groupby, true);
		 * </code>
		 *
		 * @param string $field The field to aggregate
		 * @param Array $groupby A list of fields to group results by
		 * @return mixed The maximum field value of a group
		 * @see QueryFilter->returnAggregate()
		 */
		public function max($field, $groupby = null) {
			return $this->returnAggregate($field, $field, 'MAX', $groupby, true);
		}

		/**
		 * Get a minimum field value and return the result immediately.
		 *
		 * This call is identical to:
		 *
		 * <code>
		 * $query->returnAggregate($field, $field, 'MIN', $groupby, true);
		 * </code>
		 *
		 * @param string $field The field to aggregate
		 * @param Array $groupby A list of fields to group results by
		 * @return mixed The minimum field value of a group
		 * @see QueryFilter->returnAggregate()
		 */
		public function min($field, $groupby = null) {
			return $this->returnAggregate($field, $field, 'MIN', $groupby, true);
		}

		/**
		 * Get a average field value and return the result immediately.
		 *
		 * This call is identical to:
		 *
		 * <code>
		 * $query->returnAggregate($field, $field, 'AVG', $groupby);
		 * </code>
		 *
		 * @param string $field The field to aggregate
		 * @param Array $groupby A list of fields to group results by
		 * @return mixed The average field value of a group
		 * @see QueryFilter->returnAggregate()
		 */
		public function avg($field, $groupby = null) {
			return floatval($this->returnAggregate($field, $field, 'AVG', $groupby));
		}

		/**
		 * Get the sum of all field values and return the result immediately.
		 *
		 * This call is identical to:
		 *
		 * <code>
		 * $query->returnAggregate($field, $field, 'SUM', $groupby, true);
		 * </code>
		 *
		 * @param string $field The field to aggregate
		 * @param Array $groupby A list of fields to group results by
		 * @return mixed The sum of all field values in a group
		 * @see QueryFilter->returnAggregate()
		 */
		public function sum($field, $groupby = null) {
			return $this->returnAggregate($field, $field, 'SUM', $groupby, true);
		}

		/**
		 * Get the total count of all matching records and return the
		 * result immediately.
		 *
		 * This call is identical to:
		 *
		 * <code>
		 * $query->returnAggregate($field, $field, 'COUNT', $groupby);
		 * </code>
		 *
		 * @param string $field The field to aggregate
		 * @param Array $groupby A list of fields to group results by
		 * @return mixed The total number of records per group
		 * @see QueryFilter->returnAggregate()
		 */
		public function sqlcount($field = null, $groupby = null) {
			return intval($this->returnAggregate($field, $field, 'COUNT', $groupby));
		}

		/**
		 * Get an aggregate field value and return the results immediately.
		 *
		 * The following two statements are equivalent:
		 *
		 * <code>
		 * $query->addAggregate('id', 'id', 'MAX')->one()->id;
		 * </code>
		 * and:
		 * <code>
		 * $query->returnAggregate('id', 'id', 'MAX', null, true);
		 * </code>
		 *
		 * This only returns a single value, so if you are specifying
		 * fields to group by, make sure you are also filtering to
		 * get the correct group, or you will only get the first
		 * value of a result set.
		 *
		 * @param string $name The new field name
		 * @param string $field The field name to aggregate
		 * @param string $fn The SQL aggregate function (MIN, MAX, etc)
		 * @param Array $groupby A list of fields to group results by
		 * @param bool $convert Whether to type-convert the resulting value.
		 *			For example, if you are computing an average of integer
		 *			fields, you probably don't want to convert the resulting
		 *			float value to an integer.
		 * @return mixed The aggregate field value
		 */
		public function returnAggregate($name, $field, $fn, $groupby = null, $convert = false) {
			$filter = new QueryFilter($this->queryhandler, $this);
			list($name, $fieldObj) = $filter->addAggregate($name, $field, $fn, $groupby);
			$result = $filter->raw();
			$aggregate = $result[0][$name];
			if ($convert) {
				return $fieldObj->convertFromDbValue($aggregate);
			} else {
				return $aggregate;
			}
		}

		/*
		 * Methods used by the QueryFilter-returning methods above to apply
		 * to current filter.
		 */

		/**
		 * Applies a filter to the current filter, rather than creating a new
		 * QueryFilter and adding to the end of the chain.
		 *
		 * @param Array $filters A hash array of field => value pairs
		 * @see QueryFilter::filter()
		 */
		public function applyFilter($filters) {
			$this->createFilterQuery($filters, false, false);
		}

		/**
		 * Applies a filter to the current filter, rather than creating a new
		 * QueryFilter and adding to the end of the chain.
		 *
		 * @param Array $filters A hash array of field => value pairs
		 * @see QueryFilter::filteror()
		 */
		public function applyFilterOr($filters) {
			$this->createFilterQuery($filters, false, true);
		}

		/**
		 * Applies an exclusion filter to the current filter, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @param Array $filters A hash array of field => value pairs
		 * @see QueryFilter::exclude()
		 */
		public function applyExclude($filters) {
			$this->createFilterQuery($filters, true, false);
		}

		/**
		 * Applies an exclusion filter to the current filter, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @param Array $filters A hash array of field => value pairs
		 * @see QueryFilter::excludeor()
		 */
		public function applyExcludeOr($filters) {
			$this->createFilterQuery($filters, true, true);
		}

		/**
		 * Adds extra fields from related tables to the results
		 * of this query, to reduce additional queries.
		 *
		 * @see QueryFilter::extra()
		 */
		public function applyExtra($select, $where = null, $params = null) {
			if ($select) {
				foreach ($select as $name => $field) {
					list ($realField, $joins, $relmodel, $relfield) = $this->getJoinInfo($field);
					$this->query['select'][$name] = $realField;
					$this->query['converters'][$name] = $relmodel->_fields[$relfield];
					if (count($joins))
						$this->query['tables'] = $this->mergeJoins((array)$this->query['tables'], $joins);
				}
			}
			if ($where)
				$this->applyCondition($where, $params);
		}

		/**
		 * Evaluate a conditional expression to the query that
		 * is not limited to one field.
		 *
		 * @see QueryFilter::condition()
		 */
		private function evalCondition($expression) {
			
			$joins = array();
			$offset = 0;

			$skip = array('VALUES', 'OR', 'AND', 'NULL', 'NOT', 'IS', 'IN');

			if (strpos($expression, '`') !== FALSE)
				// Fields already explicitly specified in database
				// form, no need to parse
				return array($expression, $joins);

			$matches = array();
			// Check if expression uses related fields
			$matchct = preg_match_all("/[a-zA-Z][a-zA-Z0-9_\.]+/",
				$expression, $matches, PREG_OFFSET_CAPTURE);
			if ($matchct > 0) {
				foreach ($matches[0] as $matchField) {
					if ($matchField[1] > 0 && $expression{$matchFields[1]-1} == '%')
						continue;
					if (in_array($matchField[0], $skip))
						continue;
					list ($realField, $fieldJoins, $relmodel, $relfield) = $this->getJoinInfo($matchField[0]);
					// Add any necessary table joins
					if (count($fieldJoins))
						$joins = $this->mergeJoins($joins, $fieldJoins);
					// Replace dot-notation names with real SQL names
					$start = $matchField[1] + $offset;
					$expression = substr($expression, 0, $start) . $realField
						. substr($expression, $start + strlen($matchField[0]));
					$offset += strlen($realField) - strlen($matchField[0]);
				}
				if (strpos($expression, '!') !== FALSE) {
					$expression = 'IF('.str_replace(array('!',':'),
						array(',',','), $expression).')';
				}
			}

			return array($expression, $joins);

		}

		/**
		 * Add a more complex conditional expression to the query that
		 * is not limited to one field.
		 *
		 * @see QueryFilter::condition()
		 */
		public function applyCondition($expression, $params = null) {

			list($expression, $joins) = $this->evalCondition($expression);

			if ($joins && count($joins) > 0)
				$this->query['tables'] = $this->mergeJoins(
					(array)$this->query['tables'], $joins);

			if (isset($this->query['where']['sql']))
				$this->query['where']['sql'] = $this->query['where']['sql'].' AND ('.$expression.')';
			else
				$this->query['where']['sql'] = $expression;

			if ($params)
				$this->query['where']['params'] = array_merge(
					isset($this->query['where']['params'])
						? $this->query['where']['params']
						: array(),
					$params);

		}

		/**
		 * Manually join tables.
		 *
		 * @see QueryFilter::join()
		 */
		public function applyJoin($joins) {
			if (count($joins))
				$this->query['tables'] = $this->mergeJoins((array)$this->query['tables'], $joins);
		}

		/**
		 * Applies random ordering to the current filter, rather than creating a new
		 * QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::random()
		 */
		public function applyRandom() {
			if ($this->query['order'])
				$this->query['order'] .= ', RAND()';
			else
				$this->query['order'] = 'RAND()';
		}

		/**
		 * Applies ordering to the current filter, rather than creating a new
		 * QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::order()
		 */
		public function applyOrder() {
			$args = func_get_args();
			if ($args && is_array($args[0]))
				$args = $args[0];
			list($clause, $joins) = $this->orderClause($args);
			if ($this->query['order'])
				$this->query['order'] .= ', '.$clause;
			else
				$this->query['order'] = $clause;
			if (count($joins))
				foreach ($joins as $t => $j)
					$this->query['tables'][$t] = $j;
		}

		/**
		 * Generates a list of SQL order clauses and table joins based on
		 * a field list.
		 *
		 * @see QueryFilter::order()
		 */
		private function orderClause($fields) {
			$clauses = array();
			$joins = array();
			foreach ($fields as $order) {
				$dir = 'ASC';
				$table = $this->tableName;
				if (substr($order, 0, 1) == '-') {
					$order = substr($order, 1);
					$dir = 'DESC';
				}
				if (substr($order, 0, 1) == '+')
					$order = substr($order, 1);
				// Add appropriate table joins here
				$joinInfo = $this->getJoinInfo($order);
				$joins = $this->mergeJoins($joins, $joinInfo[1]);
				$clauses[] = $joinInfo[0].' '.$dir;
			}
			return array(implode(', ', $clauses), $joins);
		}

		/**
		 * Generates SQL table join info from the specified field name.
		 *
		 * The return value is of the format:
		 *
		 * <code>
		 * array(
		 *    // List of table joins
		 *    [0] => array(
		 *        '`target` ON (`target`.`join_field` = `source`.`id_field`)' => 'INNER JOIN',
		 *        ...
		 *        ),
		 *    // The model owning the resolved field
		 *    [1] => $related_model
		 * );
		 * </code>
		 *
		 * @param Model $model The model that is the source of the join.
		 *		This will usually $this->model.
		 * @param string $field The field we want to access. If this field
		 *		represents a related object field in dotted notation (i.e.
		 *		"site.page.title", it must be resolvable from the given model
		 *		object (i.e. $model->site must exist)
		 * @return Array A hash array of join information (defined above)
		 * @throws InvalidParametersException if a specified field is
		 *		not defined as a relation field
		 */
		private function getFieldJoinInfo($model, $field, $type = 'LEFT OUTER') {

			$joins = array();
			$relmodel = null;

			$fieldobj = $model->_fields[$field];
			if ($fieldobj) {

				if ($fieldobj instanceof RelationField) {

					$relmodel = $fieldobj->getRelationModel();
					$tname = $relmodel->_table;
					if ($fieldobj instanceof OneToManyField) {
						$tjoin = $fieldobj->joinField;
						$joins['`'.$tname.'` ON (`'.$tname.'`.`'.$tjoin.'` = `'.$model->_table.'`.`'.$model->_idField.'`)'] = $type.' JOIN';
					} elseif ($fieldobj instanceof ManyToManyField) {
						$joinmodel = $fieldobj->getJoinModel();
						$joinfield = $fieldobj->joinField;
						$relid = $fieldobj->getFieldType()->field;
						$targetfield = $fieldobj->targetField;
						$joins['`'.$joinmodel->_table.'` ON (`'.$joinmodel->_table.'`.`'.$joinfield.'` = `'.$model->_table.'`.`'.$model->_idField.'`)'] = $type.' JOIN';
						$joins['`'.$relmodel->_table.'` ON (`'.$relmodel->_table.'`.`'.$relid.'` = `'.$joinmodel->_table.'`.`'.$targetfield.'`)'] = $type.' JOIN';
					} elseif (isset($fieldobj->options['reverseJoin'])) {
						$joinField = $fieldobj->options['reverseJoin'];
						$joins['`'.$tname.'` ON (`'.$tname.'`.`'.$joinField.'` = `'.$model->_table.'`.`'.$model->_idField.'`)'] = $type.' JOIN';
					} else {
						$tid = $relmodel->_idField;
						$joins['`'.$tname.'` ON (`'.$tname.'`.`'.$tid.'` = `'.$model->_table.'`.`'.$field.'`)'] = $type.' JOIN';
					}

				} else {
					throw new InvalidParametersException('The field "'.$field.'" is not a relation field.');
				}

			}

			return array($joins, $relmodel);

		}

		/**
		 * Generates SQL table join info from the specified field name and
		 * join type.
		 *
		 * The return value is of the format:
		 *
		 * <code>
		 * array(
		 *    // The fully-qualified resolved SQL field name
		 *    [0] => '`target_table`.`field_name`',
		 *    // List of table joins required to query for this field
		 *    [1] => array(
		 *        '`target` ON (`target`.`join_field` = `source`.`id_field`)' => 'INNER JOIN',
		 *        ...
		 *        ),
		 *    // The Model owning the resolved field
		 *    [2] => $related_model,
		 *    // The ModelField defining the resolved field
		 *    [3] => $related_field
		 * );
		 * </code>
		 *
		 * @param string $field The field we want to access. If this field
		 *		represents a related object field in dotted notation (i.e.
		 *		"site.page.title", it must be resolvable from the given model
		 *		object (i.e. $model->site must exist)
		 * @param String $type The SQL join type ('LEFT OUTER', 'INNER', etc)
		 * @return Array A hash array of join information (defined above)
		 */
		private function getJoinInfo($field, $type = 'LEFT OUTER') {

			$joins = array();
			$currmodel = $this->model;

			if (strpos($field, '.') !== false
					|| $this->model->_fields[$field] instanceof RelationSetField) {

				$relfields = explode('.', $field);
				$field = array_pop($relfields);
				foreach ($relfields as $f) {
					$joinInfo = $this->getFieldJoinInfo($currmodel, $f, $type);
					$currmodel = $joinInfo[1];
					$joins = array_merge($joins, $joinInfo[0]);
				}

				if ($field == 'pk')
					$field = $currmodel->_idField;

				// RelationSetFields are artifical fields; need to add another
				// join and return the relation table's primary key instead
				if ($currmodel->_fields[$field] instanceof RelationSetField) {
					$joinInfo = $this->getFieldJoinInfo($currmodel, $field, $type);
					$currmodel = $joinInfo[1];
					$joins = array_merge($joins, $joinInfo[0]);
					$field = $currmodel->_idField;
				}

			}

			$qualField = '`'.$currmodel->_table.'`.`'.$field.'`';

			return array($qualField, $joins, $currmodel, $field);

		}

		/**
		 * Applies a subset slice to the current filter, rather than creating a new
		 * QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::slice()
		 */
		public function applySlice($limit = 0, $offset = 0) {
			$this->query['limit'] = intval($limit);
			$this->query['offset'] = intval($offset);
		}

		/**
		 * Apply unique record filtering to the current filter, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::distinct()
		 */
		public function applyDistinct() {
			$this->query['distinct'] = true;
		}

		/**
		 * Applies a preload directive to the current object, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::preload()
		 */
		public function applyPreload($field) {
			if ($field) {
				list($joins, $relmodel) = $this->getFieldJoinInfo($this->model, $field);
				if ($relmodel) {
					$this->query['preload'][] = $field;
					$this->query['tables'] = $this->mergeJoins($this->query['tables'], $joins);
					$tname = $relmodel->_table;
					foreach ($relmodel->_fields as $fname => $fobj) {
						if (!($fobj instanceof RelationSetField))
							$this->query['select']['__'.$field.'__'.$fname] = $tname.'.'.$fname;
					}
				}
			}
		}

		/**
		 * Applies a debug directive to the current object, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::debug()
		 */
		public function applyDebug() {
			$this->query['debug'] = true;
		}

		/**
		 * Applies a grouping clause to the current object, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::group()
		 */
		public function applyGrouping($groupby, $select = false) {

			if ($groupby) {
				
				for ($i = 0; $i < count($groupby); ++$i) {
					$gf = $groupby[$i];
					if (strpos($gf, '.') !== FALSE) {
						$ji = $this->getJoinInfo($gf);
						$gf = $ji[0];
					} else {
						$gf = '`'.$this->tableName.'`.`'.$gf.'`';
					}
					if ($select)
						$this->query['select'][$groupby[$i]] = $gf;
				}

				$this->query['groupby'] = $groupby;

			}

		}

		/**
		 * Add an aggregate field to the current object, rather than
		 * creating a new QueryFilter and adding to the end of the chain.
		 *
		 * @see QueryFilter::aggregate()
		 */
		public function addAggregate($name, $field, $fn, $groupby = null) {

			if (!$field)
				$field = $this->model->_idField;
			if (!$name)
				$name = $field;

			$realField = null;

			if (strpos($field, '.') !== FALSE || $this->model->_fields[$field] instanceof RelationSetField) {

				list($realField, $joins, $relmodel, $relfield) = $this->getJoinInfo($field);

				if (!$groupby)
					$groupby = array_keys($this->model->_dbFields);

				if ($joins && count($joins) > 0)
					$this->query['tables'] = $joins;
				$this->query['converters'][$name] = $relmodel->_fields[$relfield];
				$this->query['select'][$name] = $fn.'('.$realField.')';
				$this->applyGrouping($groupby, false);

			} else  {

				$this->query['aggregates'][] = $name;
				$this->query['select'][$name] = $fn.'(`'.$this->tableName.'`.`'.$field.'`)';
				$this->applyGrouping($groupby, true);
				$realField = $this->model->_fields[$field];

			}

			return array($name, $realField);
			
		}

		// Debug function for retrieving SQL for the current filter.
		public function sql() {
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			list($sql, $params) = $this->buildSQL($q);
			return $this->replaceSQLParams($sql, $params);
		}

		/*
		 * Methods that execute a query and return the results
		 */

		/**
		 * Execute the current query and return results as a set of
		 * Model instances.
		 *
		 * @param string $map Return a hash array mapped by the specified
		 *		field's value, rather than a numerically-indexed list
		 * @param bool $multimap If a $map parameter is specified,
		 * 		make the returned map values an array of Model objects.
		 *		Useful if you want to map by a field value that is not
		 *		unique across records.
		 * @return Array An array of Model instances matching the query.
		 */
		public function select($map = null, $multimap = false) {	
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			$result = $this->doSelect($q);
			$models =& $this->createModels($result, $q['aggregates'], $map, $q['preload'], $multimap);
			$this->models = $models;
			return $this->models;
		}

		/**
		 * Execute the current query and return results as a set of
		 * hash arrays.
		 *
		 * @param string $map Return a hash array mapped by the specified
		 *		field's value, rather than a numerically-indexed list
		 * @param bool $multimap If a $map parameter is specified,
		 * 		make the returned map values an array of records.
		 *		Useful if you want to map by a field value that is not
		 *		unique across records.
		 * @return Array An array of name/value hash arrays for records
		 *		matching the query.
		 */
		public function hash($map = null, $multimap = false) {
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			$result = $this->doSelect($q);
			return $this->createHash($result, $q['aggregates'], $map, $q['preload'], $multimap);
		}

		/**
		 * Execute the current query and return results as a set of
		 * hash arrays, <i>without performing any type conversion</i>.
		 * This is the fastest way to query if you're trying to squeeze
		 * every bit of performance out of ModelQuery.
		 *
		 * @param string $map Return a hash array mapped by the specified
		 *		field's value, rather than a numerically-indexed list
		 * @param bool $multimap If a $map parameter is specified,
		 * 		make the returned map values an array of records.
		 *		Useful if you want to map by a field value that is not
		 *		unique across records.
		 * @return Array An array of name/value hash arrays for records
		 *		matching the query.
		 */
		public function raw($map = null, $multimap = false) {
			$q = $this->mergedQuery(true);
			// Aggregate queries don't mix with preloads
			if (count($q['aggregates']))
				$q = $this->mergedQuery(true, true);
			$result = $this->doSelect($q);
			return $this->createRawHash($result, $map, $q['preload'], $multimap);
		}

		/**
		 * Execute the current query and return results as a set of
		 * Model instances, mapped by the given field.  Identical
		 * to calling QueryFilter::select($key), except that it
		 * defaults to the primary key field if no key is given.
		 *
		 * @param string $key The field to map values by. Defaults to the
		 *		model's primary key.
		 * @param bool $multimap If a $map parameter is specified,
		 * 		make the returned map values an array of records.
		 *		Useful if you want to map by a field value that is not
		 *		unique across records.
		 * @return Array An array of Models for recordsmatching the query.
		 * @see QueryFilter::select()
		 */
		public function map($key = null, $multimap = false) {
			if ($key == null)
				$key = $this->model->_idField;
			return $this->select($key, $multimap);
		}

		/**
		 * Execute current query and return the results as a database cursor.
		 *
		 * Performs the same purpose as QueryFilter::select(),
		 * except that Model objects are loaded on demand as they are
		 * iterated over, which avoids loading the entire result set
		 * into memory.
		 *
		 * <code>
		 * // Get a CursorIterator object for this query
		 * $users = $uq->filter('group', 5)->cursor();
		 * 
		 * // CursorIterator can also be used normally as an array, but it keeps the database
		 * // connection open and loads rows as needed:
		 * foreach ($users as $user) {
		 *     // Do something
		 * }
		 * 
		 * // Manually close down the cursor
		 * $users->close();
		 * </code>
		 *
		 * @return CursorIterator A database cursor to iterate over
		 */
		public function cursor() {
			$q = $this->mergedQuery(true);
			$result = $this->doSelect($q);
			return new CursorIterator($this->queryhandler, $result, $q['aggregates']);
		}

		/**
		 * Execute current query and return the results as a database
		 * cursor.
		 *
		 * Performs the same purpose as QueryFilter::select(),
		 * except that records are loaded on demand as they are
		 * iterated over, which avoids loading the entire result set
		 * into memory.
		 *
		 * Differs from cursor() in that records are returned as
		 * name/value hash arrays rather than Model objects.
		 *
		 * @return HashCursorIterator A database cursor to iterate over
		 * @see QueryFilter::cursor()
		 */
		public function hashCursor() {
			$q = $this->mergedQuery(true);
			$result = $this->doSelect($q);
			return new HashCursorIterator($this, $result, $q['aggregates']);
		}

		/**
		 * Execute the current query, and return an array of values
		 * of a single field from each record in the result set.
		 *
		 * Example:
		 * <code>
		 * // Returns a simple list of all active usernames
		 * $active = $users->filter('active', true)->pluck('username');
		 * </code>
		 *
		 * @param $field The field to get values for
		 * @return Array An array of field values
		 */
		public function pluck($field) {
			$values = $this->values($field);
			return $values[$field];
		}

		/**
		 * Execute the current query, and return an array of values
		 * of the specified fields from each record in the result set.
		 *
		 * Example:
		 * <code>
		 * // Returns a simple list of all active usernames
		 * $active = $users->filter('active', true)->values('id', 'username');
		 * // Returns an array of the format:
		 * // array('id' => array(1, 5, 12, 13),
		 * //       'username' => array('admin', 'alice', 'bob', 'brian'));
		 * </code>
		 *
		 * @param $field The field to get values for
		 * @return Array A hash array of field name => values
		 */
		public function values() {
			$q = $this->mergedQuery(true);
			$fields = func_get_args();
			$values = array();
			$fieldDefs = array();

			for ($i = 0; $i < count($fields); ++$i) {
				list ($realField, $joins, $relmodel, $relfield) = $this->getJoinInfo($fields[$i]);
				if ($relfield) {
					$fieldDefs[$fields[$i]] = $relmodel->_fields[$relfield];
					$q['select'][$fields[$i]] = $realField;
					$q['converters'][$fields[$i]] = $relmodel->_fields[$relfield];
					if (count($joins))
						$q['tables'] = $this->mergeJoins((array)$q['tables'], $joins);
					$values[$fields[$i]] = array();
				}
			}

			$result = $this->doSelect($q, false);
			$modelfields =& $this->model->_dbFields;
			while (!$result->EOF) {
				$row =& $result->fields;
				foreach ($fields as $field) {
					if (isset($fieldDefs[$field])) {
						$def = $fieldDefs[$field];
						$raw = $q['aggregates'] && in_array($field, $q['aggregates']);
						if (array_key_exists($field, $row))
							$values[$field][] = $raw ? $row[$field] : $def->convertFromDbValue($row[$field]);
					}
				}
				$result->MoveNext();
			}
			return $values;
		}

		/**
		 * Execute the current query and return a single record.
		 * 
		 * @param mixed $id The unique primary key of the object to fetch.
		 *		Not required if the current filter would only return a
		 *		single object. (i.e. $query->filter('id', 1)->get())
		 * @return Model The single Model that matches the current query
		 * @throws TooManyResultsException if more than one matching record
		 * 		was found
		 * @throws DoesNotExistException if no matching records were found
		 */
		public function get($id = null) {

			$q = $this->mergedQuery(true);

			if (count($q['filters']) == 0) {
				$cacheKey = get_class($this->model).':'.$id;
				$model = $this->queryhandler->factory->cacheGet($cacheKey);
				if ($model)
					return $model;
			}
			
			$query = $id ? $this->filter('pk', $id) : $this->all();
			$models = $query->select();

			if (count($models) > 1)
				throw new TooManyResultsException('Multiple models were found.');
			elseif (count($models) == 0)
				throw new DoesNotExistException('No models were returned.');

			return $models[0];

		}

		/**
		 * Execute the current query and return the first record.
		 *
		 * @return Model The first Model that matches the current query
		 * @throws DoesNotExistException if no matching records were found
		 */
		public function one() {
			$models = $this->slice(1)->select();
			if (!count($models))
				throw new DoesNotExistException('No models were returned.');
			return $models[0];
		}

		/**
		 * Execute the current query and return the first record, or
		 * null if none exists.
		 *
		 * @return Model The first Model that matches the current query,
		 *		or null if none were found
		 */
		public function any() {
			$models = $this->slice(1)->select();
			if (count($models) > 0)
				return $models[0];
			return null;
		}

		/**
		 * Bulk-update the set of records that matches the current query
		 * filters.
		 *
		 * The fields to update are specified as a name/value hash array.  As
		 * a shortcut, you can use the optional second paramtere to update
		 * a single object by primary key.
		 *
		 * @param Array $values A field name/value hash array
		 * @param mixed $pk The primary key of the object to update
		 * @return mixed The updated record's primary key (if specified)
		 */
		public function update($values, $pk = null) {
			return $this->doUpdate($values, $pk);
		}

		/**
		 * Insert a new record into the data store.
		 *
		 * The field values are specified as either a name/value hash
		 * array or a Model object.
		 *
		 * <code>
		 * $id = $uq->insert(array('username' => 'newuser', 'site' => 5));
		 * </code>
		 *
		 * @param mixed $values A Model or hash array of field/value
		 *		pairs, or an array of the same for bulk inserts.
		 * @param mixed $updateFilters Tells ModelQuery to execute an
		 *		update instead if a key conflict is encountered. If
		 *		this parameter is TRUE, it will update with the passed
		 *		values. Alternately, it can be a hash array of field =>
		 *		condition statements. The special value %v can be used
		 *		in a condition statement to represent the value of the
		 *		field in $values.
		 * @return mixed The new record's primary key
		 * @see QueryFilter::condition()
		 */
		public function insert($model, $updateFilters = null, $async = false) {
			return $this->doInsert($model, $updateFilters, $async);
		}

		/**
		 * Delete all records that match the current query.
		 *
		 * Note that this method does not take any parameters - the current
		 * query filters are used to determine which records to delete.
		 * If you wish to only delete a single record, be sure you use a
		 * QueryFilter::filter() call on the model's primary key.
		 *
		 * @return bool TRUE if the delete succeeded
		 */
		public function delete() {
			return $this->doDelete();
		}

		/**
		 * Clear the local query cache.
		 *
		 * When a query is executed (either with select() or by using a
		 * QueryFilter object as an iterator), the query results are cached
		 * in the QueryFilter object.  If for any reason you need to refresh
		 * the results from the database, you must call flush() first to
		 * clear the cached results.
		 */
		public function flush() {
			$this->models = null;
		}

		/**
		 * Check if this query has been executed.
		 * @return boolean True if the query has been executed
		 */
		public function executed() {
			return ($this->models !== null);
		}

		/**
		 * Shortcut to just return the SQL WHERE clause from this query object.
		 * @return string The WHERE clause
		 */
		public function where_sql() {
			$q = $this->mergedQuery(true);
			if (isset($q['where']['sql']))
				return $q['where']['sql'];
			return null;
		}

		/**
		 * Shortcut to just return the SQL WHERE clause parameter list from this
		 * query object.
		 * @return string The WHERE clause parameter list
		 */
		public function where_params() {
			$q = $this->mergedQuery(true);
			if (isset($q['where']['params']))
				return $q['where']['params'];
			return array();
		}

		/*
		 * Private methods
		 */

		/**
		 * Return a SQL statement and parameters for use by query().
		 *
		 * @param Array $query The query specification array
		 * @param bool $selectAllFields If true, will select all model fields.
		 *		Otherwise, it will only select fields specified in the query
		 *		'select' option.
		 * @return Array a length-2 array or $sql, $params for passing to query()
		 */
		private function buildSQL($query, $selectAllFields = true) {

			$selectAllFields = $selectAllFields && !count($query['aggregates']);
			$fields = $this->model->_dbFields;
			$params = array();

			// Select fields
			$sql = 'SELECT ';
			if ($query['distinct'])
				$sql .= 'DISTINCT ';
			$first = true;
			if ($selectAllFields) {
				foreach ($fields as $field => $junk) {
					if (!$first) $sql .= ', ';
					$sql .= '`'.$this->tableName.'`.`'.$field.'`';
					$first = false;
				}
			}
			if (count($query['select'])) {
				foreach ($query['select'] as $field => $spec) {
					$d = $p = null;
					if (is_array($spec)) {
						$d = $spec[0];
						$p = $spec[1];
					} else {
						$d = $spec;
					}
					if (!$first) $sql .= ', ';
					$sql .= '('.$d.') AS `'.$field.'`';
					$first = false;
					if ($p && is_array($p))
						$params = array_merge($params, $p);
				}
			}

			// Tables
			$sql .= ' FROM '.$this->tableName;
			if (count($query['tables']))
				foreach ($query['tables'] as $table => $jointype)
					$sql .= ' '.$jointype.' '.$table;
			
			// Where clause
			if (isset($query['where']['sql'])) {
				$sql .= ' WHERE '.$query['where']['sql'];
				if (isset($query['where']['params']) && is_array($query['where']['params']))
					$params = array_merge($params, $query['where']['params']);
			}

			// Grouping
			if (count($query['groupby'])) {
				$sql .= ' GROUP BY ';
				$first = true;
				foreach ($query['groupby'] as $field) {
					if (!$first) $sql .= ', ';
					$sql .= $field;
					$first = false;
				}
			}

			// Ordering
			if ($query['order'])
				$sql .= ' ORDER BY '.$query['order'];

			// Offset / limit
			if ($query['limit']) {
				$sql .= ' LIMIT ?';
				$params[] = $query['limit'];
			}
			if ($query['offset']) {
				$sql .= ' OFFSET ?';
				$params[] = $query['offset'];
			}

			$sql .= ';';

			return array($sql, $params);
		}

		/**
		 * Executes the current query, parses the results into Model objects,
		 * and caches them internally.
		 *
		 * @param Array $query The query specification array
		 * @param bool $selectAllFields If true, will select all model fields.
		 *		Otherwise, it will only select fields specified in the query
		 *		'select' option.
		 * @return ADORecordSet The record set results from ADODB
		 */
		private function doSelect($query, $selectAllFields = true) {
			list($sql, $params) = $this->buildSQL($query, $selectAllFields);
			return $this->query($sql, $params);
		}

		/**
		 * Bulk-update the set of records that matches the current query
		 * filters.
		 * @see QueryFilter::update()
		 */
		private function doUpdate($model, $pk) {

			// Automatically set ordered fields
			if ($pk)
				$this->adjustOrders($model);

			$dbmodel = $this->convertToDBModel($model);
			$q = $this->mergedQuery(true);
			$bindparams = array();
			$idField = $this->model->_idField;

			$sql = 'UPDATE '.$this->tableName;
			if ($q['tables'] && count($q['tables'])) {
				foreach ($q['tables'] as $t => $j)
					$sql .= ' '.$j.' '.$t;
			}
			$sql .= ' SET ';
			$first = true;
			foreach ($dbmodel as $field => $value) {
				if ($field != $this->model->_idField || $value != $pk) {
					if (!$first) $sql .= ', ';
					$sql .= '`'.$this->tableName.'`.`'.$field.'` = ?';
					$bindparams[] = $value;
					$first = false;
				}
			}
			if (isset($q['where']['sql'])) {
				$sql .= ' WHERE '.$q['where']['sql'];
				$bindparams = array_merge($bindparams, (array)$q['where']['params']);
			} else {
				$sql .= ' WHERE '.$this->tableName.'.'.$this->model->_idField.' = ?';
				$bindparams[] = $pk;
			}
			$sql .= ';';

			$result = $this->query($sql, $bindparams);

			// Updates that don't change any field values still return 0 rows
			//if ($this->affectedRows() > 0)
			//	return true;
			//else
			//	throw new DoesNotExistException('No matching objects were found in the database.');

			$id = $pk;

			if (array_key_exists($this->model->_idField, $model))
				$id = $model[$this->model->_idField];

			return $id;

		}

		/**
		 * Bulk-update multiple models in a single query.
		 *
		 * @todo Write this
		 */
		private function doBatchUpdate(&$models) {
		}

		/**
		 * Insert a new record into the data store.
		 * @see QueryFilter::insert()
		 */
		private function doInsert($model, $updateFilters = null, $async = false) {

			$dbmodels = array();
			$fields = array();
			$bindparams = array();

			// Merge with any ":exact" query filter values
			$q = $this->mergedQuery(true);

			// Check if model is a list of models
			if ($model instanceof Model ||
					(is_array($model) && array_keys($model) !== range(0, count($model) - 1))) {

				// Automatically set ordered fields
				$this->adjustOrders($model);

				$dbmodel = $this->convertToDBModel($model);
				if ($q['insert'])
					$dbmodel = array_merge($this->convertToDBModel($q['insert']), $dbmodel);
				// Single object, only insert specified fields
				foreach ($dbmodel as $key => $value)
					$fields[] = $key;
				$dbmodels[] = $dbmodel;
			} else {

				foreach ($model as $m) {

					// Automatically set ordered fields
					$this->adjustOrders($m);

					$dbmodel = $this->convertToDBModel($m);
					if ($q['insert'])
						$dbmodel = array_merge($this->convertToDBModel($q['insert']), $dbmodel);
					$dbmodels[] = $dbmodel;
					// Check which fields are used
					foreach ($dbmodel as $key => $value)
						if (!in_array($key, $fields))
							$fields[] = $key;
				}
			}

			$values = array();
			foreach ($dbmodels as $dbmodel) {
				$vsql = '(';
				for ($i = 0; $i < sizeof($fields); ++$i) {
					$vsql .= '?';
					if ($i != sizeof($fields) - 1) $vsql .= ', ';
					if (array_key_exists($fields[$i], $q['insert']))
						$bindparams[] = $q['insert'][$fields[$i]];
					else
						$bindparams[] = $dbmodel[$fields[$i]];
				}
				$vsql .= ')';
				$values[] = $vsql;
			}

			$idField = $this->model->_idField;

			$sql = 'INSERT '.($async ? 'DELAYED ' : '').'INTO '.$this->tableName.' (';
			$first = true;
			foreach ($fields as $key) {
				if (!$first) $sql .= ', ';
				$sql .= '`'.$key.'`';
				$first = false;
			}
			$sql .= ') VALUES ';

			$sql .= implode(',', $values);

			if ($updateFilters !== null) {
				$sql .= ' ON DUPLICATE KEY UPDATE ';
				$filters = is_array($updateFilters) ? $updateFilters : array();
				$keep = ($updateFilters === false);
				$first = true;
				foreach ($fields as $f) {
					if ($f == $idField)
						continue;
					if (!$first) $sql .= ', ';
					$sql .= '`'.$f.'` = ';
					if (isset($filters[$f])) {
						$condition = $this->evalCondition($filters[$f]);
						$expression = str_replace('%v', 'VALUES(`'.$f.'`)', $condition[0]);
						$sql .= $expression;
					} elseif ($keep) {
						$sql .= '`'.$f.'`';
					} else {
						$sql .= 'VALUES(`'.$f.'`)';
					}
					$first = false;
				}
			}

			$result = $this->query($sql, $bindparams);
			if ($this->affectedRows() > -1) {
				$fields = $this->model->_dbFields;
				if (!isset($model[$idField]))
					$model[$idField] = $fields[$idField]->convertFromDbValue($this->insertId());
				return $model[$idField];
			} else {
				throw new SQLException('Could not insert record: '.$this->getError());
			}
		}

		/**
		 * Delete all records that match the current query.
		 * @see QueryFilter::delete()
		 */
		private function doDelete() {
			$ordered = $this->model->orderedFields();
			// If there's ordered fields we have to load all affected and process reordering first
			$cascade = array();
			foreach ($this->model->_fields as $field => $def)
				if ($def instanceof ManyToOneField
						&& isset($def->options['cascadeDelete'])
						&& $def->options['cascadeDelete']
						&& !isset($def->options['reverseJoin']))
					$cascade[$field] = array($def, array());
			if (count($ordered) || count($cascade)) {
				$models = $this->select();
				foreach ($models as $model) {
					$this->queryhandler->factory->cacheRemove(get_class($model).':'.$model->pk);
					if (count($ordered)) $this->deleteOrders($model);
					if (count($cascade))
						foreach ($cascade as $f => $junk)
							$cascade[$f][1][] = $model->getPrimitiveFieldValue($f);
				}
			}
			$bindparams = array();
			$q = $this->mergedQuery(true);
			$sql = 'DELETE FROM '.$this->tableName;
			if ($q['tables'] && count($q['tables'])) {
				$sql .= ' USING '.$this->tableName;
				foreach ($q['tables'] as $t => $j)
					$sql .= ' '.$j.' '.$t;
			}
			if (isset($q['where']['sql'])) {
				$sql .= ' WHERE '.$q['where']['sql'];
				$bindparams = array_merge($bindparams, (array)$q['where']['params']);
			}
			$sql .= ';';

			$result = $this->query($sql, $bindparams);

			// Cascade-delete children after parents are deleted in case
			// they're null-restricted
			if (count($cascade))
				foreach ($cascade as $f => $d)
					$d[0]->getRelationModel()->getQuery()->filter('pk:in', $d[1])->delete();

			// Affected rows is messed up for mysqlt :|
			//if ($this->affectedRows() > -1)
				return true;
			//else
				//throw new DoesNotExistException('No matching objects were found in the database.');
		}

		/**
		 * Convert an array of field values into the correct types for a
		 * database query.
		 */
		private function convertToDBModel($model) {
			$modelVals = ($model instanceof Model ? $model->getFieldValues() : $model);
			$values = array();
			$fields = $this->model->_dbFields;
			foreach ($fields as $field => $def)
				if (array_key_exists($field, $modelVals))
					$values[$field] = $def->convertToDbValue($modelVals[$field]);
			return $values;
		}

		/**
		 * If the model contains an OrderedField, adjustOrders() checks
		 * to see if any related objects' orders need to be adjusted to
		 * make room for this model before an insert or update.
		 *
		 * @param mixed &$model The model values we will be committing to
		 *		the database, as a hash array or Model object
		 */
		private function adjustOrders(&$model) {

			$ordered = $this->model->orderedFields();

			if (count($ordered)) {

				foreach ($ordered as $field => $def) {

					$groupquery = $this->queryhandler;
					$group_by = $def->groupBy ? $def->groupBy : array();
					$currorder = null;

					// Try to use versioning info if a Model object was passed in
					if ($model instanceof Model) {

						$previous = $model->getPreviousFieldValues();
						$currorder = isset($previous[$field]) ? $previous[$field] : 0;
						$changedGroup = false;

						if ($currorder)
							foreach ($group_by as $gf) {
								if ($previous[$gf] != $model->getPrimitiveFieldValue($gf)) {
									$changedGroup = true;
									$this->deleteOrders($previous);
									$currorder = null;
									break;
								}
							}

					}

					if (!$changedGroup && !$currorder && isset($model[$this->model->_idField])) {
						try {
							$original = $this->queryhandler->get($model[$this->model->_idField]);
							$currorder = $original[$field];
						} catch (DoesNotExist $e) {
							$currorder = null;
						}
					}

					if ($group_by)
						foreach ($group_by as $g)
							$groupquery = $groupquery->filter($g, $model[$g]);

					if ($model[$field] !== null) {

						$params = array();
						$targetorder = $model[$field];

						if ($currorder !== $targetorder) {

							$eq = $groupquery->filter($field, $targetorder)->exclude($this->model->_idField, $model[$this->model->_idField]);
							$exists = $eq->count();

							if ($exists) {

								$maxorder = $groupquery->max($field);
								$maxorder = ($maxorder ? ++$maxorder : 1);

								$sort = 'ASC';

								$sql = 'UPDATE '.$this->tableName.' SET '.$field.' = ';

								if (!$currorder) {

									$sql .= $field.' + 1 WHERE '.$field.' >= ?';
									$params = array($targetorder);

									$sort = 'DESC';

								} elseif ($currorder < $targetorder) {

									$sql .= 'CASE WHEN '.$field.' = ? THEN ? ELSE '.$field.' - 1 END'
										.' WHERE '.$field.' >= ? AND '.$field.' <= ?';
									$params = array($currorder, $maxorder, $currorder, $targetorder);

								} else {

									$sql .= 'CASE WHEN '.$field.' = ? THEN ? ELSE '.$field.' + 1 END'
										.' WHERE '.$field.' <= ? AND '.$field.' >= ?';
									$params = array($currorder, $maxorder, $currorder, $targetorder);

									$sort = 'DESC';

								}

								if ($wheresql = $groupquery->where_sql())
									$sql .= ' AND '.$wheresql;

								$sql .= ' ORDER BY '.$field.' '.$sort.';';

								$params = array_merge($params, $groupquery->where_params());

								$this->query($sql, $params);

							}
						}
					} else {
						$maxorder = $groupquery->max($field);
						$maxorder = ($maxorder ? ++$maxorder : 1);
						$model[$field] = $maxorder;
					}
				}
			}
		}

		/**
		 * If the model contains an OrderedField, deleteOrders() fills
		 * in any ordering gaps that would occur in the database if this
		 * model were deleted.
		 *
		 * @param mixed &$model The model values we will be committing to
		 *		the database, as a hash array or Model object
		 */
		private function deleteOrders($model) {
			$ordered = $this->model->orderedFields();
			if (count($ordered)) {
				foreach ($ordered as $field => $def) {
					$groupquery = $this->queryhandler;
					$group_by = $def->groupBy;
					if ($group_by)
						foreach ($group_by as $g)
							$groupquery = $groupquery->filter($g, $model[$g]);
					$maxorder = $groupquery->max($field);
					$maxorder = ($maxorder ? ++$maxorder : 1);
					if ($model[$field] !== null) {
						$order = $model[$field];
						$sql = 'UPDATE '.$this->tableName.' SET '.$field.' = ';
						$sql .= 'CASE WHEN '.$field.' = ? THEN ? ELSE '.$field.' - 1 END';
						$sql .= ' WHERE '.$field.' >= ?';
						if ($wheresql = $groupquery->where_sql())
							$sql .= ' AND '.$wheresql;
						$sql .= ' ORDER BY '.$field.' ASC;';
						$bindparams = array_merge(array($order, $maxorder, $order), $groupquery->where_params());
						$this->query($sql, $bindparams);
					}
				}
			}
		}

		/**
		 * Returns a complete query specification object for this filter chain.
		 *
		 * Each query filter stores its own filter state.  During query execution,
		 * mergedQuery() walks up the filter chain and correctly merges all filters
		 * contained in any ancestor filters.
		 *
		 * This method should never need to be called by external code.
		 * @param bool $applyDefaults Whether to apply any default parameters
		 *		specified in the Model definition (such as field ordering)
		 * @param bool $skipPreload Whether to include preload() filters
		 * @return Array The complete query filter chain specification
		 */
		public function mergedQuery($applyDefaults = false, $skipPreload = false) {

			$q = $this->query;

			if ($this->parentFilter) {
				$pq = $this->parentFilter->mergedQuery(false, $skipPreload);
				if ($skipPreload && count($q['preload'])) {
					$q = $pq;
				} else {
					$q['select'] = array_merge((array)$pq['select'], (array)$q['select']);
					if (isset($pq['where']['sql'])) {
						if (isset($q['where']['sql'])) {
							$q['where']['sql'] = $pq['where']['sql'].' AND '.$q['where']['sql'];
							$q['where']['params'] = array_merge((array)$pq['where']['params'], (array)$q['where']['params']);
						} else {
							$q['where']['sql'] = $pq['where']['sql'];
							$q['where']['params'] = $pq['where']['params'];
						}
					}
					$q['filters'] = array_merge((array)$pq['filters'], (array)$q['filters']);
					$q['tables'] = $this->mergeJoins((array)$pq['tables'], (array)$q['tables']);
					$q['preload'] = array_merge((array)$pq['preload'], (array)$q['preload']);
					$q['aggregates'] = array_merge((array)$pq['aggregates'], (array)$q['aggregates']);
					$q['groupby'] = array_merge((array)$pq['groupby'], (array)$q['groupby']);
					$q['converters'] = array_merge((array)$pq['converters'], (array)$q['converters']);
					if ($pq['order']) {
						if ($q['order'])
							$q['order'] = $pq['order'].', '.$q['order'];
						else
							$q['order'] = $pq['order'];
					}
					if ($pq['limit'] && !$q['limit']) $q['limit'] = $pq['limit'];
					if ($pq['offset'] && !$q['offset']) $q['offset'] = $pq['offset'];
					if ($pq['distinct']) $q['distinct'] = true;
				}

				if ($pq['debug']) $q['debug'] = true;
			}

			if ($applyDefaults && !$q['order']) {
				if ($defOrder = $this->model->getDefaultOrder()) {
					list($oclause, $ojoins) = $this->orderClause($defOrder);
					$q['order'] = $oclause;
					$q['tables'] = $this->mergeJoins($q['tables'], $ojoins ? $ojoins : array());
				}
				if ($q['preload']) {
					// Can't sort child element without sorting by parent first
					foreach ($q['preload'] as $pl) {
						$rfield = $this->model->_fields[$pl];
						if ($rfield instanceof RelationField && $defOrder = $rfield->getRelationModel()->getDefaultOrder()) {
							for ($i = 0; $i < count($defOrder); ++$i) {
								if ($defOrder[$i]{0} == '-' || $defOrder[$i]{0} == '+')
									$defOrder[$i] = $defOrder[$i]{0}.$pl.'.'.substr($defOrder[$i], 1);
								else
									$defOrder[$i] = $pl.'.'.$defOrder[$i];
							}

							// Always need to add sort by primary key in case the primary
							// order field is not unique, otherwise the child items
							// will not necessarily be consecutive.
							list ($pc, $pj) = $this->orderClause(array($this->model->_idField));
							if (!$q['order']) {
								$q['order'] = $pc;
							} else {
								$q['order'] .= ', '.$pc;
							}

							list($oclause, $ojoins) = $this->orderClause($defOrder);
							$q['order'] .= ', '.$oclause;

						}
					}
				}
			}

			$this->fullQuery = $q;

			return $q;

		}

		/**
		 * Generate the SQL and parameters for an array of filters, and cache
		 * them internally.
		 *
		 * @param Array $filters An array of field/value filters
		 * @param bool $negate Negate this filter (NOT())
		 * @param bool $or Combine multiple filters with OR rather than AND
		 */
		private function createFilterQuery($filters, $negate = false, $or = false) {

			if (count($filters)%2 == 1)
				throw new InvalidParametersException('Invalid parameter count: filter parameters come in pairs.');

			$sql = array();
			$params = array();

			for ($i = 0; $i < count($filters); $i = $i+2) {

				$operator = 'exact';
				$field = $filters[$i];
				$value = $filters[$i+1];

				if ($value instanceof Model)
					$value = $value->pk;

				if (strpos($field, ':') !== false)
					list($field, $operator) = explode(':', $field);

				if ($field == 'pk')
					$field = $this->model->_idField;

				// If field references other relations, add join clauses
				if (strpos($field, '.') !== false
						|| (isset($this->model->_fields[$field])
							&& $this->model->_fields[$field] instanceof RelationSetField)) {

					list($qualField, $joins, $currmodel, $field) =
						$this->getJoinInfo($field, ($or ? 'LEFT OUTER' : 'INNER'));

					if (count($joins))
						$this->query['tables'] = $this->mergeJoins($this->query['tables'], $joins);

					if (is_object($value))
						$value = $value->$field;

					if (is_array($value))
						for ($k = 0; $k < sizeof($value); ++$k)
							$value[$k] = $currmodel->_fields[$field]->convertToDbValue($value[$k]);
					elseif ($operator != 'isnull')
						$value = $currmodel->_fields[$field]->convertToDbValue($value);

					$fq = $this->getFilterQuery($currmodel->_table, $field, $value, $operator);

					if ($fq) {
						$sql[] = $fq['sql'];
						$params = array_merge($params, (array)$fq['params']);
					}

				} elseif (isset($this->model->_fields[$field])) {

					if (is_array($value))
						for ($k = 0; $k < sizeof($value); ++$k)
							$value[$k] = $this->model->_fields[$field]->convertToDbValue($value[$k]);
					elseif ($operator != 'isnull')
						$value = $this->model->_fields[$field]->convertToDbValue($value);
					$fq = $this->getFilterQuery($this->tableName, $field, $value, $operator);
					if ($fq) {
						$sql[] = $fq['sql'];
						$params = array_merge($params, (array)$fq['params']);
					}
				}
			}
			if (sizeof($sql))
				$this->query['where'] = array('sql' => ($negate?'NOT(':'(').implode(' '.($or?'OR':'AND').' ', $sql).')',
											'params' => $params);
		}

		/**
		 * Generate the SQL and parameters for an array of filters.
		 *
		 * @param string $table The table to query
		 * @param string $field The field name
		 * @param string $value The filter value
		 * @param string $operator The filter operator/modifier
		 * @return Array A two-element array containing the SQL and
		 *		parameters generated from the current filter chain
		 * @see QueryFilter::filter()
		 */
		private function getFilterQuery($table, $field, $value, $operator) {

			$q = $this->mergedQuery(true);
			$duplicate = false;

			foreach($q['filters'] as $filter) {
				if ($filter[0] == $table
						&& $filter[1] == $field
						&& $filter[2] == $value
						&& $filter[3] == $operator) {
					$duplicate = true;
					break;
				}
			}

			if ($duplicate)
				return null;
			else
				$this->query['filters'][] = array($table, $field, $value, $operator);

			$sql = null;
			$params = array();
			switch ($operator) {
				case 'exact':
					// Add to default field values for future insert
					//if (!$negate) $this->query['insert'][$field] = $value;
					$sql = '`'.$table.'`.`'.$field.'` = ?';
					$params[] = $value;
					break;
				case 'iexact':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = $value;
					break;
				case 'contains':
					$sql = '`'.$table.'`.`'.$field.'` LIKE ?';
					$params[] = '%'.$value.'%';
					break;
				case 'icontains':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = '%'.$value.'%';
					break;
				case 'gt':
					$sql = '`'.$table.'`.`'.$field.'` > ?';
					$params[] = $value;
					break;
				case 'gte':
					$sql = '`'.$table.'`.`'.$field.'` >= ?';
					$params[] = $value;
					break;
				case 'lt':
					$sql = '`'.$table.'`.`'.$field.'` < ?';
					$params[] = $value;
					break;
				case 'lte':
					$sql = '`'.$table.'`.`'.$field.'` <= ?';
					$params[] = $value;
					break;
				case 'in':
					if (!is_array($value) || !count($value))
						throw new InvalidParametersException('IN only accepts a non-zero length array parameter.');
					$tmp = '`'.$table.'`.`'.$field.'` IN (';
					foreach ($value as $v) {
						$tmp .= '?, ';
						$params[] = $v;
					}
					$tmp = substr($tmp, 0, -2) . ')';
					$sql = $tmp;
					break;
				case 'startswith':
					$sql = '`'.$table.'`.`'.$field.'` LIKE ?';
					$params[] = $value.'%';
					break;
				case 'istartswith':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = $value.'%';
					break;
				case 'endswith':
					$sql = '`'.$table.'`.`'.$field.'` LIKE ?';
					$params[] = '%'.$value;
					break;
				case 'iendswith':
					$sql = '`'.$table.'`.`'.$field.'` ILIKE ?';
					$params[] = '%'.$value;
					break;
				case 'range':
					if (!is_array($value) || count($value) != 2)
						throw new InvalidParametersException('RANGE only accepts a two length array parameter.');
					$sql = '`'.$table.'`.`'.$field.'` >= ? AND '.$table.'.'.$field.' <= ?';
					$params[] = $value[0];
					$params[] = $value[1];
					break;
				case 'isnull':
					$sql = '`'.$table.'`.`'.$field.'` IS '.(!$value ? 'NOT ' : '').'NULL';
					break;
			}
			return array('sql' => $sql, 'params' => $params);
		}

		/**
		 * Execute an arbitrary query on the databse table associated with
		 * this model.
		 *
		 * Bind parameters are specified with a "?" in the SQL string.
		 *
		 * Since this returns an ADORecordSet object, you will want to manually
		 * convert the result to a usable set of objects using createModels() or
		 * createRawHash().
		 *
		 * @params string $sql The SQL query to execute
		 * @params string $params The SQL parameters to bind the query
		 * @return ADORecordSet The record set object from ADODB
		 * @throws SQLException if the query fails
		 */
		public function query($sql, $params) {

			if (!$this->fullQuery)
				$this->mergedQuery(true);

			$qf =& $this->queryhandler->factory;
			$start = microtime(true);

			$qf->queryCount++;
			$c =& $qf->getConnection();
			if (!$qf->inTransaction())
				$c->BeginTrans();
			$stmt = $c->prepare($sql);
			$result = $c->execute($stmt, $params);

			if ($this->fullQuery['debug']) {
				$fullsql = $this->replaceSQLParams($sql, $params);
				if ($qf->logger) {
					$elapsed = microtime(true) - $start;
					$qf->logger->logDebug('Query ('.round($elapsed*1000,2).'ms): '.$fullsql);
				} else {
					print_r($fullsql);
					echo "<br><br>\n\n";
				}
			} elseif ($qf->logger) {
				$elapsed = microtime(true) - $start;
				$qf->logger->logDebug('Query ('.round($elapsed*1000,2).'ms): '.substr($sql, 0, 100).(strlen($sql)>100?'... <i>(more)</i>':''));
			}

			if ($result) {
				if (!$qf->inTransaction())
					$c->CommitTrans();
				return $result;
			} else {
				$error = $c->ErrorMsg();
				if (!$qf->inTransaction())
					$c->RollbackTrans();
				else
					$qf->fail();
				throw new SQLException('Database query failed: '.$error);
			}

		}

		private function replaceSQLParams($sql, $params) {
			$fullsql = $sql;
			$ppos = strpos($fullsql, '?');
			$pct = 0;
			while ($ppos !== FALSE) {
				$param = is_string($params[$pct])
					? '\''.$params[$pct].'\''
					: ($params[$pct] === null ? 'NULL' : strval($params[$pct]));
				$fullsql = substr($fullsql, 0, $ppos).$param.substr($fullsql, $ppos + 1);
				$ppos = strpos($fullsql, '?');
				$pct++;
			}
			return $fullsql;
		}

		/**
		 * Create a set of Model objects from a query result.
		 *
		 * @param ADORecordSet $result The record set result from ADODB
		 * @param Array $rawfields A list of fields to skip during type conversion
		 * @param string $map The field value to use as an array index if the
		 *		results should be returned as a mapped hash array
		 * @param Array $preload A list of related object fields that the calling
		 *		query attempted to preload
		 * @return Array A list of Model objects created from the result set
		 */
		public function &createModels($result, $rawfields, $map = null, $preload = null, $multimap = false) {
			$models = array();
			$lastid = null;
			$pk = $this->model->_idField;
			if ($map == 'pk')
				$map = $pk;
			$model = null;
			$preloadFields = array();
			while (!$result->EOF) {
				if (!$preload || $result->fields[$pk] != $lastid) {
					if ($model) {
						if (count($preloadFields) > 0) {
							foreach ($preloadFields as $field => $set) {
								if ($model->_fields[$field] instanceof RelationSetField) {
									$model->$field->relations->models = $set;
								} elseif (isset($set[0])) {
									$model->$field = $set[0];
								}
							}
							$preloadFields = array();
						}
						if ($map) {
							$mapval = $model->getPrimitiveFieldValue($map);
							if ($multimap) {
								if (!isset($models[$mapval]))
									$models[$mapval] = array();
								$models[$mapval][] = $model;
							} else
								$models[$mapval] = $model;
						} else
							$models[] = $model;
					}
					$cacheKey = get_class($this->model).':'
						.$this->model->_fields[$pk]->convertFromDbValue($result->fields[$pk]);
					if ($this->queryhandler->factory->cacheExists($cacheKey)) {
						$model = $this->queryhandler->factory->cacheGet($cacheKey);
					} else {
						$model = $this->queryhandler->create($result->fields, $rawfields, UPDATE_FROM_DB);
						$this->queryhandler->factory->cachePut($cacheKey, $model);
					}
				}

				$lastid = $result->fields[$pk];
				if ($preload) {
					foreach ($preload as $field) {

						$fo = $this->model->_fields[$field];
						if ($fo && $fo instanceof RelationField) {

							$relmodel = $fo->getRelationModel();
							$relobj = null;

							$relCacheKey = get_class($relmodel).':'
								.$relmodel->_fields[$relmodel->_idField]->convertFromDbValue(
									$result->fields['__'.$field.'__'.$relmodel->_idField]);

							if ($this->queryhandler->factory->cacheExists($relCacheKey)) {
								$relobj = $this->queryhandler->factory->cacheGet($relCacheKey);
							} else {
								$relfields = array();
								foreach($result->fields as $f => $v) {
									$prelen = strlen($field)+4;
									if (substr($f, 0, $prelen) == '__'.$field.'__')
										$relfields[substr($f, $prelen)] = $v;
								}
								$relobj = $relmodel->getQuery()->create($relfields, null, UPDATE_FROM_DB);
								if (!$relobj->pk) continue;
								$this->queryhandler->factory->cachePut(get_class($relobj).':'.$relobj->pk, $relobj);
							}

							if (!isset($preloadFields[$field]))
								$preloadFields[$field] = array();
							$preloadFields[$field][] = $relobj;

						}
					}
				}
				$result->MoveNext();
			}
			if ($model) {
				if (count($preloadFields) > 0) {
					foreach ($preloadFields as $field => $set) {
						if ($model->_fields[$field] instanceof RelationSetField) {
							$model->$field->relations->models = $set;
						} elseif (isset($set[0])) {
							$model->$field = $set[0];
						}
					}
					$preloadFields = array();
				}
				if ($map) {
					$mapval = $model->getPrimitiveFieldValue($map);
					if ($multimap) {
						if (!isset($models[$mapval]))
							$models[$mapval] = array();
						$models[$mapval][] = $model;
					} else
						$models[$mapval] = $model;
				} else
					$models[] = $model;
			}
			return $models;
		}

		/**
		 * Create a raw hash array from a query result.
		 *
		 * @param ADORecordSet $result The record set result from ADODB
		 * @param string $map The field value to use as an array index if the
		 *		results should be returned as a mapped hash array
		 * @param Array $preload A list of related object fields that the calling
		 *		query attempted to preload
		 * @param bool $multimap If a $map parameter is specified,
		 * 		make the returned map values an array of hash arrays.
		 *		Useful if you want to map by a field value that is not
		 *		unique across records.
		 * @return Array A list of hash arrays created from the result set
		 */
		public function createRawHash($result, $map = null, $preload = null, $multimap = false) {

			$hash = array();
			$lastid = null;
			$pk = $this->model->_idField;
			$record = null;

			if ($map == 'pk')
				$map = $pk;

			while (!$result->EOF) {

				$record = array();

				if ($preload) {
					foreach ($result->fields as $k => $v) {
						if ((strpos($k, '__')) === 0) {
							// Preload object
							list($junk, $field, $k) = explode('__', $k);
							if (!isset($record[$field]))
								$record[$field] = array();
							$record[$field][$k] = $v;
						} elseif (!$preload || !in_array($k, $preload))
							$record[$k] = $v;
					}
				} else
					$record = $result->fields;

				if ($map) {
					if ($multimap) {
						if (!isset($hash[$record[$map]]))
							$hash[$record[$map]] = array();
						$hash[$record[$map]][] = $record;
					} else
						$hash[$record[$map]] = $record;
				} else
					$hash[] = $record;

				$result->MoveNext();

			}

			return $hash;

		}

		/**
		 * Create a type-converted hash array from a query result.
		 *
		 * @param ADORecordSet $result The record set result from ADODB
		 * @param Array $rawfields A list of fields to skip during type conversion
		 * @param string $map The field value to use as an array index if the
		 *		results should be returned as a mapped hash array
		 * @param Array $preload A list of related object fields that the calling
		 *		query attempted to preload
		 * @param bool $multimap If a $map parameter is specified,
		 * 		make the returned map values an array of hash arrays.
		 *		Useful if you want to map by a field value that is not
		 *		unique across records.
		 * @return Array A list of hash arrays created from the result set
		 */
		public function createHash($result, $rawfields, $map = null, $preload = null, $multimap = false) {

			$hash = array();
			$lastid = null;
			$pk = $this->model->_idField;
			$record = null;

			if ($map == 'pk')
				$map = $this->model->_idField;

			while (!$result->EOF) {
				$model = $this->model;
				if ($this->model->_subclassField)
					$model = $this->queryhandler->getConcreteType($result->fields);
				$row =& $result->fields;
				if (!$preload || $row[$pk] != $lastid) {
					if ($record) {
						if ($map) {
							if ($multimap) {
								if (!isset($hash[$record[$map]]))
									$hash[$record[$map]] = array();
								$hash[$record[$map]][] = $record;
							} else
								$hash[$record[$map]] = $record;
						} else
							$hash[] = $record;
					}
					$record = $this->createHashFromRow($row, $model, $rawfields, $preload);
				}
				if (isset($row[$pk]))
					$lastid = $row[$pk];
				if ($preload) {
					foreach ($preload as $field) {
						$fo = $model->_fields[$field];
						if ($fo && $fo instanceof RelationField) {
							$relmodel = $fo->getRelationModel();
							$relfields = array();
							foreach($result->fields as $f => $v) {
								$prelen = strlen($field)+4;
								if (substr($f, 0, $prelen) == '__'.$field.'__')
									$relfields[substr($f, $prelen)] = $v;
							}
							if ($fo instanceof RelationSetField) {
								if (!isset($record[$field]))
									$record[$field] = array();
								if (!$relfields[$pk]) continue;
								$record[$field][] = $this->createHashFromRow($relfields, $relmodel);
							} else {
								if (!$relfields[$pk]) continue;
								$record[$field] = $this->createHashFromRow($relfields, $relmodel);
							}
						}
					}
				}
				$result->MoveNext();
			}
			if ($record) {
				if ($map) {
					if ($multimap) {
						if (!isset($hash[$record[$map]]))
							$hash[$record[$map]] = array();
						$hash[$record[$map]][] = $record;
					} else
						$hash[$record[$map]] = $record;
				} else
					$hash[] = $record;
			}
			return $hash;
		}

		/**
		 * Create a hash array representation a single query record.
		 *
		 * @param $row A row array from ADODB
		 * @param $model The prototype model to use for field type conversion
		 * @param $rawfields A list of fields to skip during type conversion
		 * @param Array $preload A list of related object fields that the calling
		 *		query attempted to preload
		 * @return Array A hash array created from the result row
		 */
		public function createHashFromRow($row, $model, $rawfields = null, $preload = null) {

			$record = array();
			$fields = $model->_dbFields;

			foreach ($fields as $field => $def) {
				if (!$preload || !in_array($field, $preload) || !$def instanceof RelationField) {
					if (array_key_exists($field, $row)) {
						$raw = $rawfields && ($rawfields == ALLFIELDS || in_array($field, $rawfields));
						$value = $raw ? $row[$field] : $def->convertFromDbValue($row[$field]);
						if ($value instanceof Model)
							$record[$field] = $value->getFieldValues();
						else
							$record[$field] = $value;
					}
				}
			}

			// Format aggregate fields
			if ($this->fullQuery && count($cf = $this->fullQuery['converters']) > 0)
				foreach ($cf as $f => $d)
					if (array_key_exists($f, $row))
						$record[$f] = $d->convertFromDbValue($row[$f]);

			return $record;
		}

		/**
		 * Return a count of affected rows from a query.
		 *
		 * Unreliable or unsupported for many drivers.  For drivers that
		 * are supported, note this for updates this does not return
		 * the number of rows that MATCHED, only the number of rows that
		 * were CHANGED.
		 *
		 * @return int The number of rows that were modified.
		 */
		private function affectedRows() {
			return $this->queryhandler->getConnection()->Affected_Rows();
		}

		/**
		 * Retrieve the primary key ID of the last row inserted.
		 * @return mixed The primary key of the last inserted record
		 */
		private function insertId() {
			return $this->queryhandler->getConnection()->Insert_ID();
		}

		/**
		 * Get the most recent error message reported by ADODB.
		 * @return string The last  error message.
		 */
		private function getError() {
			return $this->queryhandler->getConnection()->ErrorMsg();
		}

		/**
		 * Merged two join definition arrays, giving preference to
		 * INNER JOIN definitions in case of a conflict.
		 */
		private function mergeJoins($tables1, $tables2) {
			foreach ($tables1 as $t1 => $j1) {
				if (isset($tables2[$t1])) {
					if ($j1 == 'INNER JOIN')
						unset($tables2[$t1]);
					elseif ($tables2[$t1] == 'INNER JOIN')
						unset($tables1[$t1]);
				}
			}
			return array_merge($tables1, $tables2);
		}

		// Iterator functions
		public function rewind() {
			if (!$this->models) $this->select();
			reset($this->models);
		}
		public function current() {
			if (!$this->models) $this->select();
			return current($this->models);
		}
		public function key() {
			if (!$this->models) $this->select();
			return key($this->models);
		}
		public function next() {
			if (!$this->models) $this->select();
			return next($this->models);
		}
		public function valid() {
			if (!$this->models) $this->select();
			return current($this->models) !== false;
		}

		// ArrayAccess
		public function offsetUnset($index) {
			// Blocked, can't unset model
		}
		public function offsetSet($index, $value) {
			// Blocked, can't set model
		}
		public function offsetGet($index) {
			if (!$this->models) $this->select();
			return $this->models[$index];
		}
		public function offsetExists($index) {
			if (!$this->models) $this->select();
			return array_key_exists($index, $this->models);
		}

		/**
		 * Get the number of matching rows for the current query.
		 * If the query has been executed already, it will return the
		 * number of records in the local cache.  Otherwise, it will
		 * query the database to determine the total number of matching
		 * records (with QueryFilter::sqlcount()).
		 *
		 * @return int The matching record count
		 * @see QueryFilter::sqlcount()
		 */
		public function count() {

			if (!$this->fullQuery)
				$this->mergedQuery(true);

			// Offset/limit screws up SQL COUNT
			if ($this->fullQuery['limit']
					|| $this->fullQuery['offset']) {
				try {
					$this->select();
				} catch (InvalidUsageException $e) {
					// Can't select from root filter
				}
			}

			if ($this->models) {
				// Already executed a query, return actual results
				return count($this->models);
			} else {
				// Use SQL COUNT() function to get row count without modifying current query
				return $this->sqlcount();
			}

		}

		/**
		 * Get the underlying QueryHandler object for this filter.
		 * @return QueryHandler The root query handler
		 */
		public function getHandler() {
			return $this->queryhandler;
		}

		/**
		 * Get the underlying QueryFactory object for this filter.
		 * @return QueryFactory The query factory
		 */
		public function getQueryFactory() {
			return $this->factory;
		}

	}
