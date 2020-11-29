<?php namespace Obie\Encoding\Multipart;

class Segment {
	protected $headers   = [];
	protected $body      = '';
	protected $body_size = 0;

	function __construct(string $body, array $headers = []) {
		$this->setBody($body);
		$this->setHeaders($headers);
	}

	protected static function encodeBody(string $body, string $encoding) {
		switch ($encoding) {
		case 'quoted-printable':
			return quoted_printable_encode($body);
		case 'base64':
			return base64_encode($body);
		case '7bit': case '8bit': case 'binary': default:
			return $body;
		}
	}

	protected static function decodeBody(string $body, string $encoding) {
		switch ($encoding) {
		case 'quoted-printable':
			return quoted_printable_decode($body);
		case 'base64':
			return base64_decode($body);
		case '7bit': case '8bit': case 'binary': default:
			return $body;
		}
	}

	protected static function normalizeHeader(string $key): string {
		return strtolower(str_replace('_', '-', trim($key)));
	}

	public function setHeader(string $key, string $value): self {
		$key = static::normalizeHeader($key);
		$this->headers[$key] = $value;
		return $this;
	}

	public function setHeaders(array $headers): self {
		foreach ($headers as $k => $v) {
			$this->setHeader($k, (string)$v);
		}
		return $this;
	}

	public function setBody(string $body): self {
		$this->body      = $body;
		$this->body_size = strlen($body);
		return $this;
	}

	public function getHeader(string $key): ?string {
		$key = static::normalizeHeader($key);
		if (!array_key_exists($key, $this->headers)) return null;
		return $this->headers[$key];
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function getBodySize(): int {
		return $this->body_size;
	}

	public function build(): string {
		$body_encoding = $this->getHeader('content-transfer-encoding') ?? '8bit';
		$raw = '';
		foreach ($this->headers as $k => $v) {
			$raw .= $k . ': ' . $v . "\r\n";
		}
		$raw .= "\r\n";
		$raw .= static::encodeBody($this->body, $body_encoding);
		return $raw;
	}

	public static function encode(string $body, array $headers = []): string {
		return (new static($body, $headers))->build();
	}

	public static function decode(string $raw): ?self {
		$raw_len = strlen($raw);
		$body = '';
		$headers = [];

		$last_field_name = null;
		$offset = 0;
		while (
			$offset < $raw_len &&
			($lfpos = strpos($raw, "\n", $offset)) !== -1 &&
			($line = substr($raw, $offset, $lfpos - $offset + 1)) !== false
		) {
			if ($lfpos === false) {
				$lfpos = $raw_len - $offset;
			}
			$line_trim = trim($line);

			// parse header
			if (strlen($line_trim) > 0) {
				// handle obs-fold
				if (preg_match('/^\s+.+$/', $line) === 1 && $last_field_name === null) {
					$headers[$last_field_name] .= ' ' . $line_trim;
				} else {
					// RFC7230 section 3.2.4 states that no whitespace is allowed
					// between the header field-name and colon.
					if (preg_match('/^.*\s+:.*$/', $line) === 1) {
						return null;
					}

					$header_parts = explode(':', $line, 2);
					$field_name = static::normalizeHeader($header_parts[0]);
					$headers[$field_name] = count($header_parts) > 1 ? trim($header_parts[1]) : '';
				}
			}

			// move offset to next line
			$offset = $lfpos + 1;
			$last_field_name = $field_name;

			// if this line was blank, set body to next line and stop parsing
			if ($line === "\r\n" || $line === "\n") {
				$body_encoding = array_key_exists('content-transfer-encoding', $headers) ? $headers['content-transfer-encoding'] : '8bit';
				return new static(static::decodeBody(substr($raw, $offset), $body_encoding), $headers);
			}
		}
		return null;
	}
}