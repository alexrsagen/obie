<?php namespace Obie\Encoding;

class Url {
	public static function decode(string $url): array|false {
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

	/**
	 * Remove dot segments, as per RFC 3986 section 5.2.4.
	 * WARNING: This function will trim any leading "../"
	 *
	 * @param string $input
	 * @return string $output
	 */
	public static function removeDotSegments(string $input) {
		// 1.  The input buffer is initialized with the now-appended path
		//     components and the output buffer is initialized to the empty
		//     string.
		$output = '';

		// 2.  While the input buffer is not empty, loop as follows:
		while ($input !== '') {
			if (
				($prefix = substr($input, 0, 3)) === '../' ||
				($prefix = substr($input, 0, 2)) === './'
			) {
				// A.  If the input buffer begins with a prefix of "`../`" or "`./`",
				//     then remove that prefix from the input buffer; otherwise,
				$input = substr($input, strlen($prefix));
			} elseif (
				// B.  if the input buffer begins with a prefix of "`/./`" or "`/.`",
				//     where "`.`" is a complete path segment, then replace that
				//     prefix with "`/`" in the input buffer; otherwise,
				($prefix = substr($input, 0, 3)) === '/./' ||
				($prefix = $input) === '/.'
			) {
				$input = '/' . substr($input, strlen($prefix));
			} elseif (
				// C.  if the input buffer begins with a prefix of "/../" or "/..",
				//     where "`..`" is a complete path segment, then replace that
				//     prefix with "`/`" in the input buffer and remove the last
				//     segment and its preceding "/" (if any) from the output
				//     buffer; otherwise,
				($prefix = substr($input, 0, 4)) === '/../' ||
				($prefix = $input) === '/..'
			) {
				$input = '/' . substr($input, strlen($prefix));
				$output = substr($output, 0, strrpos($output, '/'));
			} elseif ($input === '.' || $input === '..') {
				// D.  if the input buffer consists only of "." or "..", then remove
				//     that from the input buffer; otherwise,
				$input = '';
			} else {
				// E.  move the first path segment in the input buffer to the end of
				//     the output buffer, including the initial "/" character (if
				//     any) and any subsequent characters up to, but not including,
				//     the next "/" character or the end of the input buffer.
				$pos = strpos($input, '/');
				if ($pos === 0) $pos = strpos($input, '/', $pos+1);
				if ($pos === false) $pos = strlen($input);
				$output .= substr($input, 0, $pos);
				$input = (string) substr($input, $pos);
			}
		}

		// 3.  Finally, the output buffer is returned as the result of remove_dot_segments.
		return $output;
	}
}