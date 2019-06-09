<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

trait SoftDeleteTrait {
	private $_set_deleted_at = true;

	public function beforeDeleteSetDeletedAt() {
		if ($this->_set_deleted_at) {
			$this->deleted_at = date("Y-m-d H:i:s");
			if (property_exists($this, '_set_updated_at')) {
				$this->_set_updated_at = false;
			}
			$this->update();
			if (property_exists($this, '_set_updated_at')) {
				$this->_set_updated_at = true;
			}
			return true;
		}
	}
}
