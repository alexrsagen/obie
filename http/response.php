<?php namespace Obie\Http;
use \Obie\Encoding\Json;

class Response {	// Constants

	// HTTP 1xx - Informational
	const HTTP_CONTINUE                               = 100;
	const HTTP_SWITCHING_PROTOCOLS                    = 101;
	const HTTP_PROCESSING                             = 102;

	// HTTP 2xx - Success
	const HTTP_OK                                     = 200;
	const HTTP_CREATED                                = 201;
	const HTTP_ACCEPTED                               = 202;
	const HTTP_NON_AUTHORITATIVE_INFORMATION          = 203;
	const HTTP_NO_CONTENT                             = 204;
	const HTTP_RESET_CONTENT                          = 205;
	const HTTP_PARTIAL_CONTENT                        = 206;
	const HTTP_MULTI_STATUS                           = 207;
	const HTTP_ALREADY_REPORTED                       = 208;
	const HTTP_IM_USED                                = 226;

	// HTTP 3xx - Redirection
	const HTTP_MULTIPLE_CHOICES                       = 300;
	const HTTP_MOVED_PERMANENTLY                      = 301;
	const HTTP_FOUND                                  = 302;
	const HTTP_SEE_OTHER                              = 303;
	const HTTP_NOT_MODIFIED                           = 304;
	const HTTP_USE_PROXY                              = 305;
	const HTTP_TEMPORARY_REDIRECT                     = 307;
	const HTTP_PERMANENT_REDIRECT                     = 308;

	// HTTP 4xx - Client Error
	const HTTP_BAD_REQUEST                            = 400;
	const HTTP_UNAUTHORIZED                           = 401;
	const HTTP_PAYMENT_REQUIRED                       = 402;
	const HTTP_FORBIDDEN                              = 403;
	const HTTP_NOT_FOUND                              = 404;
	const HTTP_METHOD_NOT_ALLOWED                     = 405;
	const HTTP_NOT_ACCEPTABLE                         = 406;
	const HTTP_PROXY_AUTHENTICATION_REQUIRED          = 407;
	const HTTP_REQUEST_TIMEOUT                        = 408;
	const HTTP_CONFLICT                               = 409;
	const HTTP_GONE                                   = 410;
	const HTTP_LENGTH_REQUIRED                        = 411;
	const HTTP_PRECONDITION_FAILED                    = 412;
	const HTTP_PAYLOAD_TOO_LARGE                      = 413;
	const HTTP_REQUEST_URI_TOO_LONG                   = 414;
	const HTTP_UNSUPPORTED_MEDIA_TYPE                 = 415;
	const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE        = 416;
	const HTTP_EXPECTATION_FAILED                     = 417;
	const HTTP_IM_A_TEAPOT                            = 418;
	const HTTP_MISDIRECTED_REQUEST                    = 421;
	const HTTP_UNPROCESSABLE_ENTITY                   = 422;
	const HTTP_LOCKED                                 = 423;
	const HTTP_FAILED_DEPENDENCY                      = 424;
	const HTTP_UPGRADE_REQUIRED                       = 426;
	const HTTP_PRECONDITION_REQUIRED                  = 428;
	const HTTP_TOO_MANY_REQUESTS                      = 429;
	const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE        = 431;
	const HTTP_CONNECTION_CLOSED_WITHOUT_RESPONSE     = 444;
	const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS          = 451;
	const HTTP_CLIENT_CLOSED_REQUEST                  = 499;

	// HTTP 5xx - Server Error
	const HTTP_INTERNAL_SERVER_ERROR                  = 500;
	const HTTP_NOT_IMPLEMENTED                        = 501;
	const HTTP_BAD_GATEWAY                            = 502;
	const HTTP_SERVICE_UNAVAILABLE                    = 503;
	const HTTP_GATEWAY_TIMEOUT                        = 504;
	const HTTP_HTTP_VERSION_NOT_SUPPORTED             = 505;
	const HTTP_VARIANT_ALSO_NEGOTIATES                = 506;
	const HTTP_INSUFFICIENT_STORAGE                   = 507;
	const HTTP_LOOP_DETECTED                          = 508;
	const HTTP_NOT_EXTENDED                           = 510;
	const HTTP_NETWORK_AUTHENTICATION_REQUIRED        = 511;
	const HTTP_NETWORK_CONNECT_TIMEOUT_ERROR          = 599;

	const HTTP_STATUSTEXT = [
		// HTTP 1xx - Informational
		self::HTTP_CONTINUE                           => 'Continue',
		self::HTTP_SWITCHING_PROTOCOLS                => 'Switching protocols',
		self::HTTP_PROCESSING                         => 'Processing',

		// HTTP 2xx - Success
		self::HTTP_OK                                 => 'Ok',
		self::HTTP_CREATED                            => 'Created',
		self::HTTP_ACCEPTED                           => 'Accepted',
		self::HTTP_NON_AUTHORITATIVE_INFORMATION      => 'Non-authoritative information',
		self::HTTP_NO_CONTENT                         => 'No content',
		self::HTTP_RESET_CONTENT                      => 'Reset content',
		self::HTTP_PARTIAL_CONTENT                    => 'Partial content',
		self::HTTP_MULTI_STATUS                       => 'Multi status',
		self::HTTP_ALREADY_REPORTED                   => 'Already reported',
		self::HTTP_IM_USED                            => 'IM used',

		// HTTP 3xx - Redirection
		self::HTTP_MULTIPLE_CHOICES                   => 'Multiple choices',
		self::HTTP_MOVED_PERMANENTLY                  => 'Moved permanently',
		self::HTTP_FOUND                              => 'Found',
		self::HTTP_SEE_OTHER                          => 'See other',
		self::HTTP_NOT_MODIFIED                       => 'Not modified',
		self::HTTP_USE_PROXY                          => 'Use proxy',
		self::HTTP_TEMPORARY_REDIRECT                 => 'Temporary redirect',
		self::HTTP_PERMANENT_REDIRECT                 => 'Permanent redirect',

		// HTTP 4xx - Client Error
		self::HTTP_BAD_REQUEST                        => 'Bad request',
		self::HTTP_UNAUTHORIZED                       => 'Unauthorized',
		self::HTTP_PAYMENT_REQUIRED                   => 'Payment required',
		self::HTTP_FORBIDDEN                          => 'Forbidden',
		self::HTTP_NOT_FOUND                          => 'Not found',
		self::HTTP_METHOD_NOT_ALLOWED                 => 'Method not allowed',
		self::HTTP_NOT_ACCEPTABLE                     => 'Not acceptable',
		self::HTTP_PROXY_AUTHENTICATION_REQUIRED      => 'Proxy authentication required',
		self::HTTP_REQUEST_TIMEOUT                    => 'Request timeout',
		self::HTTP_CONFLICT                           => 'Conflict',
		self::HTTP_GONE                               => 'Gone',
		self::HTTP_LENGTH_REQUIRED                    => 'Length required',
		self::HTTP_PRECONDITION_FAILED                => 'Precondition failed',
		self::HTTP_PAYLOAD_TOO_LARGE                  => 'Payload too large',
		self::HTTP_REQUEST_URI_TOO_LONG               => 'Request URI too long',
		self::HTTP_UNSUPPORTED_MEDIA_TYPE             => 'Unsupported media type',
		self::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE    => 'Requested range not satisfiable',
		self::HTTP_EXPECTATION_FAILED                 => 'Expectation failed',
		self::HTTP_IM_A_TEAPOT                        => 'I\'m a teapot',
		self::HTTP_MISDIRECTED_REQUEST                => 'Misdirected request',
		self::HTTP_UNPROCESSABLE_ENTITY               => 'Unprocessable entity',
		self::HTTP_LOCKED                             => 'Locked',
		self::HTTP_FAILED_DEPENDENCY                  => 'Failed dependency',
		self::HTTP_UPGRADE_REQUIRED                   => 'Upgrade required',
		self::HTTP_PRECONDITION_REQUIRED              => 'Precondition required',
		self::HTTP_TOO_MANY_REQUESTS                  => 'Too many requests',
		self::HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE    => 'Request header fields too large',
		self::HTTP_CONNECTION_CLOSED_WITHOUT_RESPONSE => 'Connection closed without response',
		self::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS      => 'Unavailable for legal reasons',
		self::HTTP_CLIENT_CLOSED_REQUEST              => 'Client closed request',

		// HTTP 5xx - Server Error
		self::HTTP_INTERNAL_SERVER_ERROR              => 'Internal server error',
		self::HTTP_NOT_IMPLEMENTED                    => 'Not implemented',
		self::HTTP_BAD_GATEWAY                        => 'Bad gateway',
		self::HTTP_SERVICE_UNAVAILABLE                => 'Service unavailable',
		self::HTTP_GATEWAY_TIMEOUT                    => 'Gateway timeout',
		self::HTTP_HTTP_VERSION_NOT_SUPPORTED         => 'HTTP version not supported',
		self::HTTP_VARIANT_ALSO_NEGOTIATES            => 'Variant also negotiates',
		self::HTTP_INSUFFICIENT_STORAGE               => 'Insufficient storage',
		self::HTTP_LOOP_DETECTED                      => 'Loop detected',
		self::HTTP_NOT_EXTENDED                       => 'Not extended',
		self::HTTP_NETWORK_AUTHENTICATION_REQUIRED    => 'Network authentication required',
		self::HTTP_NETWORK_CONNECT_TIMEOUT_ERROR      => 'Network connect timeout error'
	];

	// HTTP response code classes
	const HTTP_CLASS_NO_RESPONSE   = 0;
	const HTTP_CLASS_INFORMATIONAL = 1;
	const HTTP_CLASS_SUCCESS       = 2;
	const HTTP_CLASS_REDIRECTION   = 3;
	const HTTP_CLASS_CLIENT_ERROR  = 4;
	const HTTP_CLASS_SERVER_ERROR  = 5;

	protected $data,
	          $errors,
	          $code,
	          $headers;

	function __construct($data = null, array $errors = [], int $code = 0, array $headers = []) {
		$this->data    = $data;
		$this->errors  = $errors;
		$this->code    = $code;
		$this->headers = $headers;
	}

	public function getData(...$path) {
		if (count($path) === 1 && is_array($path[0])) {
			$path = $path[0];
		}
		$cur = $this->data;
		foreach ($path as $k) {
			if (!is_array($cur) || !array_key_exists($k, $cur)) {
				return null;
			}
			$cur = $cur[$k];
		}
		return $cur;
	}

	public function setData($data) {
		$this->data = $data;
		return $this;
	}

	public function getHeader(string $key = null) {
		if ($key === null) {
			return $this->headers;
		}
		$key = strtolower($key);
		if (array_key_exists($key, $this->headers)) {
			return $this->headers[$key];
		}
		return null;
	}

	public function setHeader($key, string $value = null) {
		if (!is_string($key) && !is_array($key)) {
			throw new \InvalidArgumentException('key must be string or array');
		}
		if ($value === null) {
			$this->headers = $key;
			return $this;
		}
		$this->headers[$key] = $value;
		return $this;
	}

	public function getFirstError() {
		$error = null;
		$keys  = array_keys($this->errors);
		if (count($keys) > 0) {
			$error = $this->errors[$keys[0]];
		}
		if (!is_string($error)) {
			$error = Json::encode($error);
		}
		return $error;
	}

	public function getLastError() {
		$error = null;
		$keys  = array_keys($this->errors);
		if (count($keys) > 0) {
			$error = $this->errors[$keys[count($keys) - 1]];
		}
		if (!is_string($error)) {
			$error = Json::encode($error);
		}
		return $error;
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function hasErrors(): bool {
		return !empty($this->errors);
	}

	public function setErrors(array $errors) {
		$this->errors = $errors;
		return $this;
	}

	public function getResponseCode() {
		return $this->code;
	}

	public function getResponseCodeClass(): int {
		return (int)floor($this->code / 100);
	}

	public function setResponseCode(int $code) {
		$this->code = $code;
		return $this;
	}
}
