<?php namespace ZeroX\Vars;
if (!defined('IN_ZEROX')) {
	return;
}

class VarContainer {
	use VarTrait;

	public function __construct(&$storage = null) {
		$this->_init_vars($storage);
	}
}
