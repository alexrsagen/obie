<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

class SoftUpdateModel extends BaseModel {
	use SoftUpdateTrait;
}
