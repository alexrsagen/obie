<?php namespace ZeroX\Validation;
if (!defined('IN_ZEROX')) {
	return;
}

interface IValidator {
	public function validate($input) : bool;
}
