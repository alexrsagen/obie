<?php namespace Obie\Validation;

class WebauthnAuthDataValidator implements IValidator {
	private $message = '';

	public function getMessage() {
		return $this->message;
	}

	public function validate($input) : bool {
		if (!is_array($input)) {
			$this->message = 'not an array';
			return false;
		}
		if (!array_key_exists('rpIdHash', $input) || !is_string($input['rpIdHash'])) {
			$this->message = 'rpIdHash is missing or not a string';
			return false;
		}
		if (!array_key_exists('flags', $input) || !is_int($input['flags'])) {
			$this->message = 'flags is missing or not an int';
			return false;
		}
		if (!array_key_exists('signCount', $input) || !is_int($input['signCount'])) {
			$this->message = 'signCount is missing or not an int';
			return false;
		}
		if (!array_key_exists('aaguid', $input) || !is_string($input['aaguid']) && !is_null($input['aaguid'])) {
			$this->message = 'aaguid is missing or not a string';
			return false;
		}
		if (!array_key_exists('credentialIdLength', $input) || !is_int($input['credentialIdLength'])) {
			$this->message = 'credentialIdLength is missing or not an int';
			return false;
		}
		if (!array_key_exists('credentialId', $input) || !is_string($input['credentialId']) && !is_null($input['credentialId'])) {
			$this->message = 'credentialId is missing or not a string';
			return false;
		}
		if (!array_key_exists('credentialPublicKey', $input) || !is_string($input['credentialPublicKey']) && !is_null($input['credentialPublicKey'])) {
			$this->message = 'credentialPublicKey is missing or not a string';
			return false;
		}
		return true;
	}
}