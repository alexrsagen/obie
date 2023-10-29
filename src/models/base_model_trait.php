<?php namespace Obie\Models;
use Obie\Formatters\EnglishNoun;

trait BaseModelTrait {
	// Error handling methods
	protected static ?\PDOException $find_error = null;
	public static function getLastFindError(): ?\PDOException {
		return static::$find_error;
	}
	public static function setLastFindError(?\PDOException $find_error): void {
		static::$find_error = $find_error;
	}

	protected static ?\PDOException $count_error = null;
	public static function getLastCountError(): ?\PDOException {
		return static::$count_error;
	}
	public static function setLastCountError(?\PDOException $count_error): void {
		static::$count_error = $count_error;
	}

	protected static ?string $last_query = null;
	public static function getLastQuery(): ?string {
		return static::$last_query;
	}
	public static function setLastQuery(?string $last_query): void {
		static::$last_query = $last_query;
	}

	// Source table methods
	protected static ?string $source          = null;
	protected static ?string $source_singular = null;

	protected static function initSource(): void {
		if (static::$source === null) {
			static::$source_singular = EnglishNoun::classNameToSingular(get_called_class());
			static::$source = EnglishNoun::toPlural(static::$source_singular);
		}
		if (static::$source_singular === null) {
			static::$source_singular = EnglishNoun::toSingular(static::$source);
		}
	}

	public static function getSource(): ?string {
		static::initSource();
		return static::$source;
	}

	public static function setSource(string $source, ?string $source_singular = null): void {
		static::$source = $source;
		static::$source_singular = $source_singular;
		static::initSource();
	}

	public static function getEscapedSource(): ?string {
		$source = static::getSource();
		if ($source === null) return null;
		return ModelHelpers::getEscapedSource($source);
	}

	// Column definition methods
	protected static array $columns = [];

	/**
	 * Define one or more columns on this table.
	 *
	 * @param mixed $column If $type is not null, a string specifying a column name or an array of column names. Otherwise an array of "column" => "type".
	 * @param string|null $type The type to use for all columns specified. To specify a unique type for each column, leave as null and specify $column as an array of "column" => "type".
	 * @return void
	 * @throws \InvalidArgumentException If invalid arguments specified
	 */
	public static function define(string|array $column, ?string $type = null): void {
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
				if (!in_array($type, BaseModel::VALID_TYPES, true)) {
					throw new \InvalidArgumentException('Type must be a member of BaseModel::VALID_TYPES');
				}
				static::$columns[$key] = $type;
			}
		} else {
			throw new \InvalidArgumentException('Column must be a string or array of "column" => "type"');
		}
	}

	public static function getColumnDefinitions(): array {
		return static::$columns;
	}

	public static function getColumnType(string $column): ?string {
		return array_key_exists($column, static::$columns) ? static::$columns[$column]: null;
	}

	// Primary key methods
	protected static array $pk = [];
	public static function getPrimaryKeys(): array {
		if (count(static::$pk) === 0) static::initPrimaryKeys();
		return static::$pk;
	}
	public static function setPrimaryKeys(array $primary_keys): void {
		static::$pk = $primary_keys;
	}

	// Database methods
	protected static ?\PDO $db = null;
	public static function getDatabase(): ?\PDO {
		if (static::$db === null) {
			return static::getDefaultDatabase();
		}
		return static::$db;
	}
	public static function setDatabase(\PDO $db): void {
		static::$db = $db;
	}

	protected static ?\PDO $ro_db = null;
	public static function getReadOnlyDatabase(): ?\PDO {
		if (static::$ro_db === null) {
			return static::getDefaultReadOnlyDatabase();
		}
		return static::$ro_db;
	}
	public static function setReadOnlyDatabase(\PDO $db): void {
		static::$ro_db = $db;
	}
}
