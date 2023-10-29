<?php namespace Obie\Vars;

class VarContainer {
	use VarTrait;

	public function __construct(array|VarCollection &$storage = []) {
		$this->_init_vars($storage);
	}
}
