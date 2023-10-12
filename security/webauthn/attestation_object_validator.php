<?php namespace Obie\Security\Webauthn;
use Obie\Validation\IValidator;

class AttestationObjectValidator implements IValidator {
	private $message = '';

	public function getMessage() {
		return $this->message;
	}

	public function validate($input) : bool {
		if (!is_array($input)) {
			$this->message = 'not an array';
			return false;
		}
		if (!array_key_exists('fmt', $input) || !is_string($input['fmt'])) {
			$this->message = 'fmt is missing or not a string';
			return false;
		}
		if (preg_match('/[^\x21\x23-\x5b\x5d-\x7E]/', $input['fmt']) === 1) {
			$this->message = 'fmt contains invalid characters';
			return false;
		}
		switch ($input['fmt']) {
		case 'android-key':
		case 'packed':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			if (!array_key_exists('alg', $input['attStmt']) || !is_int($input['attStmt']['alg'])) {
				$this->message = 'attStmt.alg is missing or not an int';
				return false;
			}
			if (!array_key_exists('sig', $input['attStmt']) || !is_string($input['attStmt']['sig'])) {
				$this->message = 'attStmt.sig is missing or not a string';
				return false;
			}
			// optional in 'packed': x5c
			if ($input['fmt'] === 'android-key' && (!array_key_exists('x5c', $input['attStmt']) || !is_array($input['attStmt']['x5c']) || count($input['attStmt']['x5c']) < 1)) {
				$this->message = 'attStmt.x5c is missing or not an array or empty';
				return false;
			}
			break;
		case 'android-safetynet':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			if (!array_key_exists('ver', $input['attStmt']) || !is_string($input['attStmt']['ver'])) {
				$this->message = 'attStmt.ver is missing or not a string';
				return false;
			}
			if (!array_key_exists('response', $input['attStmt']) || !is_string($input['attStmt']['response'])) {
				$this->message = 'attStmt.response is missing or not a string';
				return false;
			}
			break;
		case 'fido-u2f':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			if (!array_key_exists('sig', $input['attStmt']) || !is_string($input['attStmt']['sig'])) {
				$this->message = 'attStmt.sig is missing or not a string';
				return false;
			}
			if (!array_key_exists('x5c', $input['attStmt']) || !is_array($input['attStmt']['x5c']) || count($input['attStmt']['x5c']) !== 1) {
				$this->message = 'attStmt.x5c is missing or not an array or empty';
				return false;
			}
			break;
		case 'tpm':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			if (!array_key_exists('ver', $input['attStmt']) || !is_string($input['attStmt']['ver'])) {
				$this->message = 'attStmt.ver is missing or not a string';
				return false;
			}
			if (!array_key_exists('alg', $input['attStmt']) || !is_int($input['attStmt']['alg'])) {
				$this->message = 'attStmt.alg is missing or not an int';
				return false;
			}
			if (!array_key_exists('x5c', $input['attStmt']) || !is_array($input['attStmt']['x5c']) || count($input['attStmt']['x5c']) < 1) {
				$this->message = 'attStmt.x5c is missing or not an array or empty';
				return false;
			}
			if (!array_key_exists('sig', $input['attStmt']) || !is_string($input['attStmt']['sig'])) {
				$this->message = 'attStmt.sig is missing or not a string';
				return false;
			}
			if (!array_key_exists('certInfo', $input['attStmt']) || !is_string($input['attStmt']['certInfo'])) {
				$this->message = 'attStmt.certInfo is missing or not a string';
				return false;
			}
			if (!array_key_exists('pubArea', $input['attStmt']) || !is_string($input['attStmt']['pubArea'])) {
				$this->message = 'attStmt.pubArea is missing or not a string';
				return false;
			}
			break;
		case 'none':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			break;
		case 'apple':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			if (!array_key_exists('x5c', $input['attStmt']) || !is_array($input['attStmt']['x5c']) || count($input['attStmt']['x5c']) < 1) {
				$this->message = 'attStmt.x5c is missing or not an array or empty';
				return false;
			}
			break;
		case 'apple-appattest':
			if (!array_key_exists('attStmt', $input) || !is_array($input['attStmt'])) {
				$this->message = 'attStmt is missing or not an array';
				return false;
			}
			if (!array_key_exists('x5c', $input['attStmt']) || !is_array($input['attStmt']['x5c']) || count($input['attStmt']['x5c']) < 1) {
				$this->message = 'attStmt.x5c is missing or not an array or empty';
				return false;
			}
			if (!array_key_exists('receipt', $input['attStmt']) || !is_string($input['attStmt']['receipt'])) {
				$this->message = 'attStmt.receipt is missing or not an array or empty';
				return false;
			}
			break;
		}
		if (!array_key_exists('authData', $input) || !is_string($input['authData'])) {
			$this->message = 'authData is missing or not a string';
			return false;
		}
		return true;
	}
}