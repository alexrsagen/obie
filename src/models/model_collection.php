<?php namespace Obie\Models;

/**
 * @template T of BaseModel
 * @property T[] $models
 * @package Obie\Models
 */
class ModelCollection implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable {
	protected array $models = [];
	protected ?string $error = null;

	public function __construct(array $models = []) {
		$this->models = $models;
	}

	// ArrayAccess

	/**
	 * @param int $offset
	 */
	public function offsetExists($offset): bool {
		return isset($this->models[$offset]);
	}

	/**
	 * @return T
	 */
	public function offsetGet($offset): mixed {
		return $this->models[$offset];
	}

	/**
	 * @param ?int $offset
	 * @param T $value
	 */
	public function offsetSet($offset, $value): void {
		if ($offset === null) {
			$this->models[] = $value;
		} else {
			$this->models[$offset] = $value;
		}
	}

	/**
	 * @param int $offset
	 */
	public function offsetUnset($offset): void {
		unset($this->models[$offset]);
	}

	/**
	 * IteratorAggregate
	 * @return \Traversable<int, T>|T[]
	 */
	public function getIterator(): \Traversable {
		return new \ArrayIterator($this->models);
	}

	/**
	 * Countable
	 * @return int<0, max>
	 */
	public function count(): int {
		return count($this->models);
	}

	/**
	 * Serializable
	 * @return T[]
	 */
	public function __serialize(): array {
		return $this->models;
	}

	/**
	 * @param T[] $data
	 */
	public function __unserialize(array $data) {
		$this->models = $data;
	}

	/**
	 * JsonSerializable
	 * @return array[]
	 */
	public function jsonSerialize(): mixed {
		return $this->toArray();
	}

	// Custom methods

	/**
	 * @param T $model
	 */
	public function add($model) {
		$this->models[] = $model;
	}

	/**
	 * @return array[]
	 */
	public function toArray() {
		$arr = [];
		foreach ($this->models as $model) {
			$arr[] = $model->toArray();
		}
		return $arr;
	}

	public function getRelated(string $relation_name, array $options = [], bool $count = false): static|int {
		if ($count) {
			$results = 0;
		} else {
			$results = new static();
		}
		foreach ($this->models as $model) {
			if (!method_exists($model, 'getRelated')) continue;
			$model_results = $model->getRelated($relation_name, $options, $count);
			if ($count) {
				if (is_int($model_results)) $results += $model_results;
			} elseif ($model_results instanceof static) {
				foreach ($model_results as $model_result) {
					$results->add($model_result);
				}
			} elseif (!empty($model_results)) {
				$results[] = $model_results;
			}
		}
		return $results;
	}

	public function load(): bool {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->load()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function save(): bool {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->save()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function create(): bool {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->create()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function update(): bool {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->update()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function delete(): bool {
		foreach ($this->models as $model) {
			$this->error = null;
			if (!$model->delete()) {
				$this->error = $model->getLastError();
				return false;
			}
		}
		return true;
	}

	public function getLastError(): ?string {
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
