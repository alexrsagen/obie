<?php namespace ZeroX\Validation;
use \ZeroX\Validation\IValidator;

class WebauthnClientDataValidator implements IValidator {
	private $message = '';

	public function getMessage() {
		return $this->message;
	}

	public function validate($input) : bool {
		if (!is_array($input)) {
			$this->message = 'not an array';
			return false;
		}
		if (!array_key_exists('type', $input) || !is_string($input['type'])) {
			$this->message = 'type is missing or not a string';
			return false;
		}
		if (!array_key_exists('challenge', $input) || !is_string($input['challenge'])) {
			$this->message = 'challenge is missing or not a string';
			return false;
		}
		if (!array_key_exists('origin', $input) || !is_string($input['origin'])) {
			$this->message = 'origin is missing or not a string';
			return false;
		}
		// optional: tokenBinding
		if (array_key_exists('tokenBinding', $input)) {
			if (!array_key_exists('status', $input['tokenBinding']) || !is_string($input['tokenBinding']['status'])) {
				$this->message = 'tokenBinding.status is missing or not a string';
				return false;
			}

			if (!in_array(['present', 'supported'], $input['tokenBinding']['status'], true)) {
				$this->message = 'tokenBinding.status string value is not known';
				return false;
			}

			// optional: tokenBinding.id
		}
		return true;
	}
}