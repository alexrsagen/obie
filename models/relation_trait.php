<?php namespace Obie\Models;
use Obie\Formatters\EnglishNoun;
use Obie\Log;

trait RelationTrait {
	protected static $relations = [];
	protected $_relation_cache  = [];

	public static function getAllRelations(): array {
		return array_keys(static::$relations);
	}

	public static function relationExists(string $name): bool {
		return array_key_exists($name, static::$relations);
	}

	public static function relationsExist(string ...$names): bool {
		return empty(array_diff($names, static::getAllRelations()));
	}

	protected static function canGetOrSet(string $name): bool {
		return static::columnExists($name) || static::relationExists($name);
	}

	protected static function buildJoin(array &$options = []): string {
		$query = parent::buildJoin($options);
		if (array_key_exists('with', $options) && is_string($options['with'])) {
			$options['with'] = [$options['with']];
		}
		if (array_key_exists('with', $options) && !is_array($options['with'])) {
			throw new \InvalidArgumentException('With must be a string or array of strings');
		}
		if (array_key_exists('group', $options) && is_array($options['group'])) {
			foreach ($options['group'] as $column) {
				if (is_string($column)) {
					$parts = explode('.', $column);
					if (count($parts) > 1) {
						if (!array_key_exists('with', $options)) {
							$options['with'] = [];
						}
						$options['with'][] = implode('.', array_slice($parts, 0, -1));
					}
				}
			}
		}
		if (array_key_exists('order', $options) && is_array($options['order'])) {
			foreach ($options['order'] as $column => $type) {
				if (is_string($column)) {
					$parts = explode('.', $column);
					if (count($parts) > 1) {
						if (!array_key_exists('with', $options)) {
							$options['with'] = [];
						}
						$options['with'][] = implode('.', array_slice($parts, 0, -1));
					}
				}
			}
		}
		if (array_key_exists('with', $options)) {
			$query .= static::buildJoinWith($options);
		}
		return $query;
	}

	protected static function buildJoinWith(array &$options = []): string {
		if (!array_key_exists('with', $options)) return '';
		$with_tree = [];
		foreach ($options['with'] as $with) {
			$parts = explode('.', $with);
			$cur = &$with_tree;
			foreach ($parts as $part) {
				if (!array_key_exists($part, $cur)) {
					$cur[$part] = [];
				}
				$cur = &$cur[$part];
			}
			unset($cur);
		}
		return static::getJoinFromWithTree($with_tree);
	}

	public function get(string $key, bool $hooks = true) {
		if (static::columnExists($key)) {
			if (!array_key_exists($key, $this->_data)) return null;
			$value = $this->_data[$key];
			if ($hooks) {
				foreach ($this->getHooks('beforeGet') as $name) {
					// hook arguments: key, value, is_relation, type
					$value = $this->{$name}($key, $value, false, static::$columns[$key]);
				}
			}
			return $value;
		} elseif (static::relationExists($key)) {
			if (!array_key_exists($key, $this->_relation_cache)) {
				$this->_relation_cache[$key] = $this->getRelated($key);
				if (!$this->_relation_cache[$key]) $this->_relation_cache[$key] = null;
			}
			$value = $this->_relation_cache[$key];
			if ($hooks) {
				foreach ($this->getHooks('beforeGet') as $name) {
					// hook arguments: key, value, is_relation, type (always null in case of relation)
					$value = $this->{$name}($key, $value, true, null);
				}
			}
			return $value;
		}
		$class_name = static::class;
		throw new \Exception("Column $key is not defined in model $class_name");
	}

	public function getRelated(string $relation_name, string|array $options = [], bool $count = false) {
		if (is_string($options)) {
			$options = ['conditions' => $options];
		}
		if (!array_key_exists($relation_name, static::$relations)) {
			return false;
		}

		$relation = static::$relations[$relation_name];
		$target = $relation['target_model'];

		$relation_options = [
			'conditions' => [],
			'bind'       => []
		];

		$relation_options['conditions'][] = ModelHelpers::getEscapedWhere($relation['target_fields'], $target::getSource());
		if (array_key_exists('conditions', $options)) {
			if (is_string($options['conditions'])) {
				$options['conditions'] = [$options['conditions']];
			}
			$relation_options['conditions'] = array_merge($relation_options['conditions'], $options['conditions']);
		}
		if (array_key_exists('conditions', $relation['default_options'])) {
			if (is_string($relation['default_options']['conditions'])) {
				$relation['default_options']['conditions'] = [$relation['default_options']['conditions']];
			}
			$relation_options['conditions'] = array_merge($relation_options['conditions'], $relation['default_options']['conditions']);
		}
		$options['conditions'] = $relation_options['conditions'];

		foreach ($relation['source_fields'] as $key) {
			if (is_object($key)) {
				$relation_options['bind'][] = $key->bindTo($this, $this)();
			} elseif (method_exists($this, $key)) {
				$relation_options['bind'][] = $this->{$key}();
			} else {
				$relation_options['bind'][] = $this->{$key};
			}
		}
		if (array_key_exists('bind', $options)) {
			$relation_options['bind'] = array_merge($relation_options['bind'], $options['bind']);
		}
		if (array_key_exists('bind', $relation['default_options'])) {
			$relation_options['bind'] = array_merge($relation_options['bind'], $relation['default_options']['bind']);
		}
		$options['bind'] = $relation_options['bind'];

		switch ($relation['type']) {
		case RelationModel::TYPE_BELONGS_TO_ONE:
		case RelationModel::TYPE_HAS_ONE:
			$options['limit'] = 1;
			break;
		}

		if ($count) {
			$target_count = $target::count($options);
			if ($target_count === false && $target::getLastCountError()) {
				Log::error($target::getLastCountError(), ['prefix' => 'BaseModel: Could get count of model "' . $target . '" due to database error: ']);
				Log::debug($target::getLastQuery(), ['prefix' => 'Last SQL query that failed: ']);
				return false;
			}
			return $target_count;
		}

		switch ($relation['type']) {
		case RelationModel::TYPE_BELONGS_TO_ONE:
		case RelationModel::TYPE_HAS_ONE:
			return $target::findFirst($options);

		case RelationModel::TYPE_BELONGS_TO_MANY:
		case RelationModel::TYPE_HAS_MANY:
			return $target::find($options);
		}
		return false;
	}

	public static function getRelation(string $relation_name) {
		if (!array_key_exists($relation_name, static::$relations)) {
			return false;
		}
		return static::$relations[$relation_name];
	}

	public static function getRelations() {
		return static::$relations;
	}

	public static function getJoin(string $relation_name, string $kind = 'LEFT', string $relation_alias = '', string $alias = '') {
		$relation = static::getRelation($relation_name);
		if (!$relation) return false;

		if (empty($relation_alias)) {
			$relation_alias = $relation_name;
		}
		if (empty($alias)) {
			$alias = static::getSource();
		}

		$target = $relation['target_model'];
		switch ($relation['type']) {
		case RelationModel::TYPE_BELONGS_TO_ONE:
		case RelationModel::TYPE_BELONGS_TO_MANY:
		case RelationModel::TYPE_HAS_ONE:
		case RelationModel::TYPE_HAS_MANY:
			return $kind . ' JOIN ' . $target::getEscapedSource() .
				' AS ' . ModelHelpers::getEscapedSource($relation_alias) .
				' ON ' . ModelHelpers::getEscapedOn(
					$relation['target_fields'], $relation_alias,
					$relation['source_fields'], $alias);
		}

		return false;
	}

	public static function getJoinFromWithTree(array $with, ?string $parent_relation_name = null): string {
		if ($parent_relation_name === null) $parent_relation_name = static::getSource();
		$join = '';
		foreach ($with as $relation_name => $with_tree) {
			$relation = static::getRelation($relation_name);
			if (!$relation) {
				$class_name = static::class;
				throw new \InvalidArgumentException("Relation \"$relation_name\" does not exist on model \"$class_name\"");
			}
			$relation_alias = (!empty($parent_relation_name) ? $parent_relation_name . '.' : '') . $relation_name;
			$join .= ' ' . static::getJoin($relation_name, relation_alias: $relation_alias, alias: $parent_relation_name);
			$join .= $relation['target_model']::getJoinFromWithTree($with_tree, $relation_alias);
		}
		return $join;
	}

	public static function addRelation(int $relation_type, string|array|callable $source_fields, $target_model, string|array|callable $target_fields, string $relation_name = null, array $default_options = []) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if ($relation_name === null) {
			if ($relation_type === RelationModel::TYPE_HAS_ONE || $relation_type === RelationModel::TYPE_BELONGS_TO_ONE) {
				$relation_name = EnglishNoun::classNameToSingular($target_model);
			} else {
				$relation_name = EnglishNoun::classNameToPlural($target_model);
			}
		}
		static::$relations[$relation_name] = [
			'type' => $relation_type,
			'source_fields' => $source_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}

	public static function belongsTo(string|array|callable $source_fields, $target_model, string|array|callable $target_fields, string $relation_name = null, array $default_options = []) {
		return static::addRelation(RelationModel::TYPE_BELONGS_TO_ONE, $source_fields, $target_model, $target_fields, $relation_name, $default_options);
	}

	public static function belongsToMany(string|array|callable $source_fields, $target_model, string|array|callable $target_fields, string $relation_name = null, array $default_options = []) {
		return static::addRelation(RelationModel::TYPE_BELONGS_TO_MANY, $source_fields, $target_model, $target_fields, $relation_name, $default_options);
	}

	public static function hasOne(string|array|callable $source_fields, $target_model, string|array|callable $target_fields, string $relation_name = null, array $default_options = []) {
		return static::addRelation(RelationModel::TYPE_HAS_ONE, $source_fields, $target_model, $target_fields, $relation_name, $default_options);
	}

	public static function hasMany(string|array|callable $source_fields, $target_model, string|array|callable $target_fields, string $relation_name = null, array $default_options = []) {
		return static::addRelation(RelationModel::TYPE_HAS_MANY, $source_fields, $target_model, $target_fields, $relation_name, $default_options);
	}
}