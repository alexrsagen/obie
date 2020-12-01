<?php namespace Obie\Http;
use \Obie\Encoding\Querystring;
use \Obie\Encoding\Multipart;
use \Obie\Encoding\Multipart\FormData;
use \Obie\Encoding\Json;
use \Obie\Encoding\Url;
use \Obie\Http\Mime;

// TODO: test
// TODO: setters
class Request {
	// Request methods
	const METHOD_GET     = 'GET';
	const METHOD_HEAD    = 'HEAD';
	const METHOD_POST    = 'POST';
	const METHOD_PUT     = 'PUT';
	const METHOD_DELETE  = 'DELETE';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_PATCH   = 'PATCH';

	public function __construct(
		public string $scheme = '',
		public ?string $username = '',
		public ?string $password = '',
		public string $host = '',
		public string $method = '',
		public string $path = '',
		public array $query = [],
		protected array $headers = [],
		protected ?Mime $content_type = null,
		protected string $body = '',
		protected mixed $body_data = null,
		public string $remote_ip = '',
		public int $remote_port = 0,
	) {
		$this->scheme = strtolower($scheme);
		$this->host = strtolower($host);
		$this->method = strtoupper($method);
		$this->headers = array_combine(array_map(function($key) {
			return static::normalizeHeaderKey($key);
		}, array_keys($headers)), array_values($headers));
		if (empty($body_data) && !empty($body) && ($this->getMethod() === 'POST' || $this->getMethod() === 'PUT')) {
			$this->body_data = $this->decodeBody($body);
		}
	}

	// Helpers

	protected static function normalizeHeaderKey(string $key): string {
		return strtolower(str_replace('_', '-', str_starts_with($key, 'HTTP_') ? substr($key, 5) : $key));
	}

	protected static function normalizeAddress(string $address, bool $binary = false): ?string {
		$address_bin = inet_pton($address);
		if ($address_bin === false) return null;

		// fix IPv6-mapped IPv4 address
		$v4mapped_prefix_bin = hex2bin('00000000000000000000ffff');
		if (str_starts_with($address_bin, $v4mapped_prefix_bin)) {
			$address_bin = substr($address_bin, strlen($v4mapped_prefix_bin));
		}

		return $binary ? $address_bin : inet_ntop($address_bin);
	}

	protected function decodeBody(string $input): mixed {
		if (strlen($input) === 0) return null;
		return match($this->getContentType()?->getType()) {
			'multipart/form-data' => FormData::decode($input),
			'application/x-www-form-urlencoded' => Querystring::decode($input),
			'application/json' => Json::decode($input),
			default => null,
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
		return '';
	}

	// Initializers

	public static function current(): ?static {
		if (php_sapi_name() === 'cli') return null;
		static $current = null;
		if ($current === null) {
			// get path and query string
			$path = $_SERVER['REQUEST_URI'];
			$qs_pos = strpos($path, '?');
			$qs = '';
			if ($qs_pos !== false) {
				$qs = substr($path, $qs_pos + 1);
				$path = substr($path, 0, $qs_pos);
			}
			// get host
			$host = '';
			if (!empty($_SERVER['HTTP_HOST'])) {
				$host = $_SERVER['HTTP_HOST'];
			} elseif (!empty($_SERVER['SERVER_NAME'])) {
				$host = $_SERVER['SERVER_NAME'];
			} elseif (!empty($_SERVER['SERVER_ADDR'])) {
				$host = $_SERVER['SERVER_ADDR'];
			}
			// get current request
			$current = new static(
				scheme: !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' || !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http',
				username: array_key_exists('PHP_AUTH_USER', $_SERVER) ? $_SERVER['PHP_AUTH_USER'] : null,
				password: array_key_exists('PHP_AUTH_PW', $_SERVER) ? $_SERVER['PHP_AUTH_PW'] : null,
				host: $host,
				method: strtoupper($_SERVER['REQUEST_METHOD']),
				path: $path,
				query: Querystring::decode($qs),
				headers: array_filter($_SERVER, function($k) {
					return str_starts_with($k, 'HTTP_');
				}, ARRAY_FILTER_USE_KEY),
				body: file_get_contents('php://input'),
				remote_ip: $_SERVER['REMOTE_ADDR'],
				remote_port: (int)$_SERVER['REMOTE_PORT'],
			);
		}
		return $current;
	}

	// Getters

	public function getScheme(): string {
		return $this->scheme;
	}

	public function getHost(): string {
		return $this->host;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function getRawBody(): string {
		return $this->body;
	}

	public function getUsername(): ?string {
		return $this->username;
	}

	public function getPassword(): ?string {
		return $this->password;
	}

	public function getBody(?string $key = null, string $type = 'string', ?string $fallback = null): mixed {
		if ($key === null) return $this->body_data;
		$value = is_array($this->body_data) && array_key_exists($key, $this->body_data) ? $this->body_data[$key] : $fallback;
		return match($type) {
			'int' => (int)$value,
			'float' => (float)$value,
			'bool' => $value === '1' || $value === 'true' || $value === 'yes',
			default => $value,
		};
	}

	public function getQuery(?string $key = null, string $type = 'string', ?string $fallback = null): mixed {
		if ($key === null) return $this->query;
		$value = array_key_exists($key, $this->query) ? $this->query[$key] : $fallback;
		return match($type) {
			'int' => (int)$value,
			'float' => (float)$value,
			'bool' => $value === '1' || $value === 'true' || $value === 'yes',
			default => $value,
		};
	}

	public function getQueryString(): string {
		return Querystring::encode($this->getQuery());
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

	public function getContentType(): ?Mime {
		if ($this->content_type === null) {
			$hdr = $this->getHeader('content-type');
			if (!empty($hdr)) {
				$this->content_type = Mime::decode($hdr);
			}
		}
		return $this->content_type;
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function getRemoteAddress(?string $forward_header = null, bool $binary = false): ?string {
		$address = $this->remote_ip;
		if ($forward_header !== null) {
			$address = $this->getHeader($forward_header) ?? $this->remote_ip;
		}
		return static::normalizeAddress($address, $binary);
	}

	public function getRemotePort(): int {
		return $this->remote_port;
	}

	public function getURL(): string {
		$parts = [
			'scheme' => $this->getScheme(),
			'host' => $this->getHost(),
			'path' => $this->getPath(),
		];
		if (!empty($this->getUsername())) {
			$parts['user'] = $this->getUsername();
		}
		if (!empty($this->getPassword())) {
			$parts['pass'] = $this->getPassword();
		}
		if (!empty($this->getQueryString())) {
			$parts['query'] = $this->getQueryString();
		}
		return Url::encode($parts);
	}

	// Setters

	public function setContentType(string|Mime $mime): static {
		if (is_string($mime)) {
			$mime = Mime::decode($mime);
			$mime->setParameter('charset', 'utf-8');
		}
		$this->content_type = $mime;
		$this->setHeader('content-type', $mime->encode());
		return $this;
	}

	// Actions

	public function perform(): Response {
		// TODO: interact with Client
	}
}