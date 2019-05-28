<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	exit;
}

trait SoftCreateTrait {
	private $_set_created_at = true;

	public function beforeCreateSetCreatedAt() {
		if ($this->_set_created_at) {
			$this->created_at = date("Y-m-d H:i:s");
		}
	}
}
