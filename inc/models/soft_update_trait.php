<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

trait SoftUpdateTrait {
	protected $_set_updated_at = true;

	public function beforeUpdateSetUpdatedAt() {
		if ($this->_set_updated_at) {
			$this->updated_at = date("Y-m-d H:i:s");
		}
	}
}
