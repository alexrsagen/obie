<?php namespace Obie\Encoding\Spf1;
use Obie\Log;

// MacroString::extractString implements an RFC 7208 macro-string encoder/decoder.
class MacroString {
	const MACRO_LETTER_CHARS = [
		"s", "l", "o", "d", "i", "p", "h", "c",
		"r", "t", "v"
	];

	// macro-literal: visible characters except "%"
	const MACRO_LITERAL_CHARS = [
		// %x21-24
		"\x21", "\x22", "\x23", "\x24",

		// %x26-7E
		"\x26", "\x27", "\x28", "\x29", "\x2a", "\x2b", "\x2c", "\x2d",
		"\x2e", "\x2f", "\x30", "\x31", "\x32", "\x33", "\x34", "\x35",
		"\x36", "\x37", "\x38", "\x39", "\x3a", "\x3b", "\x3c", "\x3d",
		"\x3e", "\x3f", "\x40", "\x41", "\x42", "\x43", "\x44", "\x45",
		"\x46", "\x47", "\x48", "\x49", "\x4a", "\x4b", "\x4c", "\x4d",
		"\x4e", "\x4f", "\x50", "\x51", "\x52", "\x53", "\x54", "\x55",
		"\x56", "\x57", "\x58", "\x59", "\x5a", "\x5b", "\x5c", "\x5d",
		"\x5e", "\x5f", "\x60", "\x61", "\x62", "\x63", "\x64", "\x65",
		"\x66", "\x67", "\x68", "\x69", "\x6a", "\x6b", "\x6c", "\x6d",
		"\x6e", "\x6f", "\x70", "\x71", "\x72", "\x73", "\x74", "\x75",
		"\x76", "\x77", "\x78", "\x79", "\x7a", "\x7b", "\x7c", "\x7d",
		"\x7e",
	];

	public static function extractString(string $input, int &$position): ?string {
		// 1. Let positionStart be position.
		$position_start = $position;
		// 3. While true:
		while (true) {
			// 3.1. Collect a sequence of code points (macro-literal) from input, given position.
			for (; $position < strlen($input) && in_array($input[$position], self::MACRO_LITERAL_CHARS, true); $position++) {}
			// 3.2. If position is past the end of input, then break.
			if ($position >= strlen($input)) break;
			// 3.3. If the code point at position within input is not "%", break.
			if ($input[$position] !== '%') break;
			// 3.4. Extract macro-expand
			if (static::extranctExpand($input, $position) === null) return null;
		}
		// 4. Return the code points from positionStart to position, inclusive, within input.
		return substr($input, $position_start, $position - $position_start);
	}

	public static function extranctExpand(string $input, int &$position): ?string {
		// 1. Let positionStart be position.
		$position_start = $position;
		// 2. Assert: the code point at position within input is "%"
		if ($position >= strlen($input)) return null;
		if ($input[$position] !== '%') {
			Log::warning(sprintf('Spf1: MacroString::extractExpand: first code point is not "%%" (found "%s")', addslashes(substr($input, $position-1, 3))));
			return null;
		}
		// 3. Advance position by 1 (skipping "%")
		$position++;
		// 4. Let macroOpenOrEscapedLiteral be the code point at position within input.
		if ($position >= strlen($input)) return null;
		$macro_open_or_escaped_literal = $input[$position];
		// 5. Advance position by 1.
		$position++;
		// 6. If macroOpenOrEscapedLiteral is not "%", "_" or "-", then:
		if (!in_array($macro_open_or_escaped_literal, ['%', '_', '-'], true)) {
			// 6.1. Assert: macroOpenOrEscapedLiteral is "{"
			if ($macro_open_or_escaped_literal !== '{') {
				Log::warning(sprintf('Spf1: MacroString::extractExpand: macroOpenOrEscapedLiteral is not "{" (found "%s")', addslashes($macro_open_or_escaped_literal)));
				return null;
			}
			// 6.2. Let macroLetter be the code point at position within input.
			if ($position >= strlen($input)) return null;
			$macro_letter = $input[$position];
			// 6.3. Advance position by 1.
			$position++;
			// 6.4. Assert: macroLetter is a macro-letter
			if (!in_array($macro_letter, self::MACRO_LETTER_CHARS, true)) {
				Log::warning('Spf1: MacroString::extractExpand: macroLetter is not a valid macro-letter');
				return null;
			}
			// 6.5. Collect a sequence of digits (transformers, *DIGIT) from input, given position.
			for (; $position < strlen($input) && strpos('0123456789', $input[$position]) !== false; $position++) {}
			// 6.6. Assert: Position is not past the end of input.
			if ($position >= strlen($input)) {
				Log::warning('Spf1: MacroString::extractExpand: macroOpenOrEscapedLiteral ended early, after collecting transformers digits');
				return null;
			}
			// 6.7. If the code point at position within input is "r", then:
			if ($input[$position] === 'r') {
				// 6.7.1. Advance position by 1.
				$position++;
			}
			// 6.8. Collect a sequence of code points (delimiter) from input, given position.
			for (; $position < strlen($input) && strpos('.-+,/_=', $input[$position]) !== false; $position++) {}
			// 6.11. Assert: Position is past the end of input.
			if ($position >= strlen($input)) {
				Log::warning('Spf1: MacroString::extractExpand: macroOpenOrEscapedLiteral ended early, after collecting delimiters');
				return null;
			}
			// 6.12. Let macroEnd be the code point at position within input.
			$macro_end = $input[$position];
			// 6.13. Advance position by 1.
			$position++;
			// 6.14. Assert: macroEnd is "}"
			if ($macro_end !== '}') {
				Log::warning('Spf1: MacroString::extractExpand: macroEnd is not "}"');
				return null;
			}
		}
		// 7. Return the code points from positionStart to position, inclusive, within input.
		return substr($input, $position_start, $position - $position_start);
	}
}