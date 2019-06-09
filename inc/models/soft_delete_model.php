<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

class SoftDeleteModel extends BaseModel {
	use SoftDeleteTrait;
}
