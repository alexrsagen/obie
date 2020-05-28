<?php namespace ZeroX\Validation;

interface IValidator {
	public function validate($input) : bool;
}
