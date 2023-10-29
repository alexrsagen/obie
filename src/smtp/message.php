<?php namespace Obie\Smtp;
use Obie\Http\Multipart;
use Obie\Http\Multipart\Segment;
use Obie\Http\Mime;

class Message {
	protected array $headers = [];
	protected ?Mime $content_type = null;

	function __construct(
		public string $from,
		public array $to = [],
		public array $cc = [],
		public array $bcc = [],
		array $headers = [],
		public string $body = '',
		?string $body_plaintext = null,
		?string $body_html = null,
		array $attachments = [],
	) {
		if (!empty($headers)) $this->setHeaders($headers);
		if (empty($body)) $this->setBody($body_plaintext, $body_html, $attachments);
	}

	public function getRecipients(): array {
		return array_merge($this->to, $this->cc, $this->bcc);
	}

	/**
	 * Set message body from plaintext and/or HTML body and attachments
	 *
	 * @param null|string $plaintext Plaintext body
	 * @param null|string $html HTML body
	 * @param Attachment[] $attachments Attachments
	 * @return static
	 */
	public function setBody(?string $plaintext = null, ?string $html = null, array $attachments = []): static {
		if ($plaintext !== null && $html !== null || !empty($attachments)) {
			// create multipart/mixed segment container and boundary
			$mixed_segments = [];
			$mixed_boundary = Multipart::generateBoundary();
			$mixed_mime = Mime::decode(Multipart::ENC_MIXED);
			$mixed_mime->setParameter('boundary', $mixed_boundary);

			if ($plaintext !== null && $html !== null) {
				// create multipart/alternate segment container and boundary
				$alternate_segments = [];
				$alternate_boundary = Multipart::generateBoundary();
				$alternate_mime = Mime::decode(Multipart::ENC_ALTERNATIVE);
				$alternate_mime->setParameter('boundary', $alternate_boundary);

				// add text/plain body to multipart/alternate segment
				$plaintext_segment = new Segment($plaintext);
				$plaintext_segment->setContentType('text/plain');
				$alternate_segments[] = $plaintext_segment;

				// create multipart/related segment container and boundary
				$related_segments = [];
				$related_boundary = Multipart::generateBoundary();
				$related_mime = Mime::decode(Multipart::ENC_RELATED);
				$related_mime->setParameter('boundary', $related_boundary);

				// add text/html body to multipart/related segment
				$html_segment = new Segment($html);
				$html_segment->setContentType('text/html');
				$related_segments[] = $html_segment;

				// add multipart/related segment to multipart/alternate segment
				$related_segment = new Segment(Multipart::encode($related_segments, $related_boundary));
				$related_segment->setContentType($related_mime);
				$alternate_segments[] = $related_segment;

				// add multipart/alternate segment to multipart/mixed segment
				$alternate_segment = new Segment(Multipart::encode($alternate_segments, $alternate_boundary));
				$alternate_segment->setContentType($alternate_mime);
				$mixed_segments[] = $alternate_segment;
			} else {
				// add text/plain or text/html segment to multipart/mixed segment
				$body_segment = new Segment(quoted_printable_encode($plaintext ?? $html ?? ''));
				$body_segment->setContentType($plaintext !== null ? 'text/plain' : 'text/html');
				$body_segment->setHeader('content-transfer-encoding', 'quoted-printable');
				$mixed_segments[] = $body_segment;
			}

			// add attachments to multipart/mixed segment
			foreach ($attachments as $attachment) {
				if (!($attachment instanceof Attachment)) continue;
				$mixed_segments[] = $attachment->toSegment();
			}

			// add multipart/mixed segment to body
			$this->setContentType($mixed_mime);
			$this->body = Multipart::encode($mixed_segments, $mixed_boundary);
		} else {
			// add text/plain or text/html content to body
			$this->setContentType($plaintext !== null ? 'text/plain' : 'text/html');
			$this->setHeader('content-transfer-encoding', 'quoted-printable');
			$this->body = quoted_printable_encode($plaintext ?? $html ?? '');
		}

		return $this;
	}

	public static function normalizeHeaderKey(string $key): string {
		$key = trim($key);
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
		foreach (explode("\n", $input) as $line) {
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
		}
		if (empty($mime->getParameter('charset'))) {
			$mime->setParameter('charset', 'utf-8');
		}
		$this->content_type = $mime;
		$this->setHeader('content-type', $mime->encode());
		return $this;
	}
}