<?php namespace ZeroX\Http;
use \ZeroX\Log;
use \ZeroX\Encoding\Json;
use \ZeroX\Encoding\Querystring;
use \ZeroX\Encoding\Multipart;
use \ZeroX\Encoding\Multipart\FormData;

class Client {
	// Request methods
	const METHOD_GET     = 'GET';
	const METHOD_HEAD    = 'HEAD';
	const METHOD_POST    = 'POST';
	const METHOD_PUT     = 'PUT';
	const METHOD_DELETE  = 'DELETE';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_PATCH   = 'PATCH';

	// Log formats
	public static $request_log_format  = "----- BEGIN HTTP REQUEST -----\n%s\n%s\n----- END HTTP REQUEST -----";
	public static $response_log_format = "----- BEGIN HTTP RESPONSE -----\n%s\n----- END HTTP RESPONSE -----";

	protected $debug;

	function __construct(bool $debug = false) {
		$this->debug = $debug;
	}

	public function setDebug(bool $debug) {
		$this->debug = $debug;
		return $this;
	}

	/**
	 * Performs a HTTP request
	 *
	 * @param string $method        Request method, can be any valid HTTP method
	 * @param string $req_url       Request URL
	 * @param array  $req_query     Request query key-value array, gets merged with any query string already present in URL
	 * @param array  $req_data      Request data, used as body in method POST, PUT or PATCH
	 * @param array  $req_headers   Request headers
	 * @param string $req_data_type Request data type, can be (urlencoded|json|*other*), default urlencoded
	 *
	 * @return Response
	 */
	public function request(string $method, string $req_url, array $req_query = [], $req_data = null, array $req_headers = [], string $req_data_type = 'urlencoded') {
		// Force method to uppercase
		$method = strtoupper($method);

		// Strip query string from request URL and add to query array
		$req_url_qs_pos = strpos($req_url, '?');
		if ($req_url_qs_pos !== false) {
			$req_url_qs = Querystring::decode(substr($req_url, $req_url_qs_pos + 1));
			$req_url = substr($req_url, 0, $req_url_qs_pos);
			$req_query = array_merge($req_url_qs, $req_query);
		}

		// Add query array to request URL
		if (!empty($req_query)) {
			$req_url .= '?' . preg_replace('/%5B[0-9]+%5D/', '%5B%5D', http_build_query($req_query));
		}

		// Initialize cURL context
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $req_url);
		curl_setopt($ch, CURLOPT_USERAGENT, 'CargonizE2/1.0');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, $this->debug);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		// Add POST/PUT/PATCH body data to cURL context
		$body = '';
		switch ($method) {
			case 'POST':
			case 'PUT':
			case 'PATCH':
				if (!empty($req_data)) {
					switch ($req_data_type) {
						case 'application/json':
						case 'json':
							$body          = Json::encode($req_data);
							$req_headers[] = 'Content-Type: application/json; charset=utf-8';
							break;

						case 'application/x-www-form-urlencoded':
						case 'urlencoded':
							$body          = Querystring::encode($req_data);
							$req_headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
							break;

						case 'multipart/form-data':
						case 'multipart':
							$boundary = Multipart::generateBoundary();
							$body = FormData::encode($req_data, [], $boundary);
							$req_headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
							break;

						case 'raw':
						default:
							$body = $req_data;
							break;
					}
					$req_headers[] = 'Content-Length: ' . strlen($body);
					$req_headers[] = 'Expect:';
					curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
					curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
					curl_setopt($ch, CURLOPT_POST, 1);
				}
				break;
		}

		// Add headers to cURL context
		curl_setopt($ch, CURLOPT_HTTPHEADER, $req_headers);

		// Execute request, get response code and throw exception on error
		$res_data = curl_exec($ch);
		if ($res_data === false) {
			$res = new Response($res_data, [sprintf('cURL error (%d): %s', curl_errno($ch), curl_error($ch))]);
		} else {
			// Dump request if debugging
			if ($this->debug) {
				Log::debug(sprintf(static::$request_log_format, curl_getinfo($ch, CURLINFO_HEADER_OUT), $body));
			}

			// Get response code and size of response headers
			$res_code         = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			$res_header_size  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		}
		curl_close($ch);
		if ($res_data === false) {
			return $res;
		}

		// Dump response if debugging
		if ($this->debug) {
			Log::debug(sprintf(static::$response_log_format, $res_data));
		}

		// Get the headers of the last request (ignoring the headers of any redirects)
		$res_headerstr          = substr($res_data, 0, $res_header_size);
		$res_data               = substr($res_data, $res_header_size);
		$res_headerstr_startpos = strrpos("\r\n\r\n", $res_headerstr);
		if ($res_headerstr_startpos !== false) {
			$res_headerstr = substr($res_headerstr, $res_headerstr_startpos);
		}

		// Parse headers
		$res_headers = [];
		foreach (explode("\r\n", $res_headerstr) as $i => $line) {
			if ($i > 0) {
				$kv = explode(':', $line, 2);
				if (count($kv) === 1) {
					$res_headers[strtolower($kv[0])] = '';
				} elseif (count($kv) === 2) {
					$res_headers[strtolower($kv[0])] = ltrim($kv[1]);
				}
			}
		}

		// Parse body
		$errors = [];
		$res_data_decoded = null;
		if (array_key_exists('content-type', $res_headers)) {
			if (substr($res_headers['content-type'], 0, 16) === 'application/json') {
				$res_data_decoded = Json::decode($res_data);
				if ($res_data_decoded === null) {
					$errors[] = 'JSON decode error';
				}
			} elseif (substr($res_headers['content-type'], 0, 15) === 'application/xml' || substr($res_headers['content-type'], 0, 8) === 'text/xml') {
				$libxml_prev_use_internal_errors = libxml_use_internal_errors(true);
				$res_data_decoded = simplexml_load_string($res_data);
				if ($res_data_decoded === false) {
					foreach (libxml_get_errors() as $error) {
						switch ($error->level) {
							case LIBXML_ERR_WARNING:
								$errors[] = sprintf('XML decode warning at line %d column %d: %s',
									(int)$error->line,
									(int)$error->column,
									trim($error->message));
								break;
							case LIBXML_ERR_ERROR:
								$errors[] = sprintf('XML decode error at line %d column %d: %s',
									(int)$error->line,
									(int)$error->column,
									trim($error->message));
								break;
							case LIBXML_ERR_FATAL:
								$errors[] = sprintf('XML decode fatal error at line %d column %d: %s',
									(int)$error->line,
									(int)$error->column,
									trim($error->message));
								break;
						}
					}
				}
				libxml_use_internal_errors($libxml_prev_use_internal_errors);
			}
		}

		return new Response($res_data_decoded ?? $res_data, $errors, $res_code, $res_headers);
	}
}
