<?php namespace Obie\Models;
use \Obie\Formatters\EnglishNoun;
use \Obie\Formatters\Casing;

class BaseModel {
	const VALID_TYPES = [
		'int', 'integer',
		'bool', 'boolean',
		'float', 'double',
		'string',
		'date'
	];

	protected $_error                = null;
	protected $_new                  = true;
	protected $_data                 = [];
	protected $_original_data        = [];
	protected $_modified_columns     = [];
	protected static $_default_db    = null;
	protected static $_default_ro_db = null;

	// Model definition methods

	public static function define($column, string $type = null) {
		if ($type !== null) {
			if (is_string($column)) {
				$column = [$column];
			}
			if (is_array($column)) {
				$old = $column;
				$column = [];
				foreach ($old as $key) {
					$column[$key] = $type;
				}
			} else {
				throw new \InvalidArgumentException('Column must be a string or array of "column" => "type"');
			}
		}
		if (is_array($column)) {
			foreach ($column as $key => $type) {
				if (!is_string($key) || !is_string($type)) {
					throw new \InvalidArgumentException('Column must be a string or array of "column" => "type"');
				}
				$type = strtolower($type);
				if (!in_array($type, self::VALID_TYPES, true)) {
					throw new \InvalidArgumentException('Type must be a member of BaseModel::VALID_TYPES');
				}
				static::$columns[$key] = $type;
			}
		} else {
			throw new \InvalidArgumentException('Column must be a string or array of "column" => "type"');
		}
	}

	protected static function initPrimaryKeys() {
		if (count(static::$pk) === 0) {
			$class_name = get_called_class();
			$columns = static::getAllColumns();

			static::$pk = [];
			$stmt = static::getReadOnlyDatabase()->prepare('SHOW KEYS FROM ' . static::getEscapedSource() . ' WHERE `Key_name` = \'PRIMARY\'');
			$stmt->execute();
			foreach ($stmt->fetchAll() as $row) {
				// Ensure that primary key exists as a public variable of this model
				if (!in_array($row['Column_name'], $columns)) {
					throw new \Exception("Primary key \"{$row['Column_name']}\" not defined in model \"$class_name\"");
				}

				static::$pk[] = $row['Column_name'];
			}
		}
	}

	public static function getPrimaryKeys() {
		static::initPrimaryKeys();
		return static::$pk;
	}

	public static function getAllColumns(): array {
		return array_keys(static::$columns);
	}

	public static function columnExists(string $name): bool {
		return array_key_exists($name, static::$columns);
	}

	public static function columnsExist(string ...$names): bool {
		return empty(array_diff($names, static::getAllColumns()));
	}

	protected static function canGetOrSet(string $name): bool {
		return static::columnExists($name);
	}

	protected static function initSource() {
		if (static::$source === null) {
			static::$source_singular = EnglishNoun::classNameToSingular(get_called_class());
			static::$source = EnglishNoun::toPlural(static::$source_singular);
		}
		if (static::$source_singular === null) {
			static::$source_singular = EnglishNoun::toSingular(static::$source);
		}
	}

	public static function setSource(string $source, string $source_singular = null) {
		static::$source = $source;
		static::$source_singular = $source_singular;
		static::initSource();
	}

	public static function getSource() {
		static::initSource();
		return static::$source;
	}

	public static function getEscapedSource() {
		return ModelHelpers::getEscapedSource(static::getSource());
	}

	public static function setDatabase(\PDO $db) {
		static::$db = $db;
	}

	public static function getDatabase() {
		if (static::$db === null) {
			return self::getDefaultDatabase();
		}
		return static::$db;
	}

	public static function setDefaultDatabase(\PDO $db) {
		if (property_exists(get_called_class(), '_default_db')) {
			static::$_default_db = $db;
		} else {
			self::$_default_db = $db;
		}
	}

	public static function getDefaultDatabase() {
		if (property_exists(get_called_class(), '_default_db')) {
			return static::$_default_db;
		}
		return self::$_default_db;
	}

	public static function setReadOnlyDatabase(\PDO $db) {
		static::$ro_db = $db;
	}

	public static function getReadOnlyDatabase() {
		if (static::$ro_db === null) {
			return self::getDefaultReadOnlyDatabase();
		}
		return static::$ro_db;
	}

	public static function setDefaultReadOnlyDatabase(\PDO $db) {
		if (property_exists(get_called_class(), '_default_ro_db')) {
			static::$_default_ro_db = $db;
		} else {
			self::$_default_ro_db = $db;
		}
	}

	public static function getDefaultReadOnlyDatabase() {
		if (property_exists(get_called_class(), '_default_ro_db') && static::$_default_ro_db !== null) {
			return static::$_default_ro_db;
		} elseif (self::$_default_ro_db !== null) {
			return self::$_default_ro_db;
		}
		return self::getDefaultDatabase();
	}

	// Statement building methods

	protected static function buildSelect(array $options = []) {
		if (!array_key_exists('what', $options)) {
			$options['what'] = static::getAllColumns();
		}
		if (is_array($options['what'])) {
			$options['what'] = ModelHelpers::getEscapedList($options['what'], static::getSource());
		}
		if (!is_string($options['what'])) {
			throw new \InvalidArgumentException('What must be a string or array of strings');
		}
		$query = 'SELECT ' . $options['what'] . ' FROM ' . static::getEscapedSource();
		if (array_key_exists('join', $options)) {
			if (is_string($options['join'])) {
				$options['join'] = [$options['join']];
			}
			if (!is_array($options['join'])) {
				throw new \InvalidArgumentException('Join must be a string or array of strings');
			}
			foreach ($options['join'] as $join) {
				$query .= ' ' . $join;
			}
		}
		if (array_key_exists('with', $options)) {
			$class_name = get_called_class();
			if (!method_exists($class_name, 'getJoin')) {
				throw new \InvalidArgumentException("With can only be used on models with relations. Model \"$class_name\" does not have relations.");
			}
			if (is_string($options['with'])) {
				$options['with'] = [$options['with']];
			}
			if (!is_array($options['with'])) {
				throw new \InvalidArgumentException('With must be a string or array of strings');
			}
			foreach ($options['with'] as $with) {
				$join = static::getJoin($with);
				if (!$join) {
					throw new \InvalidArgumentException("Relation \"$with\" does not exist on model \"$class_name\"");
				}
				$query .= ' ' . $join;
			}
		}
		if (array_key_exists('conditions', $options)) {
			if (is_string($options['conditions'])) {
				$options['conditions'] = [$options['conditions']];
			}
			if (!is_array($options['conditions'])) {
				throw new \InvalidArgumentException('Conditions must be a string or array of strings');
			}
			if (count($options['conditions']) > 0) {
				$non_empty_conditions = [];
				foreach ($options['conditions'] as $condition) {
					if (!is_string($condition)) {
						throw new \InvalidArgumentException('Conditions must be a string or array of strings');
					}
					if (strlen($condition) > 0) {
						$non_empty_conditions[] = $condition;
					}
				}
				if (count($non_empty_conditions) > 0) {
					$query .= ' WHERE ' . (count($non_empty_conditions) > 1 ? '(' : '') . '(' . implode(') AND (', $non_empty_conditions) . ')' . (count($non_empty_conditions) > 1 ? ')' : '');
				}
			}
		}
		foreach (['group', 'order'] as $expr) {
			if (array_key_exists($expr, $options)) {
				$query .= strtoupper($expr) . ' BY ';
				if (is_array($options[$expr])) {
					$is_first = true;
					foreach ($options[$expr] as $key => $type) {
						if (!is_string($key) && !is_int($key)) {
							throw new \InvalidArgumentException(ucfirst($expr) . ' must be an associative array of (col_name | position) => ("ASC" | "DESC")');
						}
						if (!is_string($type)) {
							throw new \InvalidArgumentException(ucfirst($expr) . ' must be an associative array of (col_name | position) => ("ASC" | "DESC")');
						}
						$type = strtoupper($type);
						if ($type !== 'ASC' && $type !== 'DESC') {
							throw new \InvalidArgumentException(ucfirst($expr) . ' must be an associative array of (col_name | position) => ("ASC" | "DESC")');
						}
						if (!$is_first) {
							$query .= ',';
						}
						if (is_int($key)) {
							$query .= (string)$key . ' ' . $type;
						} else {
							$query .= static::getEscapedSource() . '.`' . $key . '` ' . $type;
						}
						$is_first = false;
					}
				} elseif (is_string($options[$expr])) {
					$query .= $options[$expr];
				} else {
					throw new \InvalidArgumentException(ucfirst($expr) . ' must be an associative array of (col_name | position) => ("ASC" | "DESC")');
				}
			}
		}
		if (array_key_exists('limit', $options)) {
			if (!is_int($options['limit'])) {
				throw new \InvalidArgumentException('Limit must be an int');
			}
			$query .= ' LIMIT ';
			if (array_key_exists('offset', $options)) {
				if (!is_int($options['offset'])) {
					throw new \InvalidArgumentException('Offset must be an int');
				}
				$query .= (string)$options['offset'] . ',';
			}
			$query .= (string)$options['limit'];
		}
		if (array_key_exists('for', $options) && is_array($options['for'])) {
			foreach (['update', 'share'] as $for_type) {
				if (array_key_exists($for_type, $options['for'])) {
					if (is_string($options['for'][$for_type])) {
						$options['for'][$for_type] = [$options['for'][$for_type]];
					}
					if (!is_array($options['for'][$for_type]) && !is_bool($options['for'][$for_type])) {
						throw new \InvalidArgumentException(ucfirst($for_type) . ' must be an indexed array of table names or a boolean');
					}
					if (is_bool($options['for'][$for_type]) && $options['for'][$for_type] || is_array($options['for'][$for_type]) && count($options['for'][$for_type]) > 0) {
						if (is_bool($options['for'][$for_type]) && $for_type === 'share') {
							$query .= ' LOCK IN SHARE MODE';
						} else {
							$query .= ' FOR ' . strtoupper($for_type);
							if (is_array($options['for'][$for_type]) && count($options['for'][$for_type]) > 0) {
								$query .= ' OF `' . implode('`,`') . '`';
							}
							if (array_key_exists('nowait', $options['for'])) {
								if (!is_bool($options['for']['nowait'])) {
									throw new \InvalidArgumentException('Nowait must be a boolean');
								}
								if ($options['for']['nowait']) {
									$query .= ' NOWAIT';
								}
							}
						}
					}
				}
			}
		}

		return $query;
	}

	protected static function buildStatement(string $query, array $options) {
		$db   = array_key_exists('read_only', $options) && $options['read_only'] ? static::getReadOnlyDatabase() : static::getDatabase();
		$stmt = $db->prepare($query);
		if (array_key_exists('bind', $options)) {
			if (!is_array($options['bind'])) {
				throw new \InvalidArgumentException('Bind must be an indexed array');
			}
			$is_seq = $options['bind'] !== [] && array_keys($options['bind']) === range(0, count($options['bind']) - 1);
			foreach ($options['bind'] as $key => $val) {
				if ($is_seq || is_int($key)) {
					$key += 1;
				}
				static::bindValue($stmt, $key, $val);
			}
		}
		return $stmt;
	}

	// Initializers

	function __construct(array $data = []) {
		foreach ($data as $key => $val) {
			$this->set($key, $val, false);
		}
	}

	public static function findFirst($options = null) {
		if (is_string($options)) {
			$options = ['conditions' => $options];
		}
		if ($options === null) {
			$options = [];
		}
		if (!is_array($options)) {
			throw new \InvalidArgumentException('Options must be an array of options or a string of conditions');
		}

		// Enforce limit of one row
		$options['limit'] = 1;
		unset($options['offset']);

		// Enforce read only option
		$options['read_only'] = true;

		// Build and execute statement
		$stmt = static::buildStatement(static::buildSelect($options), $options);
		static::$find_error = null;
		try {
			$stmt->execute();
		} catch (\PDOException $e) {
			static::$find_error = $e;
			return false;
		}
		$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		if (count($result) === 0) return false;

		// Create model instance
		$model = new static();
		foreach ($result[0] as $key => $val) {
			$model->set($key, $val, false);
		}

		// Run post-load hooks
		foreach (get_class_methods($model) as $name) {
			if (substr($name, 0, 9) === 'afterLoad') {
				$model->{$name}();
			}
		}

		$model->forceCleanState();
		return $model;
	}

	public static function find($options = null) {
		if (is_string($options)) {
			$options = ['conditions' => $options];
		}
		if ($options === null) {
			$options = [];
		}
		if (!is_array($options)) {
			throw new \InvalidArgumentException('Options must be an array of options or a string of conditions');
		}

		// Enforce read only option
		$options['read_only'] = true;

		// Build and execute statement
		$stmt = static::buildStatement(static::buildSelect($options), $options);
		static::$find_error = null;
		try {
			$stmt->execute();
		} catch (\PDOException $e) {
			static::$find_error = $e;
			return false;
		}
		$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		if (count($result) === 0) return false;

		// Collect results into a ModelCollection of model instances
		$models = new ModelCollection();
		foreach ($result as $row) {
			$model = new static();
			foreach ($row as $key => $val) {
				$model->set($key, $val, false);
			}

			// Run post-load hooks
			foreach (get_class_methods($model) as $name) {
				if (substr($name, 0, 9) === 'afterLoad') {
					$model->{$name}();
				}
			}

			$model->forceCleanState();
			$models[] = $model;
		}

		return $models;
	}

	public static function count(array $options = []) {
		// Enforce read only option
		$options['read_only'] = true;

		// Enforce what option
		$options['what'] = 'COUNT(' . ModelHelpers::getEscapedList(static::getPrimaryKeys(), static::getSource()) . ') AS `rowcount`';

		// Build and execute statement which gets row count efficiently with given options
		$stmt = static::buildStatement(static::buildSelect($options), $options);
		$stmt->execute();
		$count = $stmt->fetch(\PDO::FETCH_ASSOC)['rowcount'];
		if (is_string($count)) $count = (int)$count;
		return $count;
	}

	public static function getLastFindError() {
		return static::$find_error;
	}

	// Database interaction methods

	public function load() {
		// Run pre-load hooks
		foreach ($this->getHooks('beforeLoad') as $name) {
			if ($this->{$name}()) return true;
		}

		// Build query options
		$pk_list = static::getPrimaryKeys();
		$options = [
			'conditions' => ModelHelpers::getEscapedWhere($pk_list, static::getSource()),
			'bind'       => [],
			'read_only'  => true
		];
		foreach ($pk_list as $key) {
			$options['bind'][] = $this->get($key, false);
		}

		// Build and execute statement
		$stmt = static::buildStatement(static::buildSelect($options), $options);
		$this->_error = null;
		try {
			$stmt->execute();
		} catch (\PDOException $e) {
			$this->_error = $e;
			return false;
		}
		$result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		if (count($result) === 0) return false;

		foreach ($result[0] as $key => $val) {
			$this->set($key, $val, false);
		}

		// Run post-load hooks
		foreach ($this->getHooks('afterLoad') as $name) {
			$this->{$name}();
		}

		$this->forceCleanState();
		return true;
	}

	public function save() {
		// Run pre-save hooks
		foreach ($this->getHooks('beforeSave') as $name) {
			if ($this->{$name}()) return true;
		}

		if ($this->_new) {
			// Data has not previously been loaded, insert new row
			$retval = $this->create();
		} else {
			// Update existing row
			$retval = $this->update();
		}

		if ($retval) {
			// Run post-save hooks
			foreach ($this->getHooks('afterSave') as $name) {
				$this->{$name}();
			}
		}

		return $retval;
	}

	public function create() {
		// Run pre-create hooks
		foreach ($this->getHooks('beforeCreate') as $name) {
			if ($this->{$name}()) return true;
		}

		// Update database
		$stmt = static::getDatabase()->prepare('INSERT INTO ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_modified_columns, static::getSource()));
		$this->bindValues($stmt, $this->_modified_columns);
		$this->_error = null;
		try {
			$retval = $stmt->execute();
		} catch (\PDOException $e) {
			$this->_error = $e;
			return false;
		}
		$id = static::getDatabase()->lastInsertId();

		if ($retval) {
			if (count(static::getPrimaryKeys()) === 1) {
				$this->set(static::getPrimaryKeys()[0], $id, false);
			}

			// Run post-create hooks
			foreach ($this->getHooks('afterCreate') as $name) {
				$this->{$name}();
			}

			$this->forceCleanState();
		}

		return $retval;
	}

	public function update() {
		// Run pre-update hooks
		foreach ($this->getHooks('beforeUpdate') as $name) {
			if ($this->{$name}()) return true;
		}

		// Update database
		$pk_list = static::getPrimaryKeys();
		$stmt = static::getDatabase()->prepare('UPDATE ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_modified_columns, static::getSource()) . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list, static::getSource()) . ' LIMIT 1');
		$i = $this->bindValues($stmt, $this->_modified_columns);
		$this->bindValues($stmt, $pk_list, $i);
		$this->_error = null;
		try {
			$retval = $stmt->execute();
		} catch (\PDOException $e) {
			$this->_error = $e;
			return false;
		}

		if ($retval) {
			// Run post-update hooks
			foreach ($this->getHooks('afterUpdate') as $name) {
				$this->{$name}();
			}

			$this->forceCleanState();
		}

		return $retval;
	}

	public function delete() {
		foreach ($this->getHooks('beforeDelete') as $name) {
			if ($this->{$name}()) return true;
		}

		$pk_list = static::getPrimaryKeys();
		$stmt = static::getDatabase()->prepare('DELETE FROM ' . static::getEscapedSource() . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list, static::getSource()) . ' LIMIT 1');
		$this->bindValues($stmt, $pk_list);
		$this->_error = null;
		try {
			$retval = $stmt->execute();
		} catch (\PDOException $e) {
			$this->_error = $e;
			return false;
		}

		if ($retval) {
			// Run post-delete hooks
			foreach ($this->getHooks('afterDelete') as $name) {
				$this->{$name}();
			}

			$this->forceCleanState();
			$this->_new = true;
		}

		return $retval;
	}

	protected function bindValues(\PDOStatement $stmt, array $keys, int $i = 1): int {
		foreach ($keys as $key) {
			$val = $this->get($key, false);
			static::bindValue($stmt, $i++, $val);
		}
		return $i;
	}

	protected static function bindValue(\PDOStatement $stmt, string|int $key, mixed $val) {
		if (is_int($val)) {
			$type = \PDO::PARAM_INT;
		} elseif (is_bool($val)) {
			$type = \PDO::PARAM_INT;
			$val = (int)$val;
		} elseif ($val === null) {
			$type = \PDO::PARAM_NULL;
		} else {
			$type = \PDO::PARAM_STR;
		}
		if ($val instanceof \DateTime) {
			$val = $val->format('Y-m-d H:i:s');
		}
		$stmt->bindValue($key, $val, $type);
	}

	public function forceCleanState() {
		$this->_new = false;
		$this->_original_data  = [];
		$this->_modified_columns = [];
		return $this;
	}

	public function getLastError() {
		return $this->_error;
	}

	// Data methods

	protected function getHooks(string $hook_prefix) {
		$hooks = [];
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, strlen($hook_prefix)) === $hook_prefix) {
				$hooks[] = $name;
			}
		}
		return $hooks;
	}

	public function modified() {
		return !empty($this->_modified_columns);
	}

	public function modifiedColumns() {
		return $this->_modified_columns;
	}

	public function originalData(?string $key = null) {
		if ($key !== null) {
			if (!static::columnExists($key)) {
				$class_name = get_called_class();
				throw new \Exception("Column $key is not defined in model $class_name");
			}
			return array_key_exists($key, $this->_original_data) ? $this->_original_data[$key] : $this->_data[$key];
		}
		return array_merge($this->_data, $this->_original_data);
	}

	public function undoModifications() {
		$this->_data = $this->originalData();
	}

	public function toArray() {
		return $this->_data;
	}

	public function get(string $key, bool $hooks = true) {
		if (!static::columnExists($key)) {
			$class_name = get_called_class();
			throw new \Exception("Column $key is not defined in model $class_name");
		}
		if (!array_key_exists($key, $this->_data)) return null;
		$value = $this->_data[$key];
		if ($hooks) {
			foreach ($this->getHooks('beforeGet') as $name) {
				// hook arguments: key, value, is_relation, type
				$value = $this->{$name}($key, $value, false, static::$columns[$key]);
			}
		}
		return $value;
	}

	public function __get(string $key) {
		return $this->get($key);
	}

	public function set(string $key, $value, bool $hooks = true) {
		if (!static::columnExists($key)) {
			$class_name = get_called_class();
			throw new \Exception("Column $key is not defined in model $class_name");
		}
		$type = static::$columns[$key];
		if ($hooks) {
			foreach ($this->getHooks('beforeSet') as $name) {
				$value = $this->{$name}($key, $value, $type);
			}
		}
		$original_data = $this->get($key, false);
		if ($value === null) {
			$this->_data[$key] = null;
		} elseif ($type === 'date') {
			if ($value instanceof \DateTime) {
				$this->_data[$key] = $value;
			} elseif (is_string($value)) {
				$this->_data[$key] = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
			} elseif (is_int($value)) {
				$this->_data[$key] = (new \DateTime)->setTimestamp($value);
			} else {
				throw new \InvalidArgumentException('Date values must be an instance of DateTime, a string or an int');
			}
		} else {
			settype($value, $type);
			$this->_data[$key] = $value;
		}
		if (!in_array($key, $this->_modified_columns)) {
			$this->_original_data[$key] = $original_data;
			$this->_modified_columns[] = $key;
		}
		if ($hooks) {
			foreach ($this->getHooks('afterSet') as $name) {
				$this->{$name}($key, $value, $type);
			}
		}
		return $this;
	}

	public function __set(string $key, $value) {
		$this->set($key, $value);
	}

	public function __call(string $function_name, array $arguments) {
		$parts = explode('_', Casing::camelToSnake($function_name), 2);
		$method = $parts[0];
		$key = count($parts) > 1 ? $parts[1] : '';
		if (static::canGetOrSet($key)) {
			switch ($method) {
				case 'get':
					return $this->__get($key);
				case 'set':
					if (count($arguments) === 0) {
						throw new \InvalidArgumentException('Setters must be called with a key and value');
					}
					$this->__set($key, $arguments[0]);
					return $this;
			}
		}
		$class_name = get_called_class();
		throw new \Exception("Method $method not defined in model $class_name");
	}
}
