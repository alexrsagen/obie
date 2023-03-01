<?php namespace Obie\Http;
use \Obie\App;
use \Obie\Vars\VarCollection;

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

	protected static $global_instance = null;

	protected static function init() {
		if (self::$global_instance === null) {
			self::$global_instance = new RouterInstance(new VarCollection());
		}
	}

	public static function __callStatic(string $method_name, array $args) {
		self::init();
		if (is_callable([self::getInstance(), $method_name])) {
			return call_user_func_array([self::getInstance(), $method_name], $args);
		}
	}

	public static function getInstance() {
		self::init();
		return self::$global_instance->getInstance();
	}

	// Global utility methods

	public static $strict               = true;
	public static $html_suffix          = '';
	public static $html_minify_options  = [];
	protected static $response_sent     = false;

	public static function stripTrailingSlash() {
		$path = static::getPath();
		$qs = static::getQueryString();
		if (substr($path, -1) === '/' && strlen($path) > 1) {
			static::redirect(rtrim($path, '/') . $qs, static::HTTP_TEMPORARY_REDIRECT);
			return true;
		}
		return false;
	}

	public static function getPost(?string $key = null, string $type = 'raw', mixed $fallback = null) {
		return Request::current()?->getBody($key, $type, $fallback);
	}

	public static function getPostBool(string $key) {
		return Request::current()?->getBody($key, 'bool', false);
	}

	public static function getQuery(?string $key = null, string $type = 'raw', mixed $fallback = null) {
		return Request::current()?->getQuery($key, $type, $fallback);
	}

	public static function getQueryBool(string $key) {
		return Request::current()?->getQuery($key, 'bool', false);
	}

	public static function getRequestHeader(?string $key = null) {
		return Request::current()?->getHeader($key);
	}

	public static function getMethod() {
		return Request::current()?->getMethod();
	}

	public static function getRemoteAddress(bool $pack = false, bool $allow_x_forwarded_for = true) {
		return Request::current()?->getRemoteAddress($allow_x_forwarded_for ? 'x-forwarded-for' : null, $pack);
	}

	public static function getRemotePort(): int {
		return Request::current()?->getRemotePort();
	}

	public static function getPath() {
		return Request::current()?->getPath();
	}

	public static function getQueryString() {
		$qs = Request::current()?->getQueryString();
		if (empty($qs)) return '';
		return '?' . $qs;
	}

	public static function getScheme() {
		return Request::current()?->getScheme();
	}

	public static function getHost(bool $validate_strict = false) {
		$host = Request::current()?->getHost();
		if ($validate_strict && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
			return null;
		}
		return $host;
	}

	public static function parseRequestHeader(string $key, string $delimiter = ',', string $endchar = ';') {
		$val = static::getRequestHeader($key);
		if ($val === null) return [];
		$end = strpos($val, $endchar);
		if ($end === false || empty($endchar)) $end = strlen($val);
		return array_map('trim', explode($delimiter, substr($val, 0, $end)));
	}

	public static function sendResponse($response = null, string $content_type = self::CONTENT_TYPE_HTML, string $charset = 'utf-8', bool $minify = true) {
		if (self::$response_sent) return;

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
		self::$response_sent = true;
	}

	public static function sendJSON($input) {
		static::sendResponse($input, self::CONTENT_TYPE_JSON);
	}

	public static function isResponseSent() {
		return self::$response_sent;
	}

	public static function redirectOut(string $location, int $code = self::HTTP_FOUND) {
		if (self::$response_sent) return;
		static::setResponseHeader('location', str_replace(array(';', "\r", "\n"), '', $location));
		static::setResponseCode($code);
		static::sendResponse();
	}

	public static function redirect(string $location, int $code = self::HTTP_FOUND) {
		static::redirectOut(rtrim(App::getConfig()->get('url'), '/') . '/' . ltrim($location, '/'), $code);
	}

	public static function setResponseCode(int $response_code) {
		Response::current()?->setCode($response_code);
	}

	public static function getResponseCode(): int {
		return Response::current()?->getCode();
	}

	public static function getResponseCodeText(): string {
		return Response::current()?->getCodeText() ?? '';
	}

	public static function getResponseHeader(?string $name = null) {
		return Response::current()?->getHeader($name);
	}

	public static function setResponseHeader(string $name, string $value) {
		Response::current()?->setHeader($name, $value);
	}

	public static function setResponseHeaders(array $headers) {
		Response::current()?->setHeaders($headers);
	}

	public static function unsetResponseHeader(string $name) {
		Response::current()?->unsetHeader($name);
	}

	public static function unsetResponseHeaders(array $names) {
		Response::current()?->unsetHeaders($names);
	}

	public static function vars(): VarCollection {
		return self::getInstance()->vars;
	}
}
