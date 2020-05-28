<?php namespace ZeroX\Models;

trait SoftCreateTrait {
	protected $_set_created_at = true;

	public function beforeCreateSetCreatedAt() {
		if ($this->_set_created_at) {
			$this->created_at = date("Y-m-d H:i:s");
		}
	}
}
