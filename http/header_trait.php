<?php namespace Obie\Http;

trait HeaderTrait {
	protected array $headers = [];
	protected ?Mime $content_type = null;
	protected ?AcceptHeader $accept = null;

	public static function normalizeHeaderKey(string $key): string {
		return strtolower(str_replace('_', '-', str_starts_with($key, 'HTTP_') ? substr($key, 5) : $key));
	}

	public static function normalizeHeaders(array $headers): array {
		return array_combine(array_map([static::class, 'normalizeHeaderKey'], array_keys($headers)), array_values($headers));
	}

	public function getHeader(?string $key = null, string $type = 'string', ?string $fallback = null): mixed {
		if ($key === null) return $this->headers;
		$key = static::normalizeHeaderKey($key);
		$value = array_key_exists($key, $this->headers) ? $this->headers[$key] : $fallback;
		return match($type) {
			'int' => (int)$value,
			'float' => (float)$value,
			'bool' => $value === '1' || $value === 'true' || $value === 'yes',
			default => $value,
		};
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getRawHeaders(): array {
		$headers = [];
		foreach ($this->getHeaders() as $key => $value) {
			$headers[] = sprintf('%s: %s', $key, $value);
		}
		return $headers;
	}

	public function setHeader(string $key, string $value): static {
		$key = static::normalizeHeaderKey($key);
		$this->headers[$key] = $value;
		return $this;
	}

	public function setHeaders(array $headers): static {
		$this->headers = static::normalizeHeaders($headers);
		return $this;
	}

	public function setRawHeaders(string $input): static {
		$headers = [];
		foreach (explode("\n", $input) as $i => $line) {
			$kv = explode(':', trim($line, "\n\r\t "), 2);
			$key = static::normalizeHeaderKey(rtrim($kv[0], "\n\r\t "));
			if (empty($key)) continue;
			$value = count($kv) > 1 ? ltrim($kv[1], "\n\r\t ") : '';
			$headers[$key] = $value;
		}
		$this->headers = $headers;
		return $this;
	}

	public function unsetHeader(string $key): static {
		$key = static::normalizeHeaderKey($key);
		unset($this->headers[$key]);
		return $this;
	}

	public function unsetHeaders(array $keys): static {
		foreach ($keys as $key) {
			if (!is_string($key)) continue;
			$this->unsetHeader($key);
		}
		return $this;
	}

	public function getContentType(): ?Mime {
		if ($this->content_type === null) {
			$hdr = $this->getHeader('content-type');
			if (!empty($hdr)) {
				$this->content_type = Mime::decode($hdr);
			}
		}
		return $this->content_type;
	}

	public function setContentType(string|Mime $mime): static {
		if (is_string($mime)) {
			$mime = Mime::decode($mime);
			if (empty($mime->getParameter('charset'))) {
				$mime->setParameter('charset', 'utf-8');
			}
		}
		$this->content_type = $mime;
		$this->setHeader('content-type', $mime->encode());
		return $this;
	}

	public function getAccept(): ?AcceptHeader {
		if ($this->accept === null) {
			$hdr = $this->getHeader('accept');
			if (!empty($hdr)) {
				$this->accept = AcceptHeader::decode($hdr);
			}
		}
		return $this->accept;
	}

	public function setAccept(string|AcceptHeader $accept): static {
		if (is_string($accept)) {
			$accept = AcceptHeader::decode($accept);
		}
		$this->accept = $accept;
		$this->setHeader('accept', $accept->encode());
		return $this;
	}
}