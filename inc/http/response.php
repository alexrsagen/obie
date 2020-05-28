<?php namespace ZeroX\Http;
use ZeroX\Encoding\Json;

class Response {
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
