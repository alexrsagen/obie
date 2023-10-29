<?php namespace Obie\Security\U2f;
use Obie\Security\U2f;

class Exception extends \Exception {
	const ERR_CHALLENGE_MISMATCH       = -1;
	const ERR_RP_ID_MISMATCH           = -2;
	const ERR_CLIENT_ERROR             = -3;
	const ERR_INVALID_CLIENT_DATA      = -4;
	const ERR_INVALID_DATA             = -5;
	const ERR_INVALID_SIGNATURE_LENGTH = -6;
	const ERR_VERSION_NOT_SUPPORTED    = -7;

	function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, public int $client_error_code = U2f::ERROR_CODE_OK) {
		parent::__construct($message, $code, $previous);
	}
}