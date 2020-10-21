<?php namespace ZeroX\Encoding;

class Url {
	public static function decode(string $url): array {
		return parse_url($url);
	}

	public static function encode(array $parts): string {
		$has_auth = false;
		$url = '';
		if (array_key_exists('scheme', $parts)) {
			$url .= $parts['scheme'] . '://';
		}
		if (array_key_exists('user', $parts)) {
			$has_auth = true;
			$url .= $parts['user'];
		}
		if (array_key_exists('pass', $parts)) {
			$has_auth = true;
			$url .= ':' . $parts['pass'];
		}
		if ($has_auth) {
			$url .= '@';
		}
		if (array_key_exists('host', $parts)) {
			$url .= $parts['host'];
		}
		if (array_key_exists('port', $parts)) {
			$url .= ':' . $parts['port'];
		}
		if (array_key_exists('path', $parts) && !empty($parts['path'])) {
			$url .= '/' . ltrim($parts['path'], '/');
		}
		if (array_key_exists('query', $parts)) {
			if (is_array($parts['query'])) {
				$parts['query'] = Querystring::encode($parts['query']);
			}
			$url .= '?' . $parts['query'];
		}
		if (array_key_exists('fragment', $parts)) {
			$url .= '#' . $parts['fragment'];
		}
		return $url;
	}
}