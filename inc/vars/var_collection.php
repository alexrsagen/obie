<?php namespace ZeroX\Vars;
if (!defined('IN_ZEROX')) {
	exit;
}

class VarCollection implements \ArrayAccess, \IteratorAggregate, \Countable, \Serializable {
	private $storage;
	private $assoc;

	// Magic methods

	public function __construct(&$storage = null, bool $assoc = false) {
		if ($storage !== null) {
			$this->storage = &$storage;
		} else {
			$this->storage = [];
		}
		$this->assoc = $assoc;
	}

	public function __get(string $name) {
		if (!isset($this->storage[$name])) {
			$this->storage[$name] = $this->assoc ? [] : new self();
		}
		return $this->storage[$name];
	}

	public function __set(string $name, $value) {
		$this->storage[$name] = $value;
	}

	// ArrayAccess

	public function offsetExists($offset) {
		return isset($this->storage[$offset]);
	}

	public function offsetGet($offset) {
		if (!isset($this->storage[$offset])) {
			$this->storage[$offset] = $this->assoc ? [] : new self();
		}
		return $this->storage[$offset];
	}

	public function offsetSet($offset, $value) {
		if ($offset === null) {
			$this->storage[] = $value;
		} else {
			$this->storage[$offset] = $value;
		}
	}

	public function offsetUnset($offset) {
		unset($this->storage[$offset]);
	}

	// IteratorAggregate

	public function getIterator() {
		return new \ArrayIterator($this->storage);
	}

	// Countable

	public function count() {
		return count($this->storage);
	}

	// Serializable

	public function serialize() {
		return serialize($this->storage);
	}

	public function unserialize($serialized) {
		$v = unserialize($serialized);
		if (is_array($v)) $this->storage = $v;
	}

	// Custom methods

	public function getContainer() {
		return new VarContainer($this->storage);
	}

	public function get(...$v) {
		$cur = &$this->storage;
		foreach ($v as $key) {
			if (!isset($cur[$key])) {
				return null;
			}
			$cur = &$cur[$key];
		}
		return $cur;
	}

	public function set(...$v) {
		if (count($v) < 2) {
			throw new \InvalidArgumentException('Both key and value must be provided');
		}
		$cur = &$this->storage;
		foreach ($v as $i => $key) {
			if ($i === count($v)-2) {
				$cur[$key] = $v[$i+1];
				return null;
			} else {
				if (!is_array($cur) && !is_a($cur, '\ZeroX\VarCollection')) {
					throw new \InvalidArgumentException('Full path from keys could not be accessed');
				}
				if (!isset($cur[$key])) {
					$cur[$key] = $this->assoc ? [] : new self();
				}
				$cur = &$cur[$key];
			}
		}
		return null;
	}

	public function unset(...$v) {
		$cur = &$this->storage;
		foreach ($v as $i => $key) {
			if ((!is_array($cur) && !is_a($cur, '\ZeroX\VarCollection')) || !isset($cur[$key])) {
				return null;
			}
			if ($i === count($v)-1) {
				unset($cur[$key]);
			} else {
				$cur = &$cur[$key];
			}
		}
		return null;
	}

	public function isset(...$v) {
		$cur = &$this->storage;
		foreach ($v as $key) {
			if ((!is_array($cur) && !is_a($cur, '\ZeroX\VarCollection')) || !isset($cur[$key])) {
				return false;
			}
			$cur = &$cur[$key];
		}
		return true;
	}
}
