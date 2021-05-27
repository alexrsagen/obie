<?php namespace Obie\Http;
use \Obie\Encoding\Querystring;
use \Obie\Encoding\Multipart;
use \Obie\Encoding\Multipart\FormData;
use \Obie\Encoding\Json;

trait BodyTrait {
	protected string $body = '';
	protected mixed $body_data = null;

	// From \Obie\Http\HeaderTrait

	abstract public function getContentType(): ?Mime;
	abstract public function setHeader(string $key, string $value): static;

	// Initializers

	protected function initBody(string $body, mixed $body_data) {
		if (empty($body_data) && !empty($body)) {
			$this->setRawBody($body);
		} elseif (empty($body) && !empty($body_data)) {
			$this->setBody($body_data);
		}
	}

	// Helpers

	protected function decodeBody(string $input): mixed {
		if (strlen($input) === 0) return null;
		return match($this->getContentType()?->getType()) {
			'multipart/form-data' => FormData::decode($input),
			'application/x-www-form-urlencoded' => Querystring::decode($input),
			'application/json' => Json::decode($input),
			default => $input,
		};
	}

	protected function encodeBody(mixed $input): string {
		if ($input === null) return '';
		switch ($this->getContentType()?->getType()) {
		case 'multipart/form-data':
			if (!is_array($input)) return '';
			$boundary = $this->getContentType()?->getParameter('boundary');
			if ($boundary === null) {
				$boundary = Multipart::generateBoundary();
				$this->getContentType()?->setParameter('boundary', $boundary);
			}
			return FormData::encode($input, [], $boundary);
		case 'application/x-www-form-urlencoded':
			return Querystring::encode($input);
		case 'application/json':
			return Json::encode($input);
		}
		return (string)$input;
	}

	protected static function getValue(mixed $data, ?string $key = null, string $type = 'string', mixed $fallback = null): mixed {
		if ($key === null) return $data;
		$path = explode('.', $key);
		$value = $data;
		foreach ($path as $k) {
			if (!is_array($value) || !array_key_exists($k, $value)) return $fallback;
			$value = $value[$k];
		}
		// If the type represents a single value and the value is an array,
		// return the first value from the array.
		if (in_array($type, ['int', 'float', 'bool', 'string'], true) && is_array($value)) {
			$keys = array_keys($value);
			$value = count($keys) === 0 ? null : $value[$keys[0]];
		}
		if ($value === null || $value === $fallback) return $fallback;
		return match($type) {
			'int' => (int)$value,
			'float' => (float)$value,
			'bool' => in_array($value, [1, '1', true, 'true', 'yes'], true),
			'string' => (string)$value,
			'array' => is_array($value) ? $value : [$value],
			default => $value,
		};
	}

	// Getters

	public function getBody(?string $key = null, string $type = 'string', mixed $fallback = null): mixed {
		return static::getValue($this->body_data, $key, $type, $fallback);
	}

	public function getRawBody(): string {
		return $this->body;
	}

	// Setters

	public function setRawBody(string $body): static {
		$this->body = $body;
		$this->body_data = $this->decodeBody($body);
		$this->setHeader('content-length', (string)strlen($this->body));
		return $this;
	}

	public function setBody(mixed $body): static {
		$this->body = $this->encodeBody($body);
		$this->body_data = $body;
		$this->setHeader('content-length', (string)strlen($this->body));
		return $this;
	}
}