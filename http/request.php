<?php namespace Obie\Http;
use Obie\Encoding\Querystring;
use Obie\Encoding\Url;
use Obie\Log;

class Request {
	use HeaderTrait;
	use BodyTrait;

	public static $request_log_format  = "----- BEGIN HTTP REQUEST -----\n%s\n%s\n----- END HTTP REQUEST -----";
	public static $response_log_format = "----- BEGIN HTTP RESPONSE -----\n%s\n----- END HTTP RESPONSE -----";

	// Request methods
	const METHOD_GET     = 'GET';
	const METHOD_HEAD    = 'HEAD';
	const METHOD_POST    = 'POST';
	const METHOD_PUT     = 'PUT';
	const METHOD_DELETE  = 'DELETE';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_PATCH   = 'PATCH';

	// Initializers

	protected array $query = [];

	public function __construct(
		public string $method = self::METHOD_GET,
		string $url = '',
		public string $scheme = '',
		public ?string $username = '',
		public ?string $password = '',
		public string $host = '',
		public string $remote_ip = '',
		public int $remote_port = 0,
		public string $path = '',
		array $query = [],
		array $headers = [],
		?Mime $content_type = null,
		?AcceptHeader $accept = null,
		string $body = '',
		mixed $body_data = null,
	) {
		if (!empty($method)) $this->setMethod($method);
		if (!empty($url)) {
			$this->setURL($url);
			if (!empty($query)) $this->setQuery(array_merge_recursive($this->getQuery(), $query));
		} elseif (!empty($query)) {
			$this->setQuery($query);
		}
		if (!empty($scheme)) $this->setScheme($scheme);
		if (!empty($username)) $this->setUsername($username);
		if (!empty($password)) $this->setPassword($password);
		if (!empty($host)) $this->setHost($host);
		if (!empty($remote_port)) $this->setRemotePort($remote_port);
		if (!empty($path)) $this->setPath($path);
		if (!empty($headers)) $this->setHeaders($headers);
		if ($content_type !== null) $this->setContentType($content_type);
		if ($accept !== null) $this->setAccept($accept);
		if ($this->methodHasBody()) {
			$this->initBody($body, $body_data);
		}
	}

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
				scheme: !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || !empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443 ? 'https' : 'http',
				username: array_key_exists('PHP_AUTH_USER', $_SERVER) ? $_SERVER['PHP_AUTH_USER'] : null,
				password: array_key_exists('PHP_AUTH_PW', $_SERVER) ? $_SERVER['PHP_AUTH_PW'] : null,
				host: $host,
				method: $_SERVER['REQUEST_METHOD'],
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

	public static function get(string $url = ''): static { return new static(method: self::METHOD_GET, url: $url); }
	public static function head(string $url = ''): static { return new static(method: self::METHOD_HEAD, url: $url); }
	public static function post(string $url = ''): static { return new static(method: self::METHOD_POST, url: $url); }
	public static function put(string $url = ''): static { return new static(method: self::METHOD_PUT, url: $url); }
	public static function delete(string $url = ''): static { return new static(method: self::METHOD_DELETE, url: $url); }
	public static function options(string $url = ''): static { return new static(method: self::METHOD_OPTIONS, url: $url); }
	public static function patch(string $url = ''): static { return new static(method: self::METHOD_PATCH, url: $url); }

	// Helpers

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

	public function getUsername(): ?string {
		return $this->username;
	}

	public function getPassword(): ?string {
		return $this->password;
	}

	public function getQuery(?string $key = null, string $type = 'string', mixed $fallback = null): mixed {
		return static::getValue($this->query, $key, $type, $fallback);
	}

	public function getQueryString(int $numeric_type = Querystring::NUMERIC_TYPE_INDEXED): string {
		return Querystring::encode($this->getQuery(), $numeric_type);
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

	public function getURL(int $numeric_type = Querystring::NUMERIC_TYPE_INDEXED): string {
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
		if (!empty($this->getQueryString(numeric_type: $numeric_type))) {
			$parts['query'] = $this->getQueryString(numeric_type: $numeric_type);
		}
		return Url::encode($parts);
	}

	public function getUseragent(): string {
		return $this->getHeader('user-agent', fallback: 'Obie/1.0');
	}

	// Setters

	public function methodHasBody(): bool {
		return in_array($this->getMethod(), ['POST', 'PUT', 'PATCH'], true);
	}

	public function setScheme(string $scheme): static {
		$this->scheme = strtolower($scheme);
		return $this;
	}

	public function setHost(string $host): static {
		$this->host = strtolower($host);
		return $this;
	}

	public function setPath(string $path): static {
		$this->path = $path;
		return $this;
	}

	public function setUsername(string $username): static {
		$this->username = $username;
		return $this;
	}

	public function setPassword(string $password): static {
		$this->password = $password;
		return $this;
	}

	public function setQuery(array $query): static {
		$this->query = $query;
		return $this;
	}

	public function setQueryString(string $qs): static {
		$this->query = Querystring::decode($qs);
		return $this;
	}

	public function setMethod(string $method): static {
		$this->method = strtoupper($method);
		return $this;
	}

	public function setRemoteAddress(string $remote_ip): static {
		$this->remote_ip = $remote_ip;
		return $this;
	}

	public function setRemotePort(int $remote_port): static {
		$this->remote_port = $remote_port;
		return $this;
	}

	public function setURL(string $url): static {
		$url = Url::decode($url);
		if (!is_array($url)) return $this;
		if (array_key_exists('scheme', $url)) $this->setScheme($url['scheme']);
		if (array_key_exists('user', $url)) $this->setUsername($url['user']);
		if (array_key_exists('pass', $url)) $this->setPassword($url['pass']);
		if (array_key_exists('host', $url)) $this->setHost($url['host']);
		if (array_key_exists('port', $url)) $this->setRemotePort($url['port']);
		if (array_key_exists('path', $url)) $this->setPath($url['path']);
		if (array_key_exists('query', $url)) $this->setQueryString($url['query']);
		return $this;
	}

	public function setUseragent(string $ua): static {
		return $this->setHeader('user-agent', $ua);
	}

	// Actions

	public function perform(bool $debug = false, int $numeric_type = Querystring::NUMERIC_TYPE_INDEXED): Response {
		// Initialize cURL context
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getURL(numeric_type: $numeric_type));
		curl_setopt($ch, CURLOPT_USERAGENT, $this->getUseragent());
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->getMethod());
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, $debug);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		// Add POST/PUT/PATCH body data to cURL context
		if ($this->methodHasBody()) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getRawBody());
			curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
			curl_setopt($ch, CURLOPT_POST, 1);
		}

		// Add headers to cURL context
		$headers = $this->getRawHeaders();
		$headers[] = 'Expect:';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// Execute request, get response code and throw exception on error
		$res_body = curl_exec($ch);
		if ($res_body === false) {
			$res_errno = curl_errno($ch);
			curl_close($ch);
			$res = new Response($res_body, errors: [sprintf('cURL error (%d): %s', $res_errno, curl_strerror($res_errno))]);
			return $res;
		}

		// Dump request + response if debugging
		if ($debug) {
			Log::debug(sprintf(static::$request_log_format, curl_getinfo($ch, CURLINFO_HEADER_OUT), $this->getRawBody()));
			Log::debug(sprintf(static::$response_log_format, $res_body));
		}

		// Get response code and size of response headers
		$res_code         = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$res_header_size  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);

		// Get the headers of the last request (ignoring the headers of any redirects)
		$res_headerstr          = substr($res_body, 0, $res_header_size);
		$res_body               = substr($res_body, $res_header_size);
		$res_headerstr_startpos = strrpos("\r\n\r\n", $res_headerstr);
		if ($res_headerstr_startpos !== false) {
			$res_headerstr = substr($res_headerstr, $res_headerstr_startpos);
		}

		return new Response($res_body, code: (int)$res_code, headers: $res_headerstr);
	}
}
