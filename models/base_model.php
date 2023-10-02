<?php namespace Obie\Models;
use Obie\Formatters\EnglishNoun;
use Obie\Formatters\Casing;

class BaseModel {
	const VALID_TYPES = [
		'int', 'integer',
		'bool', 'boolean',
		'float', 'double',
		'string',
		'date'
	];

	protected ?\PDOException $_error       = null;
	protected ?string $_last_save_query    = null;
	protected bool $_new                   = true;
	protected array $_data                 = [];
	protected array $_original_data        = [];
	protected array $_modified_columns     = [];
	protected static ?\PDO $_default_db    = null;
	protected static ?\PDO $_default_ro_db = null;
	protected static string $_timezone     = 'UTC';

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

	public static function getPrimaryKeys(): array {
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

	public static function getSource(): ?string {
		static::initSource();
		return static::$source;
	}

	public static function getEscapedSource(): ?string {
		$source = static::getSource();
		if ($source === null) return null;
		return ModelHelpers::getEscapedSource($source);
	}

	public static function setDatabase(\PDO $db) {
		static::$db = $db;
	}

	public static function getDatabase(): ?\PDO {
		if (static::$db === null) {
			return static::getDefaultDatabase();
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

	public static function getDefaultDatabase(): ?\PDO {
		$parent = get_parent_class(static::class);
		if (property_exists(static::class, '_default_db')) {
			return static::$_default_db;
		} elseif ($parent !== false && property_exists($parent, '_default_db') && $parent::$_default_db !== null) {
			return $parent::$_default_db;
		}
		return self::$_default_db;
	}

	public static function setReadOnlyDatabase(\PDO $db) {
		static::$ro_db = $db;
	}

	public static function getReadOnlyDatabase(): ?\PDO {
		if (static::$ro_db === null) {
			return static::getDefaultReadOnlyDatabase();
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

	public static function getDefaultReadOnlyDatabase(): ?\PDO {
		$parent = get_parent_class(static::class);
		if (property_exists(static::class, '_default_ro_db') && static::$_default_ro_db !== null) {
			return static::$_default_ro_db;
		} elseif ($parent !== false && property_exists($parent, '_default_ro_db') && $parent::$_default_ro_db !== null) {
			return $parent::$_default_ro_db;
		} elseif (self::$_default_ro_db !== null) {
			return self::$_default_ro_db;
		}
		return self::getDefaultDatabase();
	}

	// Statement building methods

	public static function getEscapedColumn(string $column): string {
		$column_parts = explode('.', $column);
		if (count($column_parts) > 1) {
			array_unshift($column_parts, static::getSource());
			$source_escaped = '`' . implode('.', array_slice($column_parts, 0, -1)) . '`';
		} else {
			$source_escaped = static::getEscapedSource();
		}
		return $source_escaped . '.`' . $column_parts[count($column_parts)-1] . '`';
	}

	protected static function buildWhat(array &$options = []): string {
		if (!array_key_exists('what', $options)) {
			$options['what'] = static::getAllColumns();
		}
		$query = ' ';
		if (is_array($options['what'])) {
			$query .= ModelHelpers::getEscapedList($options['what'], static::getSource());
		} elseif (is_string($options['what'])) {
			$query .= $options['what'];
		} else {
			throw new \InvalidArgumentException('What must be a string or array of strings');
		}
		return $query;
	}

	protected static function buildFrom(array &$options = []): string {
		return ' FROM ' . static::getEscapedSource();
	}

	protected static function buildJoin(array &$options = []): string {
		if (!array_key_exists('join', $options)) return '';
		if (is_string($options['join'])) {
			$options['join'] = [$options['join']];
		}
		if (!is_array($options['join'])) {
			throw new \InvalidArgumentException('Join must be a string or array of strings');
		}
		$query = '';
		foreach ($options['join'] as $join) {
			$query .= ' ' . $join;
		}
		return $query;
	}

	protected static function buildConditions(array &$options = []): string {
		if (!array_key_exists('conditions', $options)) return '';
		if (is_string($options['conditions'])) {
			$options['conditions'] = [$options['conditions']];
		}
		if (!is_array($options['conditions'])) {
			throw new \InvalidArgumentException('Conditions must be a string or array of strings');
		}
		if (count($options['conditions']) === 0) return '';
		$query = '';
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
		return $query;
	}

	protected static function buildGroup(array &$options = []): string {
		if (!array_key_exists('group', $options)) return '';
		$query = ' GROUP BY ';
		if (is_array($options['group'])) {
			$is_first = true;
			foreach ($options['group'] as $key => $column) {
				if (!is_int($key) || !is_string($column) && !is_int($column)) {
					throw new \InvalidArgumentException('Group must be a sequential array of (col_name | position)');
				}
				if (!$is_first) {
					$query .= ',';
				}
				if (is_int($column)) {
					$query .= (string)$column;
				} else {
					$column_escaped = static::getEscapedColumn($column);
					$query .= $column_escaped;
				}
				$is_first = false;
			}
		} elseif (is_string($options['group'])) {
			$query .= $options['group'];
		} else {
			throw new \InvalidArgumentException('Group must be a sequential array of (col_name | position)');
		}
		return $query;
	}

	protected static function buildOrder(array &$options = []): string {
		if (!array_key_exists('order', $options)) return '';
		$query = ' ORDER BY ';
		if (is_array($options['order'])) {
			$is_first = true;
			foreach ($options['order'] as $column => $type) {
				if (!is_string($column) && !is_int($column)) {
					throw new \InvalidArgumentException('Order must be an associative array of (col_name | position) => ("ASC" | "DESC")');
				}
				if (!is_string($type)) {
					throw new \InvalidArgumentException('Order must be an associative array of (col_name | position) => ("ASC" | "DESC")');
				}
				$type = strtoupper($type);
				if ($type !== 'ASC' && $type !== 'DESC') {
					throw new \InvalidArgumentException('Order must be an associative array of (col_name | position) => ("ASC" | "DESC")');
				}
				if (!$is_first) {
					$query .= ',';
				}
				if (is_int($column)) {
					$query .= (string)$column . ' ' . $type;
				} else {
					$column_escaped = static::getEscapedColumn($column);
					$type_inv = $type === 'ASC' ? 'DESC' : 'ASC';
					$query .= $column_escaped . ' IS NULL ' . $type_inv . ',';
					$query .= $column_escaped . ' ' . $type;
				}
				$is_first = false;
			}
		} elseif (is_string($options['order'])) {
			$query .= $options['order'];
		} else {
			throw new \InvalidArgumentException('Order must be an associative array of (col_name | position) => ("ASC" | "DESC")');
		}
		return $query;
	}

	protected static function buildLimit(array &$options = []): string {
		if (!array_key_exists('limit', $options)) return '';
		if (!is_int($options['limit'])) {
			throw new \InvalidArgumentException('Limit must be an int');
		}
		$query = ' LIMIT ';
		if (array_key_exists('offset', $options)) {
			if (!is_int($options['offset'])) {
				throw new \InvalidArgumentException('Offset must be an int');
			}
			$query .= (string)$options['offset'] . ',';
		}
		$query .= (string)$options['limit'];
		return $query;
	}

	protected static function buildFor(array &$options = []): string {
		if (!array_key_exists('for', $options) || !is_array($options['for'])) return '';

		$query = '';
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
		return $query;
	}

	protected static function buildSelect(array $options = []): string {
		$query = 'SELECT';
		$query .= static::buildWhat($options);
		$query .= static::buildFrom($options);
		$query .= static::buildJoin($options);
		$query .= static::buildConditions($options);
		$query .= static::buildGroup($options);
		$query .= static::buildOrder($options);
		$query .= static::buildLimit($options);
		$query .= static::buildFor($options);
		return $query;
	}

	protected static function buildStatement(string $query, array $options): \PDOStatement {
		static::$last_query = $query;
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

	public static function getLastQuery(): ?string {
		return static::$last_query;
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
		static::$find_error = null;
		try {
			$stmt = static::buildStatement(static::buildSelect($options), $options);
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
		static::$find_error = null;
		try {
			$stmt = static::buildStatement(static::buildSelect($options), $options);
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

	public static function getLastFindError() {
		return static::$find_error;
	}

	public static function count(array $options = []) {
		// Enforce read only option
		$options['read_only'] = true;

		// Enforce what option
		$options['what'] = 'COUNT(' . ModelHelpers::getEscapedList(static::getPrimaryKeys(), static::getSource()) . ') AS `rowcount`';

		// Build and execute statement which gets row count efficiently with given options
		static::$count_error = null;
		try {
			$stmt = static::buildStatement(static::buildSelect($options), $options);
			$stmt->execute();
		} catch (\PDOException $e) {
			static::$count_error = $e;
			return false;
		}
		$res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		if (count($res) === 0) return 0;
		if (array_key_exists('group', $options) && !empty($options['group'])) {
			if (count($res) === 1 && (int)$res[0]['rowcount'] === 0) {
				return 0;
			}
			return count($res);
		}
		return (int)$res[0]['rowcount'];
	}

	public static function getLastCountError() {
		return static::$count_error;
	}

	// Database interaction methods

	public function load(): bool {
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

	public function save(): bool {
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

	public function create(): bool {
		// Run pre-create hooks
		foreach ($this->getHooks('beforeCreate') as $name) {
			if ($this->{$name}()) return true;
		}

		// Update database
		$query = 'INSERT INTO ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_modified_columns, static::getSource());
		$this->_last_save_query = $query;
		$stmt = static::getDatabase()->prepare($query);
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
			// Set primary key value to last insert ID
			if (count(static::getPrimaryKeys()) === 1) {
				$primary_key = static::getPrimaryKeys()[0];
				if (
					$id !== false &&
					$id !== 0 &&
					static::columnExists($primary_key) &&
					in_array(static::$columns[$primary_key], ['int', 'integer'], true)
				) {
					$this->set($primary_key, $id, false);
				}
			}

			// Run post-create hooks
			foreach ($this->getHooks('afterCreate') as $name) {
				$this->{$name}();
			}

			$this->forceCleanState();
		}

		return $retval;
	}

	public function update(): bool {
		if (!$this->modified()) return true;

		// Run pre-update hooks
		foreach ($this->getHooks('beforeUpdate') as $name) {
			if ($this->{$name}()) return true;
		}

		// Update database
		$pk_list = static::getPrimaryKeys();
		$query = 'UPDATE ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_modified_columns, static::getSource()) . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list, static::getSource()) . ' LIMIT 1';
		$this->_last_save_query = $query;
		$stmt = static::getDatabase()->prepare($query);
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

	public function delete(): bool {
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

	public function forceCleanState(): static {
		$this->_new = false;
		$this->_original_data  = [];
		$this->_modified_columns = [];
		return $this;
	}

	public function getLastError() {
		return $this->_error;
	}

	public function getLastSaveQuery() {
		return $this->_last_save_query;
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

	public function modified(): bool {
		return !empty($this->_modified_columns);
	}

	public function modifiedColumns(): array {
		return $this->_modified_columns;
	}

	public function originalData(?string $key = null) {
		if ($key !== null) {
			if (!static::columnExists($key)) {
				$class_name = get_called_class();
				throw new \Exception("Column $key is not defined in model $class_name");
			}
			return array_key_exists($key, $this->_original_data) ? $this->_original_data[$key] : (array_key_exists($key, $this->_data) ? $this->_data[$key] : null);
		}
		return array_merge($this->_data, $this->_original_data);
	}

	public function undoModifications(): static {
		$this->_data = $this->originalData();
		return $this;
	}

	public function toArray() {
		$data = [];
		foreach (static::getAllColumns() as $column) {
			$type = static::$columns[$column];
			if ($type === 'date') {
				$data[$column] = $this->get($column, false)?->format(\DateTime::RFC3339);
			} else {
				$data[$column] = $this->get($column);
			}
		}
		return $data;
	}

	public function modifiedDataToArray(): array {
		return array_intersect_key($this->toArray(), array_flip($this->modifiedColumns()));
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

	public function set(string $key, $value, bool $hooks = true): static {
		// Get column metadata
		if (!static::columnExists($key)) {
			$class_name = get_called_class();
			throw new \Exception("Column $key is not defined in model $class_name");
		}
		$type = static::$columns[$key];

		// Get current column data (before change)
		$current_value = $this->get($key, false);

		// Run beforeSet* hooks
		$new_value = $value;
		if ($hooks) {
			foreach ($this->getHooks('beforeSet') as $name) {
				$new_value = $this->{$name}($key, $new_value, $type);
			}
		}

		// Transform value
		if ($new_value !== null) {
			if ($type === 'date') {
				if (is_string($new_value)) {
					$new_value = \DateTime::createFromFormat('Y-m-d H:i:s', $new_value, new \DateTimeZone(static::$_timezone));
				} elseif (is_int($new_value)) {
					$new_value = (new \DateTime)->setTimestamp($new_value);
				} elseif (!($new_value instanceof \DateTime)) {
					throw new \InvalidArgumentException('Date values must be an instance of DateTime, a string or an int');
				}
			} else {
				settype($new_value, $type);
			}
		}

		// Return if value is loaded and new value matches current value
		if (array_key_exists($key, $this->_data) && $new_value === $current_value) {
			return $this;
		}

		// Store new value
		$this->_data[$key] = $new_value;

		// Store original value
		if (!in_array($key, $this->_modified_columns)) {
			$this->_original_data[$key] = $current_value;
			$this->_modified_columns[] = $key;
		}

		// Run afterSet* hooks
		if ($hooks) {
			foreach ($this->getHooks('afterSet') as $name) {
				$this->{$name}($key, $new_value, $type);
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
