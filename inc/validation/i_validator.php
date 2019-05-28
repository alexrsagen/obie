<?php namespace ZeroX\Validation;
if (!defined('IN_ZEROX')) {
	exit;
}

interface IValidator {
	public function validate($input) : bool;
}
