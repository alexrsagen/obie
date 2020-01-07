<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	return;
}

trait BaseModelTrait {
	protected static $source          = null;
	protected static $source_singular = null;
	protected static $columns         = [];
	protected static $pk              = [];
	protected static $db              = null;
	protected static $find_error      = null;
}
