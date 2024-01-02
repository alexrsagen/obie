<?php namespace Obie\Models;
use Obie\Formatters\Casing;
use Obie\App;

abstract class BaseModel {
	const VALID_TYPES = [
		'int', 'integer',
		'bool', 'boolean',
		'float', 'double',
		'string',
		'date'
	];

	protected ?\PDOException $_error            = null;
	protected ?string $_last_save_query         = null;
	protected bool $_new                        = true;
	protected array $_data                      = [];
	protected array $_original_data             = [];
	protected array $_modified_columns          = [];

	protected static ?\PDO $_default_db         = null;
	protected static ?\PDO $_default_ro_db      = null;
	protected static string $_timezone          = 'UTC';

	public static int $max_connect_retries       = 5;
	public static int $connect_interval_seconds  = 5;

	// Model definition methods

	protected static function initPrimaryKeys(): void {
		$class_name = get_called_class();
		$columns = static::getAllColumns();

		$pk = [];
		$stmt = static::executeQueryRetry('SHOW KEYS FROM ' . static::getEscapedSource() . ' WHERE `Key_name` = \'PRIMARY\'', ['read_only' => true], $find_error);
		static::setLastFindError($find_error);
		if (!$stmt) return;
		foreach ($stmt->fetchAll() as $row) {
			// Ensure that primary key exists as a public variable of this model
			if (!in_array($row['Column_name'], $columns)) {
				throw new \Exception("Primary key \"{$row['Column_name']}\" not defined in model \"$class_name\"");
			}

			$pk[] = $row['Column_name'];
		}
		static::setPrimaryKeys($pk);
	}

	public static function getAllColumns(): array {
		return array_keys(static::getColumnDefinitions());
	}

	public static function columnExists(string $name): bool {
		return array_key_exists($name, static::getColumnDefinitions());
	}

	public static function columnsExist(string ...$names): bool {
		return empty(array_diff($names, static::getAllColumns()));
	}

	protected static function canGetOrSet(string $name): bool {
		return static::columnExists($name);
	}

	public static function setDefaultDatabase(\PDO $db): void {
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

	public static function setDefaultReadOnlyDatabase(\PDO $db): void {
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

	protected static function buildStatement(string $query, array $options = []): \PDOStatement {
		static::setLastQuery($query);

		$stmt = false;
		for ($i = 0; $i < static::$max_connect_retries; $i++) {
			$db = array_key_exists('read_only', $options) && $options['read_only'] ? static::getReadOnlyDatabase() : static::getDatabase();
			if ($i > 0) sleep(static::$connect_interval_seconds);
			if ($db === null || $i > 0) App::$app::initDatabase(force: true);

			$stmt = $db?->prepare($query);
			if ($stmt instanceof \PDOStatement) break;

			App::$app::initDatabase(force: true);
		}
		if ($stmt === false) {
			throw new \Exception('Not connected to database');
		}

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

	/**
	 * @param null|string|array $options
	 * @return static|false
	 * @throws \InvalidArgumentException If options is invalid
	 * @throws \Exception If database is unavailable
	 */
	public static function findFirst(string|array|null $options = null): static|false {
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
		$stmt = static::executeQueryRetry(static::buildSelect($options), $options, $find_error);
		static::setLastFindError($find_error);
		if (!$stmt) return false;
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

	/**
	 * @param string|array|null $options
	 * @return ModelCollection<static>|false False if no results found or query error
	 * @throws \InvalidArgumentException If options is invalid
	 * @throws \Exception If database is unavailable
	 */
	public static function find(string|array|null $options = null): ModelCollection|false {
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
		$stmt = static::executeQueryRetry(static::buildSelect($options), $options, $find_error);
		static::setLastFindError($find_error);
		if (!$stmt) return false;
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
			$models->add($model);
		}

		return $models;
	}

	public static function count(array $options = []): int|false {
		// Enforce read only option
		$options['read_only'] = true;

		// Enforce what option
		$options['what'] = 'COUNT(' . ModelHelpers::getEscapedList(static::getPrimaryKeys(), static::getSource()) . ') AS `rowcount`';

		// Build and execute statement which gets row count efficiently with given options
		$stmt = static::executeQueryRetry(static::buildSelect($options), $options, $count_error);
		static::setLastCountError($count_error);
		if (!$stmt) return false;
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

	// Database interaction methods

	public static function executeQueryRetry(string $query, array $options = [], ?string &$error = null): ?\PDOStatement {
		for ($i = 0; $i < static::$max_connect_retries; $i++) {
			if ($i > 0) sleep(static::$connect_interval_seconds);

			// Build statement
			$stmt = static::buildStatement($query, $options);

			// Execute statement
			$error = null;
			try {
				$stmt->execute();
				return $stmt;
			} catch (\PDOException $e) {
				// retry on connection error
				if (
					is_array($e->errorInfo) &&
					array_key_exists(0, $e->errorInfo) &&
					array_key_exists(1, $e->errorInfo) &&
					$e->errorInfo[0] === 'HY000' &&
					$e->errorInfo[1] === 2006 // 2006: MySQL server has gone away
				) {
					continue;
				}

				// abort on any other error
				$error = $e;
			}
			break;
		}
		return null;
	}

	protected function executeActionRetry(string $action): ?\PDOStatement {
		$stmt = null;
		for ($i = 0; $i < static::$max_connect_retries; $i++) {
			if ($i > 0) {
				sleep(static::$connect_interval_seconds);
				App::$app::initDatabase(force: true);
			}

			// Build statement depending on action
			switch ($action) {
			case 'load':
				$pk_list = static::getPrimaryKeys();
				$options = [
					'conditions' => ModelHelpers::getEscapedWhere($pk_list, static::getSource()),
					'bind'       => [],
					'read_only'  => true,
					'limit'      => 1,
				];
				foreach ($pk_list as $key) {
					$options['bind'][] = $this->get($key, false);
				}
				$stmt = static::buildStatement(static::buildSelect($options), $options);
				break;
			case 'create':
				$query = 'INSERT INTO ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_modified_columns, static::getSource());
				$this->_last_save_query = $query;
				$stmt = static::buildStatement($query);
				$this->bindValues($stmt, $this->_modified_columns);
				break;
			case 'update':
				$pk_list = static::getPrimaryKeys();
				$query = 'UPDATE ' . static::getEscapedSource() . ' SET ' . ModelHelpers::getEscapedSet($this->_modified_columns, static::getSource()) . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list, static::getSource()) . ' LIMIT 1';
				$this->_last_save_query = $query;
				$stmt = static::buildStatement($query);
				$i = $this->bindValues($stmt, $this->_modified_columns);
				$this->bindValues($stmt, $pk_list, $i);
				break;
			case 'delete':
				$pk_list = static::getPrimaryKeys();
				$stmt = static::buildStatement('DELETE FROM ' . static::getEscapedSource() . ' WHERE ' . ModelHelpers::getEscapedWhere($pk_list, static::getSource()) . ' LIMIT 1');
				$this->bindValues($stmt, $pk_list);
				break;
			default:
				return null;
			}

			// Execute statement
			$this->_error = null;
			try {
				$stmt->execute();
				return $stmt;
			} catch (\PDOException $e) {
				// retry on connection error
				if (
					is_array($e->errorInfo) &&
					array_key_exists(0, $e->errorInfo) &&
					array_key_exists(1, $e->errorInfo) &&
					$e->errorInfo[0] === 'HY000' &&
					$e->errorInfo[1] === 2006 // 2006: MySQL server has gone away
				) {
					continue;
				}

				// abort on any other error
				$this->_error = $e;
			}
			break;
		}
		return null;
	}

	public function load(): bool {
		// Run pre-load hooks
		foreach ($this->getHooks('beforeLoad') as $name) {
			if ($this->{$name}()) return true;
		}

		if (!($stmt = $this->executeActionRetry('load'))) return false;
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
		if (!($stmt = $this->executeActionRetry('create'))) return false;
		$id = static::getDatabase()?->lastInsertId();

		// Set primary key value to last insert ID
		if (count(static::getPrimaryKeys()) === 1) {
			$primary_key = static::getPrimaryKeys()[0];
			if (
				$id !== false &&
				$id !== 0 &&
				static::columnExists($primary_key) &&
				in_array(static::getColumnType($primary_key), ['int', 'integer'], true)
			) {
				$this->set($primary_key, $id, false);
			}
		}

		// Run post-create hooks
		foreach ($this->getHooks('afterCreate') as $name) {
			$this->{$name}();
		}

		$this->forceCleanState();
		return true;
	}

	public function update(): bool {
		if (!$this->modified()) return true;

		// Run pre-update hooks
		foreach ($this->getHooks('beforeUpdate') as $name) {
			if ($this->{$name}()) return true;
		}

		// Update database
		if (!($stmt = $this->executeActionRetry('update'))) return false;

		// Run post-update hooks
		foreach ($this->getHooks('afterUpdate') as $name) {
			$this->{$name}();
		}

		$this->forceCleanState();
		return true;
	}

	public function delete(): bool {
		foreach ($this->getHooks('beforeDelete') as $name) {
			if ($this->{$name}()) return true;
		}

		if (!($stmt = $this->executeActionRetry('delete'))) return false;

		// Run post-delete hooks
		foreach ($this->getHooks('afterDelete') as $name) {
			$this->{$name}();
		}

		$this->forceCleanState();
		$this->_new = true;
		return true;
	}

	protected function bindValues(\PDOStatement $stmt, array $keys, int $i = 1): int {
		foreach ($keys as $key) {
			$val = $this->get($key, false);
			static::bindValue($stmt, $i++, $val);
		}
		return $i;
	}

	protected static function bindValue(\PDOStatement $stmt, string|int $key, mixed $val): void {
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

	public function getLastError(): ?\PDOException {
		return $this->_error;
	}

	public function getLastSaveQuery(): ?string {
		return $this->_last_save_query;
	}

	// Data methods

	protected function getHooks(string $hook_prefix): array {
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

	public function originalData(?string $key = null): mixed {
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

	public function toArray(): array {
		$data = [];
		foreach (static::getColumnDefinitions() as $column => $type) {
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

	public function get(string $key, bool $hooks = true): mixed {
		if (!static::columnExists($key)) {
			$class_name = get_called_class();
			throw new \Exception("Column $key is not defined in model $class_name");
		}
		if (!array_key_exists($key, $this->_data)) return null;
		$value = $this->_data[$key];
		if ($hooks) {
			foreach ($this->getHooks('beforeGet') as $name) {
				// hook arguments: key, value, is_relation, type
				$value = $this->{$name}($key, $value, false, static::getColumnType($key));
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
		$type = static::getColumnType($key);

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

	// BaseModelTrait methods
	abstract public static function getSource(): ?string;
	abstract public static function setSource(string $source, ?string $source_singular = null): void;
	abstract public static function getEscapedSource(): ?string;
	abstract public static function define(string|array $column, ?string $type = null): void;
	abstract public static function getColumnDefinitions(): array;
	abstract public static function getColumnType(string $column): ?string;
	abstract public static function getPrimaryKeys(): array;
	abstract public static function setPrimaryKeys(array $primary_keys): void;
	abstract public static function getDatabase(): ?\PDO;
	abstract public static function setDatabase(\PDO $db): void;
	abstract public static function getReadOnlyDatabase(): ?\PDO;
	abstract public static function setReadOnlyDatabase(\PDO $db): void;
	abstract public static function getLastFindError(): ?\PDOException;
	abstract public static function setLastFindError(?\PDOException $find_error): void;
	abstract public static function getLastCountError(): ?\PDOException;
	abstract public static function setLastCountError(?\PDOException $count_error): void;
	abstract public static function getLastQuery(): ?string;
	abstract public static function setLastQuery(?string $last_query): void;
}
