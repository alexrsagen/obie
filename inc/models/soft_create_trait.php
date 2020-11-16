<?php namespace ZeroX\Models;

trait SoftCreateTrait {
	protected $_set_created_at = true;

	protected function beforeCreateSetCreatedAt() {
		if (!$this->_set_created_at || !static::columnExists('created_at')) return;
		$this->created_at = date("Y-m-d H:i:s");
	}
}
