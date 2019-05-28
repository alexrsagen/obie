<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	exit;
}

class SoftUpdateModel extends BaseModel {
	use SoftUpdateTrait;
}
