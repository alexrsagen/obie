<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

class ModelCollection implements \ArrayAccess, \IteratorAggregate, \Countable, \Serializable {
	protected $models;
	protected $error;

	public function __construct(array $models = []) {
		$this->models = $models;
	}

	// ArrayAccess

	public function offsetExists($offset) {
		return isset($this->models[$offset]);
	}

	public function offsetGet($offset) {
		return $this->models[$offset];
	}

	public function offsetSet($offset, $value) {
		if ($offset === null) {
			$this->models[] = $value;
		} else {
			$this->models[$offset] = $value;
		}
	}

	public function offsetUnset($offset) {
		unset($this->models[$offset]);
	}

	// IteratorAggregate

	public function getIterator() {
		return new \ArrayIterator($this->models);
	}

	// Countable

	public function count() {
		return count($this->models);
	}

	// Serializable

	public function serialize() {
		return serialize($this->models);
	}

	public function unserialize($serialized) {
		$v = unserialize($serialized);
		if (is_array($v)) $this->models = $v;
	}

	// Custom methods

	public function add($model) {
		$this->models[] = $model;
	}

	public function toArray() {
		$arr = [];
		foreach ($this->models as $model) {
			$arr[] = $model->toArray();
		}
		return $arr;
	}

	public function getRelated(string $relation_name, array $options = []) {
		$results = new static();
		foreach ($this->models as $model) {
			$model_results = $model->getRelated($relation_name, $options);
			if ($model_results instanceof static) {
				foreach ($model_results as $model_result) {
					$results[] = $model_result;
				}
			} else {
				$results[] = $model_results;
			}
		}
		return $results;
	}

	public function load(bool $force_reload = false) {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->load($force_reload)) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function save() {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->save()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function create() {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->create()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function update() {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->update()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function delete() {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->delete()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function getLastError() {
		return $this->error;
	}

	public function __call(string $name, array $arguments) {
		$retval_collection = null;
		$retval_other = null;
		$retval_is_bool = false;
		$retval_is_collection = false;
		foreach ($this->models as $model) {
			$cur_retval = $model->{$name}(...$arguments);
			if (is_bool($cur_retval)) {
				$retval_is_bool = true;
				$this->error = null;
				if (!$cur_retval) {
					$this->error = $model->getLastError();
					return false;
				}
			} elseif ($cur_retval instanceof static) {
				$retval_is_collection = true;
				if ($retval_collection === null) {
					$retval_collection = new static();
				}
				foreach ($cur_retval as $cur_model) {
					$retval_collection->add($cur_model);
				}
			} else {
				if ($retval_other === null) {
					$retval_other = [];
				}
				$retval_other[] = $cur_retval;
			}
		}
		if ($retval_is_bool) {
			return true;
		} elseif ($retval_is_collection) {
			return $retval_collection;
		}
		return $retval_other;
	}
}
