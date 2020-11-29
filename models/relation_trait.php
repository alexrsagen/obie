<?php namespace Obie\Models;

trait RelationTrait {
	protected static $relations = [];

	public function getRelated(string $relation_name, array $options = [], bool $count = false) {
		if (is_string($options)) {
			$options = ['conditions' => $options];
		}
		if (!is_array($options)) {
			throw new \InvalidArgumentException('Options must be a string of conditions or an array of options');
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

		switch ($relation['type']) {
		case RelationModel::TYPE_BELONGS_TO_ONE:
		case RelationModel::TYPE_BELONGS_TO_MANY:
		case RelationModel::TYPE_HAS_ONE:
		case RelationModel::TYPE_HAS_MANY:
			$relation_options['conditions'][] = ModelHelpers::getEscapedWhere($relation['target_fields']);
			break;

		case RelationModel::TYPE_BELONGS_TO_MANY_TO_MANY:
		case RelationModel::TYPE_HAS_MANY_TO_MANY:
			$intermediate = $relation['intermediate_model'];
			$relation_options['conditions'][] = ModelHelpers::getEscapedWhere($relation['intermediate_source_fields'], $intermediate::getSource());
			$relation_options['join'] = 'LEFT JOIN ' . $intermediate::getEscapedSource() .
				' ON ' . ModelHelpers::getEscapedOn(
					$relation['intermediate_target_fields'], $intermediate::getSource(),
					$relation['target_fields'], $target::getSource());
			break;
		}

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
			$relation_options['bind'][] = $this->{$key};
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
			return $count ? 1 : $target::findFirst($options);

		case RelationModel::TYPE_BELONGS_TO_MANY:
		case RelationModel::TYPE_HAS_MANY:
			return $count ? $target::count($options) : $target::find($options);

		case RelationModel::TYPE_BELONGS_TO_MANY_TO_MANY:
		case RelationModel::TYPE_HAS_MANY_TO_MANY:
			return $count ? $target::count($options) : $target::find($options);
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

	public static function getJoin(string $relation_name, string $kind = 'LEFT', string $intermediate_kind = 'LEFT') {
		$relation = static::getRelation($relation_name);
		if (!$relation) return false;

		$relation_source = ModelHelpers::getEscapedSource($relation_name);
		$target = $relation['target_model'];
		switch ($relation['type']) {
			case RelationModel::TYPE_BELONGS_TO_ONE:
			case RelationModel::TYPE_BELONGS_TO_MANY:
			case RelationModel::TYPE_HAS_ONE:
			case RelationModel::TYPE_HAS_MANY:
				return $kind . ' JOIN ' . $target::getEscapedSource() .
					' AS ' . $relation_source .
					' ON ' . ModelHelpers::getEscapedOn(
						$relation['target_fields'], $relation_name,
						$relation['source_fields'], static::getSource());

			case RelationModel::TYPE_BELONGS_TO_MANY_TO_MANY:
			case RelationModel::TYPE_HAS_MANY_TO_MANY:
				$intermediate = $relation['intermediate_model'];
				return $intermediate_kind . ' JOIN ' . $intermediate::getEscapedSource() .
					' ON ' . ModelHelpers::getEscapedOn(
						$relation['intermediate_source_fields'], $intermediate::getSource(),
						$relation['source_fields'], static::getSource()) .
					$kind . ' JOIN ' . $target::getEscapedSource() .
					' AS ' . $relation_source .
					' ON ' . ModelHelpers::getEscapedOn(
							$relation['target_fields'], $relation_name,
							$relation['intermediate_target_fields'], $intermediate::getSource());
		}

		return false;
	}

	public static function belongsTo(
		$source_fields,
		$target_model,
		$target_fields,
		string $relation_name = null,
		array $default_options = []
	) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (!is_array($source_fields)) {
			throw new \InvalidArgumentException('Source field(s) must be string or array');
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if (!is_array($target_fields)) {
			throw new \InvalidArgumentException('Target field(s) must be string or array');
		}
		if ($relation_name === null) {
			$relation_name = ModelHelpers::getSingularFromClassNS($target_model);
		}
		static::$relations[$relation_name] = [
			'type' => RelationModel::TYPE_BELONGS_TO_ONE,
			'source_fields' => $source_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}

	public static function belongsToMany(
		$source_fields,
		$target_model,
		$target_fields,
		string $relation_name = null,
		array $default_options = []
	) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (!is_array($source_fields)) {
			throw new \InvalidArgumentException('Source field(s) must be string or array');
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if (!is_array($target_fields)) {
			throw new \InvalidArgumentException('Target field(s) must be string or array');
		}
		if ($relation_name === null) {
			$relation_name = ModelHelpers::getPlural(ModelHelpers::getSingularFromClassNS($target_model));
		}
		static::$relations[$relation_name] = [
			'type' => RelationModel::TYPE_BELONGS_TO_MANY,
			'source_fields' => $source_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}

	public static function belongsToManyToMany(
		$source_fields,
		$intermediate_model,
		$intermediate_source_fields,
		$intermediate_target_fields,
		$target_model,
		$target_fields,
		string $relation_name = null,
		array $default_options = []
	) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (!is_array($source_fields)) {
			throw new \InvalidArgumentException('Source field(s) must be string or array');
		}
		if (is_string($intermediate_source_fields)) {
			$intermediate_source_fields = [$intermediate_source_fields];
		}
		if (!is_array($intermediate_source_fields)) {
			throw new \InvalidArgumentException('Intermediate source field(s) must be string or array');
		}
		if (is_string($intermediate_target_fields)) {
			$intermediate_target_fields = [$intermediate_target_fields];
		}
		if (!is_array($intermediate_target_fields)) {
			throw new \InvalidArgumentException('Intermediate target field(s) must be string or array');
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if (!is_array($target_fields)) {
			throw new \InvalidArgumentException('Target field(s) must be string or array');
		}
		if ($relation_name === null) {
			$relation_name = ModelHelpers::getPlural(ModelHelpers::getSingularFromClassNS($target_model));
		}
		static::$relations[$relation_name] = [
			'type' => RelationModel::TYPE_BELONGS_TO_MANY_TO_MANY,
			'source_fields' => $source_fields,
			'intermediate_model' => $intermediate_model,
			'intermediate_source_fields' => $intermediate_source_fields,
			'intermediate_target_fields' => $intermediate_target_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}

	public static function hasOne(
		$source_fields,
		$target_model,
		$target_fields,
		string $relation_name = null,
		array $default_options = []
	) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (!is_array($source_fields)) {
			throw new \InvalidArgumentException('Source field(s) must be string or array');
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if (!is_array($target_fields)) {
			throw new \InvalidArgumentException('Target field(s) must be string or array');
		}
		if ($relation_name === null) {
			$relation_name = ModelHelpers::getSingularFromClassNS($target_model);
		}
		static::$relations[$relation_name] = [
			'type' => RelationModel::TYPE_HAS_ONE,
			'source_fields' => $source_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}

	public static function hasMany(
		$source_fields,
		$target_model,
		$target_fields,
		string $relation_name = null,
		array $default_options = []
	) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (!is_array($source_fields)) {
			throw new \InvalidArgumentException('Source field(s) must be string or array');
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if (!is_array($target_fields)) {
			throw new \InvalidArgumentException('Target field(s) must be string or array');
		}
		if ($relation_name === null) {
			$relation_name = ModelHelpers::getPlural(ModelHelpers::getSingularFromClassNS($target_model));
		}
		static::$relations[$relation_name] = [
			'type' => RelationModel::TYPE_HAS_MANY,
			'source_fields' => $source_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}

	public static function hasManyToMany(
		$source_fields,
		$intermediate_model,
		$intermediate_source_fields,
		$intermediate_target_fields,
		$target_model,
		$target_fields,
		string $relation_name = null,
		array $default_options = []
	) {
		if (is_string($source_fields)) {
			$source_fields = [$source_fields];
		}
		if (!is_array($source_fields)) {
			throw new \InvalidArgumentException('Source field(s) must be string or array');
		}
		if (is_string($intermediate_source_fields)) {
			$intermediate_source_fields = [$intermediate_source_fields];
		}
		if (!is_array($intermediate_source_fields)) {
			throw new \InvalidArgumentException('Intermediate source field(s) must be string or array');
		}
		if (is_string($intermediate_target_fields)) {
			$intermediate_target_fields = [$intermediate_target_fields];
		}
		if (!is_array($intermediate_target_fields)) {
			throw new \InvalidArgumentException('Intermediate target field(s) must be string or array');
		}
		if (is_string($target_fields)) {
			$target_fields = [$target_fields];
		}
		if (!is_array($target_fields)) {
			throw new \InvalidArgumentException('Target field(s) must be string or array');
		}
		if ($relation_name === null) {
			$relation_name = ModelHelpers::getPlural(ModelHelpers::getSingularFromClassNS($target_model));
		}
		static::$relations[$relation_name] = [
			'type' => RelationModel::TYPE_HAS_MANY_TO_MANY,
			'source_fields' => $source_fields,
			'intermediate_model' => $intermediate_model,
			'intermediate_source_fields' => $intermediate_source_fields,
			'intermediate_target_fields' => $intermediate_target_fields,
			'target_model' => $target_model,
			'target_fields' => $target_fields,
			'default_options' => $default_options
		];
	}
}
