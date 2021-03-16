<?php namespace Obie\Http;
use \Obie\Encoding\Querystring;
use \Obie\Encoding\Multipart;
use \Obie\Encoding\Multipart\FormData;
use \Obie\Encoding\Json;

trait BodyTrait {
	protected string $body = '';
	protected mixed $body_data = null;

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
			if (!is_array($value) || !array_key_exists($k, $value)) {
				$value = $fallback;
				break;
			}
			$value = $value[$k];
		}
		$value_single = is_array($value) ? (array_key_exists(0, $value) ? $value[0] : $fallback) : $value;
		return match($type) {
			'int' => (int)$value_single,
			'float' => (float)$value_single,
			'bool' => $value_single === true || $value_single === 1 || $value_single === '1' || $value_single === 'true' || $value_single === 'yes',
			'string' => (string)$value_single,
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