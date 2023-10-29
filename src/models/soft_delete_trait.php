<?php namespace Obie\Models;

trait SoftDeleteTrait {
	protected $_set_deleted_at = true;

	protected function beforeDeleteSetDeletedAt() {
		if (!$this->_set_deleted_at || !static::columnExists('deleted_at')) return;
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
