<?php namespace ZeroX\Vars;
if (!defined('IN_ZEROX')) {
	exit;
}

class VarContainer {
	use VarTrait;

	public function __construct(&$storage = null) {
		$this->_init_vars($storage);
	}
}
