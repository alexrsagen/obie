<?php namespace Obie\Models;

trait SoftUpdateTrait {
	protected $_set_updated_at = true;

	protected function beforeUpdateSetUpdatedAt() {
		if (!$this->_set_updated_at || !static::columnExists('updated_at')) return;
		$this->updated_at = date("Y-m-d H:i:s");
	}
}
