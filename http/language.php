<?php namespace Obie\Http;

class Language {
	public function __construct(
		public string $locale = '',
		public array $parameters = [],
	) {
		$this->locale = locale_canonicalize($locale) ?? '';
	}

	public static function decode(string $input): ?static {
		// https://mimesniff.spec.whatwg.org/#parsing-a-mime-type
		// (modified for Accept-Language header format)

		// 4.4.1: Remove any leading and trailing HTTP whitespace from input.
		$input = trim($input, "\n\r\t ");
		// 4.4.2: Let position be a position variable for input, initially pointing at the start of input.
		$position = 0;
		// 4.4.7 (modified): Let locale be the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
		$locale = '';
		for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {
			$locale .= $input[$position];
		}
		// 4.4.8 (modified): Remove any trailing HTTP whitespace from locale.
		$locale = rtrim($locale, "\n\r\t ");
		// 4.4.9 (modified): If locale is the empty string or does not solely contain HTTP token code points, then return failure.
		if (strlen($locale) === 0 || !Token::isValidToken($locale)) return null;
		// 4.4.10 (modified): Let language be a new language record whose locale is an RFC 5646 canonicalized form of the locale.
		$language = new static($locale);
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
				// 4.4.11.8.1: Set parameterValue to the result of collecting an HTTP quoted string from input, given position and the extract-value flag.
				$parameter_value = QuotedString::extract($input, $position, true);
				// 4.4.11.8.2: Collect a sequence of code points that are not U+003B (;) from input, given position.
				// Given text/html;charset="shift_jis"iso-2022-jp you end up with text/html;charset=shift_jis.
				for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {}
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

			// 4.4.11.10 (modified): If all of the following are true then set language’s parameters[parameterName] to parameterValue.
			if (
				// parameterName is not the empty string
				$parameter_value !== null && strlen($parameter_value) !== 0 &&
				// parameterName solely contains HTTP token code points
				Token::isValidToken($parameter_name) &&
				// parameterValue solely contains HTTP quoted-string token code points
				QuotedString::isValid($parameter_value, true) &&
				// (modified) language’s parameters[parameterName] does not exist
				!array_key_exists($parameter_name, $language->parameters)
			) {
				$language->parameters[$parameter_name] = $parameter_value;
			}
		}

		// 4.4.12: Return language.
		return $language;
	}

	public function encode(): string {
		// https://mimesniff.spec.whatwg.org/#serializing-a-mime-type
		// 4.5.1 (modified): Let serialization be the concatenation of language’s type, U+002F (/), and language’s subtype.
		$serialization = $this->locale;
		// 4.5.2 (modified): For each name → value of language’s parameters:
		foreach ($this->parameters as $name => $value) {
			// 4.5.2.1. Append U+003B (;) to serialization.
			$serialization .= ';';
			// 4.5.2.2: Append name to serialization.
			$serialization .= $name;
			// 4.5.2.3: Append U+003D (=) to serialization.
			$serialization .= '=';
			// 4.5.2.4: If value does not solely contain HTTP token code points or value is the empty string, then:
			if (strlen($value) === 0 || !Token::isValidToken($value)) {
				// 4.5.2.4.1: Precede each occurence of U+0022 (") or U+005C (\) in value with U+005C (\).
				// 4.5.2.4.2: Prepend U+0022 (") to value.
				// 4.5.2.4.3: Append U+0022 (") to value.
				$value = QuotedString::encode($value, true);
			}
			// 4.5.2.5: Append value to serialization.
			$serialization .= $value;
		}
		// 4.5.3: Return serialization.
		return $serialization;
	}

	public function getParameter(string $name): ?string {
		return array_key_exists($name, $this->parameters) ? $this->parameters[$name] : null;
	}

	public function setParameter(string $name, ?string $value = null): static {
		if ($value === null) {
			unset($this->parameters[$name]);
		} else {
			$this->parameters[$name] = $value;
		}
		return $this;
	}
}
