<?php namespace ZeroX\Validation;
if (!defined('IN_ZEROX')) {
	exit;
}

class RegexValidator implements IValidator {
	private $regex;

	public function __construct(string $regex) {
		$this->regex = $regex;
	}

	public function validate($input) : bool {
		if (!is_string($input)) return false;
		return preg_match($this->regex, $input) === 1;
	}
}
