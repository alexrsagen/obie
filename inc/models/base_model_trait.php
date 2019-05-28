<?php namespace ZeroX\Models;
if (!defined('IN_ZEROX')) {
	exit;
}

trait BaseModelTrait {
	protected static $source          = null;
	protected static $source_singular = null;
	protected static $columns         = [];
	protected static $pk              = [];
	protected static $db              = null;
}
