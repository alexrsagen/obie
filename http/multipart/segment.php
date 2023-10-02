<?php namespace Obie\Http\Multipart;
use Obie\Http\HeaderTrait;

class Segment {
	use HeaderTrait;

	function __construct(public string $body, array $headers = [], public int $line_length = 76) {
		if (!empty($headers)) $this->setHeaders($headers);
	}

	protected static function encodeBody(string $body, string $encoding, int $line_length = 76): string {
		switch ($encoding) {
		case 'quoted-printable':
			return quoted_printable_encode($body);
		case 'base64':
			return chunk_split(base64_encode($body), $line_length, "\r\n");
		case '7bit': case '8bit': case 'binary': default:
			return $body;
		}
	}

	protected static function decodeBody(string $body, string $encoding): string|false {
		switch ($encoding) {
		case 'quoted-printable':
			return quoted_printable_decode($body);
		case 'base64':
			return base64_decode($body);
		case '7bit': case '8bit': case 'binary': default:
			return $body;
		}
	}

	public function setBody(string $body): static {
		$this->body = $body;
		return $this;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function getBodySize(): int {
		return strlen($this->body);
	}

	public function build(): string {
		$body_encoding = $this->getHeader('content-transfer-encoding') ?? '8bit';
		$raw = ltrim(implode("\r\n", $this->getRawHeaders()) . "\r\n", "\r\n");
		$raw .= "\r\n";
		$raw .= static::encodeBody($this->body, $body_encoding, $this->line_length);
		return $raw;
	}

	public static function encode(string $body, array $headers = []): string {
		return (new static($body, $headers))->build();
	}

	public static function decode(string $raw): ?static {
		$raw_len = strlen($raw);
		$headers = [];

		$last_field_name = null;
		$offset = 0;
		$line = '';
		while ($offset < $raw_len) {
			$lfpos = strpos($raw, "\n", $offset);
			if ($lfpos === false) $lfpos = strlen($raw);
			$line = substr($raw, $offset, $lfpos - $offset + 1);
			if ($line === false) break;
			$line_trim = trim($line);

			// parse header
			if (strlen($line_trim) > 0) {
				// handle obs-fold
				if (preg_match('/^\s+.+$/', $line) === 1 && $last_field_name === null) {
					$headers[$last_field_name] .= ' ' . $line_trim;
				} else {
					// RFC 7230 section 3.2.4 states that no whitespace is allowed
					// between the header field-name and colon.
					if (preg_match('/^.*\s+:.*$/', $line) === 1) {
						return null;
					}

					$header_parts = explode(':', $line, 2);
					$field_name = static::normalizeHeaderKey($header_parts[0]);
					$headers[$field_name] = count($header_parts) > 1 ? trim($header_parts[1]) : '';
				}
			}

			// move offset to next line
			$offset = $lfpos + 1;
			$last_field_name = $field_name;

			// if this line was blank, set body to rest of string and stop parsing
			if ($line === "\r\n" || $line === "\n") {
				$body_encoding = array_key_exists('content-transfer-encoding', $headers) ? $headers['content-transfer-encoding'] : '8bit';
				$body = static::decodeBody(substr($raw, $offset), $body_encoding);
				if ((!is_string($body) || strlen($body) === 0) && empty($headers)) return null;
				return new static($body ?: '', $headers);
			}
		}
		return empty($headers) ? null : new static('', $headers);
	}
}