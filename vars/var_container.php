<?php namespace Obie\Vars;

class VarContainer {
	use VarTrait;

	public function __construct(&$storage = null) {
		$this->_init_vars($storage);
	}
}
