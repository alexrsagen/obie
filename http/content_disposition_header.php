<?php namespace Obie\Http;

class ContentDispositionHeader {
	const DISP_INLINE                 = 'inline';                 // RFC 2183 (section 2.1)
	const DISP_ATTACHMENT             = 'attachment';             // RFC 2183 (section 2.2)
	const DISP_FORM_DATA              = 'form-data';              // RFC 2388 (section 3)
	const DISP_FILE                   = 'file';                   // RFC 2388 (section 4.4)
	const DISP_SIGNAL                 = 'signal';                 // RFC 3204
	const DISP_ALERT                  = 'alert';                  // RFC 3261
	const DISP_ICON                   = 'icon';                   // RFC 3261
	const DISP_RENDER                 = 'render';                 // RFC 3261
	const DISP_RECIPIENT_LIST_HISTORY = 'recipient-list-history'; // RFC 5364
	const DISP_SESSION                = 'session';                // RFC 3261
	const DISP_AIB                    = 'aib';                    // RFC 3893
	const DISP_EARLY_SESSION          = 'early-session';          // RFC 3959
	const DISP_RECIPIENT_LIST         = 'recipient-list';         // RFC 5363
	const DISP_NOTIFICATION           = 'notification';           // RFC 5438
	const DISP_BY_REFERENCE           = 'by-reference';           // RFC 5621
	const DISP_INFO_PACKAGE           = 'info-package';           // RFC 6086
	const DISP_RECORDING_SESSION      = 'recording-session';      // RFC 7866

	const PARAM_FILENAME          = 'filename';          // RFC 2183
	const PARAM_CREATION_DATE     = 'creation-date';     // RFC 2183
	const PARAM_MODIFICATION_DATE = 'modification-date'; // RFC 2183
	const PARAM_READ_DATE         = 'read-date';         // RFC 2183
	const PARAM_SIZE              = 'size';              // RFC 2183
	const PARAM_NAME              = 'name';              // RFC 7578
	const PARAM_VOICE             = 'voice';             // RFC 2421
	const PARAM_HANDLING          = 'handling';          // RFC 3204
	const PARAM_PREVIEW_TYPE      = 'preview-type';      // RFC 7763
	const PARAM_REACTION          = 'reaction';          // RFC 9078

	function __construct(
		public string $disposition = self::DISP_ATTACHMENT,
		public array $parameters = [],
	) {
		$this->disposition = strtolower(trim($disposition, "\n\r\t "));
	}

	public static function decode(string $input): ?static {
		// https://mimesniff.spec.whatwg.org/#parsing-a-mime-type
		// (modified for Content-Disposition header format)

		// 4.4.1: Remove any leading and trailing HTTP whitespace from input.
		$input = trim($input, "\n\r\t ");
		// 4.4.2: Let position be a position variable for input, initially pointing at the start of input.
		$position = 0;
		// 4.4.7 (modified): Let disposition be the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
		$disposition = '';
		for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {
			$disposition .= $input[$position];
		}
		// 4.4.8 (modified): Remove any trailing HTTP whitespace from disposition.
		$disposition = strtolower(rtrim($disposition, "\n\r\t "));
		// 4.4.9 (modified): If disposition is the empty string or does not solely contain HTTP token code points, then return failure.
		if (strlen($disposition) === 0 || !Token::isValidToken($disposition)) return null;
		// 4.4.10 (modified): Let contentDisposition be a new Content-Disposition record whose disposition is disposition, in ASCII lowercase.
		$content_disposition = new static($disposition);
		// 4.4.11: While position is not past the end of input:
		while ($position < strlen($input)) {
			// 4.4.11.1: Advance position by 1. (This skips past U+003B (;).)
			$position++;
			// 4.4.11.2: Collect a sequence of code points that are HTTP whitespace from input given position.
			// This is roughly equivalent to skip ASCII whitespace, except that HTTP whitespace is used rather than ASCII whitespace.
			for (; $position < strlen($input) && (
				$input[$position] === "\n" ||
				$input[$position] === "\r" ||
				$input[$position] === "\t" ||
				$input[$position] === " "); $position++) {}
			// 4.4.11.3: Let parameterName be the result of collecting a sequence of code points that are not U+003B (;) or U+003D (=) from input, given position.
			$parameter_name = '';
			for (; $position < strlen($input) && $input[$position] !== ';' && $input[$position] !== '='; $position++) {
				$parameter_name .= $input[$position];
			}
			// 4.4.11.4: Set parameterName to parameterName, in ASCII lowercase.
			$parameter_name = strtolower($parameter_name);
			// 4.4.11.5: If position is not past the end of input, then:
			if ($position < strlen($input)) {
				// 4.4.11.5.1: If the code point at position within input is U+003B (;), then continue.
				if ($input[$position] === ';') continue;
				// 4.4.11.5.2: Advance position by 1. (This skips past U+003D (=).)
				$position++;
			}
			// 4.4.11.6: If position is past the end of input, then break.
			if ($position >= strlen($input)) break;
			// 4.4.11.7: Let parameterValue be null.
			$parameter_value = null;
			// 4.4.11.8: If the code point at position within input is U+0022 ("), then:
			if ($input[$position] === '"') {
				// (added): If parameterName ends with U+002A (*), then return failure.
				if (str_ends_with($parameter_name, '*')) return null;
				// 4.4.11.8.1: Set parameterValue to the result of collecting an RFC 7230 quoted-string from input, given position and the extract-value flag.
				$parameter_value = QuotedString::extract($input, $position, true);
				// 4.4.11.8.2: Collect a sequence of code points that are not U+003B (;) from input, given position.
				// Given attachment;filename="example.txt"invalid-data you end up with attachment;filename=example.txt.
				for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {}
			}
			// (added): Otherwise, if parameterName ends with U+002A (*), then:
			elseif (str_ends_with($parameter_name, '*')) {
				// Set parameterValue to the result of collecting an RFC 8187 ext-value from input, given position and the extract-value flag.
				$parameter_value = ExtendedHeaderValue::extract($input, $position, true)?->decodeValue();
				if (!is_string($parameter_value)) continue;
				// Remove any trailing HTTP whitespace from parameterValue.
				$parameter_value = rtrim($parameter_value, "\n\r\t ");
				// If parameterValue is the empty string, then continue.
				if (strlen($parameter_value) === 0) continue;
			}
			// 4.4.11.9: Otherwise:
			else {
				// 4.4.11.9.1: Set parameterValue to the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
				$parameter_value = '';
				for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {
					$parameter_value .= $input[$position];
				}
				// 4.4.11.9.2: Remove any trailing HTTP whitespace from parameterValue.
				$parameter_value = rtrim($parameter_value, "\n\r\t ");
				// 4.4.11.9.3 (modified): If parameterValue is the empty string or does not solely contain RFC 8187 value-chars, then continue.
				if (strlen($parameter_value) === 0 || !Token::isValidParamValue($parameter_value)) continue;
			}

			// 4.4.11.10 (modified): If all of the following are true then set contentDisposition's parameters[parameterName] to parameterValue.
			if (
				// parameterName is not the empty string
				$parameter_name !== null && strlen($parameter_name) !== 0 &&
				// (modified) parameterName solely contains RFC 8187 attr-char code points
				Token::isValidParamName(rtrim($parameter_name, '*')) &&
				// (modified) contentDisposition's parameters[parameterName] does not exist
				!array_key_exists($parameter_name, $content_disposition->parameters)
			) {
				$content_disposition->parameters[$parameter_name] = $parameter_value;
			}
		}

		// Replace each parameter with their extended header value, if present.
		$names = array_keys($content_disposition->parameters);
		rsort($names, SORT_STRING);
		foreach ($names as $name) {
			$base_name = rtrim($name, '*');
			if (strlen($name) > strlen($base_name)) {
				$content_disposition->parameters[$base_name] = $content_disposition->parameters[$name];
				unset($content_disposition->parameters[$name]);
			}
		}

		// Return contentDisposition.
		return $content_disposition;
	}

	public function encode(bool $extended_header_value = true): string {
		// Let serialization be contentDisposition's disposition.
		$serialization = $this->disposition;
		// For each name → value of contentDisposition's parameters:
		foreach ($this->parameters as $name => $value) {
			$is_valid_param_value = Token::isValidParamValue($value);
			$is_ascii = true;
			if (!$is_valid_param_value) {
				for ($i = 0; $i < strlen($value); $i++) {
					if (!ctype_print($value[$i])) {
						$is_ascii = false;
						break;
					}
				}
			}

			// If value is not the empty string and solely contains RFC 8187 value-chars, then:
			if (strlen($value) > 0 && $is_valid_param_value) {
				// Append U+003B (;) to serialization.
				$serialization .= '; ';
				// Append name to serialization.
				$serialization .= $name;
				// Append U+003D (=) to serialization.
				$serialization .= '=';
				// Append quoted-string encoding of value to serialization.
				$serialization .= $value;
			}
			// Otherwise:
			else {
				// Append U+003B (;) to serialization.
				$serialization .= '; ';
				// Append name to serialization.
				$serialization .= $name;
				// Append U+003D (=) to serialization.
				$serialization .= '=';
				// Append quoted-string encoding of value to serialization.
				$serialization .= QuotedString::encode($value, true);
			}

			if ($extended_header_value && !$is_ascii) {
				// Append U+003B (;) to serialization.
				$serialization .= '; ';
				// Append name to serialization.
				$serialization .= $name;
				// Append U+003D (=) to serialization.
				$serialization .= '*=';
				// Append ext-value encoding of value to serialization.
				$serialization .= ExtendedHeaderValue::encode($value);
			}
		}
		return $serialization;
	}
}