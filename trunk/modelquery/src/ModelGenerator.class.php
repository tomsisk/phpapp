<?php
	class ModelGenerator {
		
		private $introspector = null;

		public function __construct($dbtype = 'mysql') {
			switch ($dbtype) {
				case 'mysql':
					$this->introspector = new MysqlIntrospector();
					break;
				default:
					throw new Exception('Unsupported database: '.$dbtype);
			}
		}

		public function setDatabase($host, $database, $username = null, $password = null) {
			$this->introspector->setDatabase($host, $database, $username, $password);
		}

		public function generateModels($outputdir, $models = null) {
			if (!is_dir($outputdir))
				throw new Exception('Directory does not exist: '.$outputdir);

			$defs = $this->introspector->createModelDefinitions($models);

			if ($defs) {
				foreach ($defs as $table => $columns) {
					$model = $this->makeModelName($table);
					$filename = $outputdir.'/'.$model.'.class.php';

					if (file_exists($filename))
						continue;

					$fh = fopen($filename, 'w');
					if (!$fh) {
						echo 'Could not open file for writing: '.$filename;
						continue;
					}

					echo 'Generating model for '.$table."...\n";
					fwrite($fh, "<?php\n");
					fwrite($fh, "	class $model extends Model {\n");
					fwrite($fh, "		public function configure() {\n");
					foreach ($columns as $column) {
						$field = null;
						$params = array('\''.$this->makeModelName($column['field'], ' ', false).'\'');
						$options = array();
						if ($column['pk'])
							$options['pk'] = 'true';
						elseif ($column['required'])
							$options['required'] = 'true';
						if ($column['references']) {
							$field = 'ManyToOneField';
							$params[] = '\''.$this->makeModelName($column['references']).'\'';
						} else {
							switch($column['type']) {
								case 'char':
									$field = 'CharField';
									$params[] = $column['size'];
									if ($column['default'] !== null)
										$options['default'] = '\''.$column['default'].'\'';
									break;
								case 'text';
									$field = 'TextField';
									if ($column['default'] !== null)
										$options['default'] = '\''.$column['default'].'\'';
									break;
								case 'bool':
									$field = 'BooleanField';
									if ($column['default'] !== null)
										$options['default'] = $column['default'] ? 'true' : 'false';
									break;
								case 'int':
									$field = 'IntegerField';
									if ($column['default'] !== null)
										$options['default'] = $column['default'];
									break;
								case 'float':
									$field = 'FloatField';
									if ($column['default'] !== null)
										$options['default'] = $column['default'];
									break;
								case 'datetime':
									$field = 'DateTimeField';
									if ($column['default'] !== null)
										$options['default'] = $column['default'];
									break;
							}
						}
						fwrite($fh, '			$this->'.$column['field'].' = new '.$field.'('.implode(', ', $params));
						if (count($options)) {
							fwrite($fh, ', array(');
							$optct = 1;
							foreach ($options as $name => $value)
								fwrite($fh, '\''.$name.'\' => '.$value.($optct++<count($options)?', ':''));
							fwrite($fh, ')');
						}
						fwrite($fh, ");\n");
					}
					fwrite($fh, "		}\n");
					fwrite($fh, "	}\n");
					fclose($fh);
				}
			}
		}

		protected function makeModelName($table, $spacer = '', $plural = true) {
			if ($plural && substr($table, -1) == 's') {
				// Make singular
				if (substr($table, -3) == 'ies')
					$name = substr($table, 0, -3).'y';
				elseif (substr($table, -3) == 'ses')
					$name = substr($table, 0, -2);
				else
					$name = substr($table, 0, -1);
			} else {
				$name = $table;
			}
			$parts = explode('_', $name);
			foreach ($parts as &$part)
				$part = ucfirst($part);
			return implode($spacer, $parts);
		}

	}

	abstract class DatabaseIntrospector {

		protected $host;
		protected $database;
		protected $username;
		protected $password;

		public abstract function createModelDefinitions($models = null);

		public function setDatabase($host, $database, $username = null, $password = null) {
			$this->host = $host;
			$this->database = $database;
			$this->username = $username;
			$this->password = $password;
		}

	}

	class MysqlIntrospector extends DatabaseIntrospector {
		
		public function createModelDefinitions($models = null) {

			$db = mysql_connect($this->host, $this->username, $this->password);
			mysql_select_db($this->database);

			$error = mysql_error();
			if ($error)
				throw new Exception($error);

			$tabledefs = array();
			$tables = mysql_query('SHOW TABLES;', $db);
			if ($tables) {
				while ($table = mysql_fetch_row($tables)) {
					if ($models && is_array($models) && !in_array($table[0], $models))
						continue;
					$columns = array();
					$stmts = mysql_query('SHOW COLUMNS FROM '.$table[0], $db);
					if ($stmts) {
						while ($stmt = mysql_fetch_assoc($stmts)) {
							list ($type, $size) = explode('(', $stmt['Type']);
							$default = $stmt['Default'];
							$required = $stmt['Null'] == 'NO';
							if ($size) $size = substr($size, 0, -1);
							switch($type) {
								case 'char':
								case 'varchar':
									$type = 'char';
									if ($stmt['Default'] !== null)
										$default = $stmt['Default'];
									break;
								case 'tinytext';
								case 'mediumtext';
								case 'longtext';
									$type = 'text';
									if ($stmt['Default'] !== null)
										$default = $stmt['Default'];
									break;
								case 'tinyint':
									if ($size == 1) {
										$type = 'bool';
										if ($stmt['Default'] !== null)
											$default = $stmt['Default'] == '1';
										break;
									}
								case 'bigint':
								case 'mediumint':
									$type = 'int';
									if ($stmt['Default'] !== null)
										$default = intval($default);
									break;
								case 'decimal':
									$type = 'float';
									if ($stmt['Default'] !== null)
										$default = floatval($default);
									break;
								case 'datetime':
								case 'timestamp':
									$type = 'datetime';
									if ($stmt['Default'] !== null) {
										if ($default != 'CURRENT_TIMESTAMP')
											$default = strtotime($default);
										else
											$default = $stmt['Default'];
									}
							}
							$column = array('field' => $stmt['Field'],
											'type' => $type,
											'size' => $size,
											'pk' => $stmt['Key'] == 'PRI',
											'required' => $required,
											'default' => $default);
							$columns[$column['field']] = $column;
						}
						mysql_free_result($stmts);
					}
					$stmts = mysql_query('SHOW CREATE TABLE '.$table[0], $db);
					if ($stmts) {
						$stmt = mysql_fetch_row($stmts);
						$createsql = $stmt[1];
						mysql_free_result($stmts);
						$matches = array();
						$matchct = preg_match_all('/FOREIGN KEY \(`([^`]*)`\) REFERENCES `([^`]*)` \(`([^`]*)`\)/m', $createsql, $matches);
						if ($matchct) {
							for ($i = 0; $i < $matchct; ++$i)
								$columns[$matches[1][$i]]['references'] = $matches[2][$i];
						}
					}
					$tabledefs[$table[0]] = $columns;
				}
				mysql_free_result($tables);
			}

			mysql_close($db);
			return $tabledefs;
		}
	}
