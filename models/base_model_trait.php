<?php namespace Obie\Models;

trait BaseModelTrait {
	protected static $source          = null;
	protected static $source_singular = null;
	protected static $columns         = [];
	protected static $pk              = [];
	protected static $db              = null;
	protected static $ro_db           = null;
	protected static $find_error      = null;
}
