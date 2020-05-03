<?php namespace ZeroX\Encoding;
if (!defined('IN_ZEROX')) {
	return;
}

class Base32 {
	const ALPHABET     = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	const ALPHABET_HEX = '0123456789ABCDEFGHIJKLMNOPQRSTUV';

	const LOOKUP = [
		'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,  'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
		'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
		'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
		'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31];

	const LOOKUP_HEX = [
		'0' => 0,  '1' => 1,  '2' => 2,  '3' => 3,  '4' => 4,  '5' => 5,  '6' => 6,  '7' => 7,
		'8' => 8,  '9' => 9,  'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15,
		'G' => 16, 'H' => 17, 'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23,
		'O' => 24, 'P' => 25, 'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31];

	/**
	 * RFC 4648-compliant base 32 encode
	 *
	 * @param string $input
	 * @return string
	 */
	public static function encode(string $input, string $alphabet = self::ALPHABET): string {
		$grp_raw = "\x00\x00\x00\x00\x00";
		$grp_enc = "========";
		$output = '';
		for ($i = 0; $i < ceil(strlen($input) / 5); $i++) {
			$grp_raw = substr($input, $i * 5, 5);
			$grp_enc[0] = $alphabet[(ord($grp_raw[0]) & 0b11111000) >> 3];
			$grp_enc[1] = $alphabet[((ord($grp_raw[0]) & 0b00000111) << 2) +
				(strlen($grp_raw) < 2 ? 0 : ((ord($grp_raw[1]) & 0b11000000) >> 6))];
			if (strlen($grp_raw) > 1) {
				$grp_enc[2] = $alphabet[(ord($grp_raw[1]) & 0b00111110) >> 1];
				$grp_enc[3] = $alphabet[((ord($grp_raw[1]) & 0b00000001) << 4) +
					(strlen($grp_raw) < 3 ? 0 : ((ord($grp_raw[2]) & 0b11110000) >> 4))];
			} else {
				$grp_enc[2] = '=';
				$grp_enc[3] = '=';
			}
			if (strlen($grp_raw) > 2) {
				$grp_enc[4] = $alphabet[((ord($grp_raw[2]) & 0b00001111) << 1) +
					(strlen($grp_raw) < 4 ? 0 : ((ord($grp_raw[3]) & 0b10000000) >> 7))];
			} else {
				$grp_enc[4] = '=';
			}
			if (strlen($grp_raw) > 3) {
				$grp_enc[5] = $alphabet[(ord($grp_raw[3]) & 0b01111100) >> 2];
				$grp_enc[6] = $alphabet[((ord($grp_raw[3]) & 0b00000011) << 3) +
					(strlen($grp_raw) < 5 ? 0 : ((ord($grp_raw[4]) & 0b11100000) >> 5))];
			} else {
				$grp_enc[5] = '=';
				$grp_enc[6] = '=';
			}
			if (strlen($grp_raw) > 4) {
				$grp_enc[7] = $alphabet[ord($grp_raw[4]) & 0b00011111];
			} else {
				$grp_enc[7] = '=';
			}
			$output .= $grp_enc;
		}
		return $output;
	}

	public static function encodeHex(string $input): string {
		return static::encode($input, self::ALPHABET_HEX);
	}

	/**
	 * RFC 4648-compliant base 32 decode
	 *
	 * @param string $input
	 * @return string
	 */
	public static function decode(string $input, array $lookup = self::LOOKUP): string {
		$grp_enc = "========";
		$output = '';
		for ($i = 0; $i < ceil(strlen($input) / 8); $i++) {
			$grp_enc = substr($input, $i * 8, 8);
			if (strlen($grp_enc) < 1 || $grp_enc[0] === '=' || $grp_enc[1] === '=') return $output;
			$output .= chr((($lookup[$grp_enc[0]] & 0b00011111) << 3) +
				(($lookup[$grp_enc[1]] & 0b00011100) >> 2));
			if (strlen($grp_enc) < 2 || $grp_enc[1] === '=' || $grp_enc[2] === '=' || $grp_enc[3] === '=') return $output;
			$output .= chr((($lookup[$grp_enc[1]] & 0b00000011) << 6) +
				(($lookup[$grp_enc[2]] & 0b00011111) << 1) +
				(($lookup[$grp_enc[3]] & 0b00010000) >> 4));
			if (strlen($grp_enc) < 4 || $grp_enc[3] === '=' || $grp_enc[4] === '=') return $output;
			$output .= chr((($lookup[$grp_enc[3]] & 0b00001111) << 4) +
				(($lookup[$grp_enc[4]] & 0b00011110) >> 1));
			if (strlen($grp_enc) < 5 || $grp_enc[4] === '=' || $grp_enc[5] === '=' || $grp_enc[6] === '=') return $output;
			$output .= chr((($lookup[$grp_enc[4]] & 0b00000001) << 7) +
				(($lookup[$grp_enc[5]] & 0b00011111) << 2) +
				(($lookup[$grp_enc[6]] & 0b00011000) >> 3));
			if (strlen($grp_enc) < 7 || $grp_enc[6] === '=' || $grp_enc[7] === '=') return $output;
			$output .= chr((($lookup[$grp_enc[6]] & 0b00000111) << 5) +
				($lookup[$grp_enc[7]]));
		}
		return $output;
	}

	public static function decodeHex(string $input): string {
		return static::decode($input, self::LOOKUP_HEX);
	}
}