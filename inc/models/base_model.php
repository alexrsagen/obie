<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

class BaseModel {
	const VALID_TYPES = [
		'int', 'integer',
		'bool', 'boolean',
		'float', 'double',
		'string',
		'date'
	];

	private $_error             = null;
	private $_new               = true;
	private $_data              = [];
	private $_changed_columns   = [];
	private $_relation_cache    = [];
	private static $_default_db = null;

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

	private static function initPrimaryKeys() {
		if (count(static::$pk) === 0) {
			$class_name = get_called_class();
			$columns = static::getAllColumns();

			static::$pk = [];
			$stmt = static::getDatabase()->prepare('SHOW KEYS FROM ' . static::getEscapedSource() . ' WHERE `Key_name` = \'PRIMARY\'');
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

	public static function getAllColumns() {
		return array_keys(static::$columns);
	}

	private static function initSource() {
		if (static::$source === null) {
			static::$source_singular = ModelHelpers::getSingularFromClassNS(get_called_class());
			static::$source = ModelHelpers::getPlural(static::$source_singular);
		}
		if (static::$source_singular === null) {
			static::$source_singular = ModelHelpers::getSingular(static::$source);
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
		self::$_default_db = $db;
	}

	public static function getDefaultDatabase() {
		return self::$_default_db;
	}

	// Statement building methods

	private static function buildSelect(string $what, array $options = []) {
		$query = 'SELECT ' . $what . ' FROM ' . static::getEscapedSource();
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
			if (is_string($options['with'])) {
				$options['with'] = [$options['with']];
			}
			if (!is_array($options['with'])) {
				throw new \InvalidArgumentException('With must be a string or array of strings');
			}
			foreach ($options['with'] as $with) {
				$join = static::getJoin($with);
				if (!$join) {
					$class_name = get_called_class();
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
				if (!is_array($options[$expr])) {
					throw new \InvalidArgumentException(ucfirst($expr) . ' must be an associative array of (col_name | position) => ("ASC" | "DESC")');
				}
				$query .= strtoupper($expr) . ' BY ';
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

		return $query;
	}

	private static function buildStatement(string $query, array $options) {
		$stmt = static::getDatabase()->prepare($query);
		if (array_key_exists('bind', $options)) {
			if (!is_array($options['bind'])) {
				throw new \InvalidArgumentException('Bind must be an indexed array');
			}
			$is_seq = $options['bind'] !== [] && array_keys($options['bind']) === range(0, count($options['bind']) - 1);
			foreach ($options['bind'] as $key => $val) {
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
				if ($is_seq) {
					$stmt->bindValue($key + 1, $val, $type);
				} else {
					$stmt->bindValue($key, $val, $type);
				}
			}
		}
		return $stmt;
	}

	// Initializers

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

		// Build and execute statement
		$stmt = static::buildStatement(static::buildSelect(ModelHelpers::getEscapedList(static::getAllColumns(), static::getSource()), $options), $options);
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
			$model->{$key} = $val;
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

		// Build and execute statement
		$stmt = static::buildStatement(static::buildSelect(ModelHelpers::getEscapedList(static::getAllColumns(), static::getSource()), $options), $options);
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
				$model->{$key} = $val;
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
		// Build and execute statement which gets row count efficiently with given options
		$stmt = static::buildStatement(static::buildSelect('COUNT(' . ModelHelpers::getEscapedList(static::getPrimaryKeys(), static::getSource()) . ') AS `rowcount`', $options), $options);
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
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, 10) === 'beforeLoad') {
				if ($this->{$name}()) return true;
			}
		}

		// Build query options
		$pk_list = static::getPrimaryKeys();
		$options = [
			'conditions' => ModelHelpers::getEscapedWhere($pk_list),
			'bind' => []
		];
		foreach ($pk_list as $key) {
			$options['bind'][] = $this->{$key};
		}

		// Build and execute statement
		$stmt = static::buildStatement(static::buildSelect(ModelHelpers::getEscapedList(static::getAllColumns(), static::getSource()), $options), $options);
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
			$this->{$key} = $val;
		}

		// Run post-load hooks
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, 9) === 'afterLoad') {
				$this->{$name}();
			}
		}

		$this->forceCleanState();
		return true;
	}

	public function save() {
		// Run pre-save hooks
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, 10) === 'beforeSave') {
				if ($this->{$name}()) return true;
			}
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
			foreach (get_class_methods($this) as $name) {
				if (substr($name, 0, 9) === 'afterSave') {
					$this->{$name}();
				}
			}
		}

		return $retval;
	}

	public function create() {
		// Run pre-create hooks
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, 12) === 'beforeCreate') {
				if ($this->{$name}()) return true;
			}
		}

		// Update database
		$stmt = static::getDatabase()->prepare('INSERT INTO ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_changed_columns));
		foreach ($this->_changed_columns as $i => $key) {
			$val = $this->{$key};
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
			$stmt->bindValue($i + 1, $val, $type);
		}
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
				$this->{static::getPrimaryKeys()[0]} = $id;
			}

			// Run post-create hooks
			foreach (get_class_methods($this) as $name) {
				if (substr($name, 0, 11) === 'afterCreate') {
					$this->{$name}();
				}
			}

			$this->forceCleanState();
		}

		return $retval;
	}

	public function update() {
		// Run pre-update hooks
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, 12) === 'beforeUpdate') {
				if ($this->{$name}()) return true;
			}
		}

		// Update database
		$pk_list = static::getPrimaryKeys();
		$stmt = static::getDatabase()->prepare('UPDATE ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_changed_columns) . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list) . ' LIMIT 1');
		$i = 0;
		foreach ($this->_changed_columns as $key) {
			$val = $this->{$key};
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
			$stmt->bindValue(++$i, $val, $type);
		}
		foreach ($pk_list as $key) {
			$val = $this->{$key};
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
			$stmt->bindValue(++$i, $val, $type);
		}
		$this->_error = null;
		try {
			$retval = $stmt->execute();
		} catch (\PDOException $e) {
			$this->_error = $e;
			return false;
		}

		if ($retval) {
			// Run post-update hooks
			foreach (get_class_methods($this) as $name) {
				if (substr($name, 0, 11) === 'afterUpdate') {
					$this->{$name}();
				}
			}

			$this->forceCleanState();
		}

		return $retval;
	}

	public function delete() {
		foreach (get_class_methods($this) as $name) {
			if (substr($name, 0, 12) === 'beforeDelete') {
				if ($this->{$name}()) return true;
			}
		}

		$pk_list = static::getPrimaryKeys();
		$stmt = static::getDatabase()->prepare('DELETE FROM ' . static::getEscapedSource() . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list) . ' LIMIT 1');
		foreach ($pk_list as $i => $key) {
			$val = $this->{$key};
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
			$stmt->bindValue($i + 1, $val, $type);
		}
		$this->_error = null;
		try {
			$retval = $stmt->execute();
		} catch (\PDOException $e) {
			$this->_error = $e;
			return false;
		}

		if ($retval) {
			// Run post-delete hooks
			foreach (get_class_methods($this) as $name) {
				if (substr($name, 0, 11) === 'afterDelete') {
					$this->{$name}();
				}
			}

			$this->forceCleanState();
			$this->_new = true;
		}

		return $retval;
	}

	public function forceCleanState() {
		$this->_new = false;
		$this->_changed_columns = [];
		return $this;
	}

	public function getLastError() {
		return $this->_error;
	}

	// Data methods

	public function toArray() {
		return $this->_data;
	}

	public function __get(string $key) {
		if (array_key_exists($key, static::$columns)) {
			if (array_key_exists($key, $this->_data)) {
				return $this->_data[$key];
			}
			return null;
		} elseif (isset(static::$relations) && array_key_exists($key, static::$relations)) {
			if (!array_key_exists($key, $this->_relation_cache)) {
				$this->_relation_cache[$key] = $this->getRelated($key);
			}
			return $this->_relation_cache[$key];
		}
		$class_name = get_called_class();
		throw new \Exception("Column $key is not defined in model $class_name");
	}

	public function __set(string $key, $value) {
		if (!array_key_exists($key, static::$columns)) {
			$class_name = get_called_class();
			throw new \Exception("Column $key is not defined in model $class_name");
		}
		if ($value === null) {
			$this->_data[$key] = null;
		} elseif (static::$columns[$key] === 'date') {
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
			settype($value, static::$columns[$key]);
			$this->_data[$key] = $value;
		}
		if (!in_array($key, $this->_changed_columns)) {
			$this->_changed_columns[] = $key;
		}
	}

	public function __call(string $name, array $arguments) {
		$name_snake = ModelHelpers::getSnakeCase($name);
		$key_snake = substr($name_snake, 4);
		if (array_key_exists($key_snake, static::$columns) ||
			isset(static::$relations) && array_key_exists($key_snake, static::$relations)) {
			switch (substr($name_snake, 0, 4)) {
				case 'get_':
					return $this->__get($key_snake);
				case 'set_':
					if (count($arguments) === 0) {
						throw new \InvalidArgumentException('Setters must be called with a key and value');
					}
					$this->__set($key_snake, $arguments[0]);
					return $this;
			}
		}
		$class_name = get_called_class();
		throw new \Exception("Method $name not defined in model $class_name");
	}
}
