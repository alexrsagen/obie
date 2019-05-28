<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	exit;
}

class SoftDeleteModel extends BaseModel {
	use SoftDeleteTrait;
}
