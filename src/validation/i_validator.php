<?php namespace Obie\Validation;

interface IValidator {
	public function validate($input) : bool;
}
