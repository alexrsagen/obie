<?php namespace Obie\Encoding;
use Obie\Encoding\Spf1\Directive;
use Obie\Encoding\Spf1\MacroString;
use Obie\Encoding\Spf1\Record;
use Obie\Log;

class Spf1 {
	const QUALIFIER_PASS = '+';
	const QUALIFIER_FAIL = '-';
	const QUALIFIER_SOFTFAIL = '~';
	const QUALIFIER_NEUTRAL = '?';

	const MECHANISM_ALL = 'all';
	const MECHANISM_INCLUDE = 'include';
	const MECHANISM_A = 'a';
	const MECHANISM_MX = 'mx';
	const MECHANISM_PTR = 'ptr';
	const MECHANISM_IP4 = 'ip4';
	const MECHANISM_IP6 = 'ip6';
	const MECHANISM_EXISTS = 'exists';

	protected static function storeDirective(string &$buf, ?Directive &$directive) {
		// create a new directive, if we have not already started decoding a directive
		if ($directive === null) {
			$directive = new Directive();
		}
		// if that the directive already has a mechanism set, then:
		if ($directive->mechanism !== '') {
			// store the value in the directive
			$directive->value = $buf;
		}
		// otherwise:
		else {
			// assert that the mechanism is valid
			if (!in_array($buf, [
				self::MECHANISM_ALL,
				self::MECHANISM_INCLUDE,
				self::MECHANISM_A,
				self::MECHANISM_MX,
				self::MECHANISM_PTR,
				self::MECHANISM_IP4,
				self::MECHANISM_IP6,
				self::MECHANISM_EXISTS,
			], true)) {
				Log::warning(sprintf('Spf1: invalid mechanism key (found "%s")', addslashes($buf)));
				return false;
			}

			// store the mechanism in the directive
			$directive->mechanism = $buf;
		}
		// empty the buffer
		$buf = '';
		return true;
	}

	public static function decode(string $input): ?Record {
		$record = new Record();

		// check version
		if ($input === 'v=spf1') return $record;
		if (!str_starts_with($input, 'v=spf1 ')) return null;

		// decode SPF record
		$buf = '';
		$buf_key = '';
		$directive = null;
		for ($position = 7; $position < strlen($input); $position++) {
			$c = $input[$position];
			switch ($c) {
			// qualifiers
			case self::QUALIFIER_PASS:
			case self::QUALIFIER_FAIL:
			case self::QUALIFIER_SOFTFAIL:
			case self::QUALIFIER_NEUTRAL:
				// assert that we have not already started decoding a directive
				if ($directive !== null) {
					Log::warning('Spf1: unexpected qualifier');
					return null;
				}
				// create a new directive
				$directive = new Directive();
				// store the qualifier in the directive
				$directive->qualifier = $c;
				break;

			// modifier key-value delimiter
			case '=':
				// assert that we have not already started decoding a value
				if ($buf_key !== '') {
					Log::warning('Spf1: double modifier key');
					return null;
				}
				// store the key in the key buffer
				$buf_key = $buf;
				// assert that the key is a valid modifier name
				if (preg_match('/^[a-z0-9\-_\.]+$/i', $buf_key) !== 1) {
					Log::warning('Spf1: ');
					return null;
				}
				// advance position by 1
				$position++;
				// fill the buffer with the result of extracting a macro-string from input, given position
				$buf = MacroString::extractString($input, $position);
				// assert that the buffer contains a valid macro-string
				if ($buf === null) {
					Log::warning('Spf1: invalid macro-string');
					return null;
				}
				// append the modifier to the record
				$record->modifiers[$buf_key] = $buf;
				// empty the buffers
				$buf = '';
				$buf_key = '';
				break;

			// mechanism key-value delimiter
			case ':':
				// assert that the directive does not already have a mechanism set
				if ($directive !== null && $directive->mechanism !== '') {
					Log::warning('Spf1: double mechanism key');
					return null;
				}
				// store mechanism key in directive
				if (!static::storeDirective($buf, $directive)) return null;
				// advance position by 1
				$position++;
				// fill the buffer with the result of extracting a macro-string from input, given position
				$buf = MacroString::extractString($input, $position);
				// assert that the buffer contains a valid macro-string
				if ($buf === null) {
					Log::warning('Spf1: invalid macro-string');
					return null;
				}
				if ($position >= strlen($input)) {
					Log::warning('Spf1: invalid domain-spec: ended before domain-end');
					return null;
				}
				if ($input[$position] === '.') {
					// extract a toplabel from input, append to buffer
					for (; $position < strlen($input) && preg_match('/^[a-z0-9\-]$/i', $input[$position]) === 1; $position++) {
						$buf .= $input[$position];
					}
					if ($position < strlen($input) && $input[$position] === '.') {
						$buf .= $input[$position];
						$position++;
					}
				} elseif ($input[$position] === '%') {
					// append to buffer the result of extracting a macro-expand from input, given position
					$buf_key = $buf;
					$buf = MacroString::extranctExpand($input, $position);
					if ($buf === null) {
						Log::warning('Spf1: invalid macro-expand');
						return null;
					}
					$buf = $buf_key . $buf;
					$buf_key = '';
				}
				// store mechanism value in directive
				if (!static::storeDirective($buf, $directive)) return null;
				// add the directive to the record
				$record->directives[] = $directive;
				// empty the directive
				$directive = null;
				break;

			// term delimiter
			case ' ':
				if (!static::storeDirective($buf, $directive)) return null;
				// add the directive to the record
				$record->directives[] = $directive;
				// empty the directive
				$directive = null;
				break;

			// any other character
			default:
				// append the current character to the buffer
				$buf .= $c;
				break;
			}
		}

		// add the last directive to the record
		if ($buf !== '' && !static::storeDirective($buf, $directive)) return null;
		if ($directive !== null) {
			$record->directives[] = $directive;
		}

		return $record;
	}

	public static function encode(Record $input): string {
		$output = 'v=spf1';
		foreach ($input->directives as $directive) {
			$output .= ' ';
			if ($directive->qualifier !== self::QUALIFIER_PASS) {
				$output .= $directive->qualifier;
			}
			$output .= $directive->mechanism;
			if ($directive->value !== '') {
				$output .= ':';
				$output .= $directive->value;
			}
		}
		foreach ($input->modifiers as $key => $value) {
			$output .= ' ';
			$output .= $key;
			$output .= '=';
			$output .= $value;
		}
		return $output;
	}
}