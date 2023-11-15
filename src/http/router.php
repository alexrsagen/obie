<?php namespace Obie\Http;
use Obie\App;
use Obie\Vars\VarCollection;

class Router {
	// Constants

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

	// Content types
	const CONTENT_TYPE_HTML = 'text/html';
	const CONTENT_TYPE_TEXT = 'text/plain';
	const CONTENT_TYPE_JSON = 'application/json';
	const CONTENT_TYPE_XML  = 'application/xml';

	// Character sets
	const CHARSET_UTF8 = 'utf-8';

	// Request methods
	const METHOD_GET     = 'GET';
	const METHOD_HEAD    = 'HEAD';
	const METHOD_POST    = 'POST';
	const METHOD_PUT     = 'PUT';
	const METHOD_DELETE  = 'DELETE';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_PATCH   = 'PATCH';

	// Special "methods" unique to this router which serve as catch-alls
	const METHOD_USE     = 'USE';
	const METHOD_ANY     = 'ANY';

	// Mapping of static methods of Router to RouterInstance methods

	protected static ?RouterInstance $global_instance = null;

	protected static function init(): void {
		if (self::$global_instance === null) {
			self::$global_instance = new RouterInstance(new VarCollection());
		}
	}

	/** @see RouterInstance::defer() */
	public static function defer(callable ...$handlers): void {
		static::getInstance()->defer(...$handlers);
	}
	/** @see RouterInstance::runDeferred() */
	public static function runDeferred(): bool {
		return static::getInstance()->runDeferred();
	}
	/** @see RouterInstance::execute() */
	public static function execute(?string $method = null, ?string $path = null): int {
		return static::getInstance()->execute($method, $path);
	}
	/** @see RouterInstance::route() */
	public static function route(string $method_str, string $route_str, callable ...$handlers): Route {
		return static::getInstance()->route($method_str, $route_str, ...$handlers);
	}
	/** @see RouterInstance::get() */
	public static function get(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->get($route_str, ...$handlers);
	}
	/** @see RouterInstance::head() */
	public static function head(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->head($route_str, ...$handlers);
	}
	/** @see RouterInstance::post() */
	public static function post(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->post($route_str, ...$handlers);
	}
	/** @see RouterInstance::put() */
	public static function put(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->put($route_str, ...$handlers);
	}
	/** @see RouterInstance::delete() */
	public static function delete(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->delete($route_str, ...$handlers);
	}
	/** @see RouterInstance::options() */
	public static function options(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->options($route_str, ...$handlers);
	}
	/** @see RouterInstance::patch() */
	public static function patch(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->patch($route_str, ...$handlers);
	}
	/** @see RouterInstance::use() */
	public static function use(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->use($route_str, ...$handlers);
	}
	/** @see RouterInstance::any() */
	public static function any(string $route_str, callable ...$handlers): Route {
		return static::getInstance()->any($route_str, ...$handlers);
	}

	/**
	 * Returns the global RouterInstance
	 *
	 * @return RouterInstance
	 */
	public static function getInstance(): RouterInstance {
		self::init();
		return self::$global_instance->getInstance();
	}

	// Global utility methods

	public static bool $strict               = true;
	public static string $html_suffix        = '';
	public static array $html_minify_options = [];
	protected static bool $response_sent     = false;

	/**
	 * Checks for a trailing slash in the current path. If a trailing slash
	 * is present, sends a redirect response to the current path
	 * and query string, without any trailing slash.
	 *
	 * @return bool Whether a redirect response was sent
	 */
	public static function stripTrailingSlash(): bool {
		$path = static::getPath();
		$qs = static::getQueryString();
		if (substr($path, -1) === '/' && strlen($path) > 1) {
			static::redirect(rtrim($path, '/') . $qs, static::HTTP_TEMPORARY_REDIRECT);
			return true;
		}
		return false;
	}

	/**
	 * @deprecated
	 * @see Request::getBody()
	 *
	 * @param null|string $key
	 * @param string $type
	 * @param mixed $fallback
	 * @return mixed
	 */
	public static function getPost(?string $key = null, string $type = 'raw', mixed $fallback = null): mixed {
		return Request::current()?->getBody($key, $type, $fallback);
	}

	/**
	 * @deprecated
	 * @see Request::getBody()
	 *
	 * @param string $key
	 * @return bool
	 */
	public static function getPostBool(string $key): bool {
		return Request::current()?->getBody($key, 'bool', false);
	}

	/**
	 * @deprecated
	 * @see Request::getQuery()
	 *
	 * @param null|string $key
	 * @param string $type
	 * @param mixed $fallback
	 * @return mixed
	 */
	public static function getQuery(?string $key = null, string $type = 'raw', mixed $fallback = null): mixed {
		return Request::current()?->getQuery($key, $type, $fallback);
	}

	/**
	 * @deprecated
	 * @see Request::getQuery()
	 *
	 * @param string $key
	 * @return bool
	 */
	public static function getQueryBool(string $key): bool {
		return Request::current()?->getQuery($key, 'bool', false);
	}

	/**
	 * @deprecated
	 * @see Request::getHeader()
	 *
	 * @param null|string $key
	 * @return string
	 */
	public static function getRequestHeader(?string $key = null): ?string {
		return Request::current()?->getHeader($key);
	}

	/**
	 * @deprecated
	 * @see Request::getMethod()
	 *
	 * @return string The empty string is returned if there is no active request (for example when running as a script).
	 */
	public static function getMethod(): string {
		return Request::current()?->getMethod();
	}

	/**
	 * @deprecated
	 * @see Request::getRemoteAddress()
	 *
	 * @param bool $pack
	 * @param bool $allow_x_forwarded_for
	 * @return null|string
	 */
	public static function getRemoteAddress(bool $pack = false, bool $allow_x_forwarded_for = true): ?string {
		return Request::current()?->getRemoteAddress($allow_x_forwarded_for ? 'x-forwarded-for' : null, $pack);
	}

	/**
	 * @deprecated
	 * @see Request::getRemotePort()
	 *
	 * @return int 0 is returned if there is no active request (for example when running as a script).
	 */
	public static function getRemotePort(): int {
		return Request::current()?->getRemotePort() ?? 0;
	}

	/**
	 * @deprecated
	 * @see Request::getPath()
	 *
	 * @return string The empty string is returned if there is no active request (for example when running as a script).
	 */
	public static function getPath(): string {
		return Request::current()?->getPath() ?? '';
	}

	/**
	 * @deprecated
	 * @see Request::getQueryString()
	 *
	 * @return string The empty string is returned if there is no active request (for example when running as a script) or if there is no query string.
	 */
	public static function getQueryString(): string {
		$qs = Request::current()?->getQueryString();
		if (empty($qs)) return '';
		return '?' . $qs;
	}

	/**
	 * @deprecated
	 * @see Request::getScheme()
	 *
	 * @return string
	 */
	public static function getScheme(): string {
		return Request::current()?->getScheme();
	}

	/**
	 * @deprecated
	 * @see Request::getHost()
	 *
	 * @param bool $validate_strict
	 * @return null|string
	 */
	public static function getHost(bool $validate_strict = false): ?string {
		$host = Request::current()?->getHost();
		if ($validate_strict && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
			return null;
		}
		return $host;
	}

	/**
	 * @deprecated Dangerous method, may not always return correct data for any given header
	 * @see \Obie\AcceptHeader
	 * @see \Obie\AcceptLanguageHeader
	 * @see \Obie\ContentDispositionHeader
	 *
	 * @param string $key
	 * @param string $delimiter
	 * @param string $endchar
	 * @return array
	 */
	public static function parseRequestHeader(string $key, string $delimiter = ',', string $endchar = ';'): array {
		$val = static::getRequestHeader($key);
		if ($val === null) return [];
		$end = strpos($val, $endchar);
		if ($end === false || empty($endchar)) $end = strlen($val);
		return array_map('trim', explode($delimiter, substr($val, 0, $end)));
	}

	/**
	 * Check if a response is already sent. If a response is not already sent,
	 * send a response.
	 *
	 * @param mixed $response The response body to send
	 * @param string $content_type The content type of the response body
	 * @param string $charset The character set of the response body
	 * @param bool $minify Whether to minify the response body (if the content type is supported by \Obie\Minify)
	 * @return bool Whether a response was sent (Router will only send one response)
	 */
	public static function sendResponse($response = null, string $content_type = self::CONTENT_TYPE_HTML, string $charset = 'utf-8', bool $minify = true): bool {
		if (self::$response_sent) return false;
		self::$response_sent = true;

		// Build response
		$content_type = Mime::decode($content_type);
		$content_type->setParameter('charset', $charset);
		$res = Response::current()
			->setContentType($content_type)
			->setMinify($minify)
			->setHTMLMinifyOptions(self::$html_minify_options)
			->setHTMLSuffix(self::$html_suffix)
			->setBody($response);

		// Run deferred functions from all router instances
		static::runDeferred();

		// Send response
		$res->send();
		return true;
	}

	/**
	 * Shorthand for Router::sendResponse()
	 *
	 * @param mixed $input
	 * @return bool Whether a response was sent (Router will only send one response)
	 */
	public static function sendJSON($input): bool {
		return static::sendResponse($input, self::CONTENT_TYPE_JSON);
	}

	/**
	 * @return bool
	 */
	public static function isResponseSent() {
		return self::$response_sent;
	}

	/**
	 * @param string $location
	 * @param int $code
	 * @return void
	 */
	public static function redirectOut(string $location, int $code = self::HTTP_FOUND): void {
		if (self::$response_sent) return;
		static::setResponseHeader('location', str_replace(array(';', "\r", "\n"), '', $location));
		static::setResponseCode($code);
		static::sendResponse();
	}

	/**
	 * @param string $location
	 * @param int $code
	 * @return void
	 */
	public static function redirect(string $location, int $code = self::HTTP_FOUND): void {
		static::redirectOut(rtrim(App::$app::getConfig()->get('url'), '/') . '/' . ltrim($location, '/'), $code);
	}

	/**
	 * @deprecated
	 * @see Response::setCode()
	 *
	 * @param int $response_code
	 * @return void
	 */
	public static function setResponseCode(int $response_code) {
		Response::current()?->setCode($response_code);
	}

	/**
	 * @deprecated
	 * @see Response::getCode()
	 *
	 * @return int 0 is returned if there is no active request (for example when running as a script).
	 */
	public static function getResponseCode(): int {
		return Response::current()?->getCode() ?? 0;
	}

	/**
	 * @deprecated
	 * @see Response::getCodeText()
	 *
	 * @return string The empty string is returned if there is no active request (for example when running as a script).
	 */
	public static function getResponseCodeText(): string {
		return Response::current()?->getCodeText() ?? '';
	}

	/**
	 * @deprecated
	 * @see Response::getHeader()
	 *
	 * @param null|string $name
	 * @return string
	 */
	public static function getResponseHeader(?string $name = null): string {
		return Response::current()?->getHeader($name);
	}

	/**
	 * @deprecated
	 * @see Response::setHeader()
	 *
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public static function setResponseHeader(string $name, string $value) {
		Response::current()?->setHeader($name, $value);
	}

	/**
	 * @deprecated
	 * @see Response::setHeaders()
	 *
	 * @param array $headers
	 * @return void
	 */
	public static function setResponseHeaders(array $headers) {
		Response::current()?->setHeaders($headers);
	}

	/**
	 * @deprecated
	 * @see Response::unsetHeader()
	 *
	 * @param string $name
	 * @return void
	 */
	public static function unsetResponseHeader(string $name) {
		Response::current()?->unsetHeader($name);
	}

	/**
	 * @deprecated
	 * @see Response::unsetHeaders()
	 *
	 * @param array $names
	 * @return void
	 */
	public static function unsetResponseHeaders(array $names) {
		Response::current()?->unsetHeaders($names);
	}

	/**
	 * Returns the VarCollection of the global RouterInstance
	 *
	 * @return VarCollection
	 */
	public static function vars(): VarCollection {
		return self::getInstance()->vars;
	}
}
