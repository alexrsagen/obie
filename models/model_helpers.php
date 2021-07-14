<?php namespace Obie\Models;

class ModelHelpers {
	public static function getEscapedList(array $column_names = [], string $table_prefix = null) {
		$list_parts = [];
		foreach ($column_names as $i => $key) {
			$list_parts[$i] = '';
			if ($table_prefix !== null) {
				$list_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
			}
			$list_parts[$i] .= static::getEscapedSource($key);
		}
		return implode(',', $list_parts);
	}

	public static function getEscapedSet(array $column_names = [], string $table_prefix = null) {
		$set_parts = [];
		foreach ($column_names as $i => $key) {
			$set_parts[$i] = '';
			if ($table_prefix !== null) {
				$set_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
			}
			$set_parts[$i] .= static::getEscapedSource($key) . ' = ?';
		}
		return implode(',', $set_parts);
	}

	public static function getEscapedWhere(array $column_names = [], string $table_prefix = null, string $kind = 'AND') {
		$where_parts = [];
		foreach ($column_names as $i => $key) {
			$where_parts[$i] = '';
			if ($table_prefix !== null) {
				$where_parts[$i] .= static::getEscapedSource($table_prefix) . '.';
			}
			$where_parts[$i] .= static::getEscapedSource($key) . ' = ?';
		}
		return implode(' ' . trim($kind) . ' ', $where_parts);
	}

	public static function getEscapedOn(array $source_column_names = [], string $source_table_prefix = null,
		array $target_column_names = [], string $target_table_prefix = null) {
		if (count($source_column_names) !== count($target_column_names)) {
			throw new \InvalidArgumentException('Source and target column count must be equal');
		}
		$on_parts = [];
		for ($i = 0; $i < count($source_column_names); $i++) {
			$on_parts[$i] = '';
			if ($source_table_prefix !== null) {
				$on_parts[$i] .= static::getEscapedSource($source_table_prefix) . '.';
			}
			$on_parts[$i] .= static::getEscapedSource($source_column_names[$i]) . ' = ';
			if ($target_table_prefix !== null) {
				$on_parts[$i] .= static::getEscapedSource($target_table_prefix) . '.';
			}
			$on_parts[$i] .= static::getEscapedSource($target_column_names[$i]);
		}
		return implode(' AND ', $on_parts);
	}

	public static function getEscapedSource(string $source) {
		return '`' . str_replace('`', '``', $source) . '`';
	}
}
