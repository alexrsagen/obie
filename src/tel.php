<?php namespace Obie;

class Tel {
	const FMT_NUM = 'num'; // Just the number, no prefix or calling code
	const FMT_LOC = 'loc'; // Local format without international prefix or calling code
	const FMT_NAT = 'nat'; // Local format with international prefix, space, calling code
	const FMT_RAW = 'raw'; // E.164 without international prefix
	const FMT_INT = 'int'; // E.164 with international prefix, calling code
	const FMT_EPP = 'epp'; // E.164 with international prefix, dot, calling code
	const FMT_TEL = 'tel'; // RFC 3966

	const TYP_FIX = 'fixedLine';
	const TYP_MOB = 'mobile';
	const TYP_TOL = 'tollFree';
	const TYP_PRR = 'premiumRate';
	const TYP_SHR = 'sharedCost';
	const TYP_PER = 'personalNumber';
	const TYP_VOI = 'voip';
	const TYP_UAN = 'uan';
	const TYP_VMN = 'voicemail';

	const NON_DIGIT_REGEX = '/[^\d\x{FF10}-\x{FF19}\x{0660}-\x{0669}\x{06F0}-\x{06F9}a-zA-Z\s()\.\-]/u';
	const NON_INT_PREFIX_OR_DIGIT_REGEX = '/[^\d\x{FF10}-\x{FF19}\x{0660}-\x{0669}\x{06F0}-\x{06F9}a-zA-Z\s()\.\-\+\x{FF0B}]/u';

	protected string $fmt = '';
	protected string $int = '';
	protected string $calling_code = '';
	protected string $country_code = '';
	protected string $typ = '';
	protected string $num = '';
	protected string $ext = '';
	protected array $params = [];

	public function getFormat(): string {
		return $this->fmt;
	}
	public function getInternationalPrefix(): string {
		return $this->int;
	}
	public function getCallingCode(): string {
		return $this->calling_code;
	}
	public function getCountry(): ?string {
		return strlen($this->country_code) > 0 ? $this->country_code : null;
	}
	public function getType(): ?string {
		return strlen($this->typ) > 0 ? $this->typ : null;
	}
	public function getNumber(): string {
		return $this->num;
	}
	public function getExt(): string {
		return $this->ext;
	}
	public function getParams(): array {
		return $this->params;
	}

	public function setFormat(string $fmt): static {
		switch ($fmt) {
		case self::FMT_LOC:
		case self::FMT_INT:
		case self::FMT_EPP:
		case self::FMT_NAT:
		case self::FMT_TEL:
			$this->fmt = $fmt;
			break;
		default:
			$this->fmt = self::FMT_NAT;
			break;
		}
		return $this;
	}
	public function setCallingCode(string $calling_code, string $int = '+'): static {
		$this->calling_code = $calling_code;
		if (strlen($calling_code) === 0) {
			$this->int = '';
		} else {
			$this->int = $int;
		}
		return $this;
	}
	public function setNum(string $num): static {
		$this->num = static::normalize($num);
		return $this;
	}
	public function setExt(string $ext): static {
		$this->ext = static::normalize($ext);
		return $this;
	}
	public function setParam(string $key, string $value): static {
		if ($key === 'ext') {
			$this->ext = $value;
		} else {
			$this->params[$key] = $value;
		}
		return $this;
	}
	public function setParams(array $params): static {
		if (array_key_exists('ext', $params)) {
			if (is_string($params['ext'])) {
				$this->ext = static::normalize($params['ext']);
			} else {
				$this->ext = '';
			}
			unset($params['ext']);
		}
		$this->params = $params;
		return $this;
	}

	/**
	 * Find number length by finding the first character which can not be
	 * a part of the number
	 * @param string $number The full phone number
	 * @param int $offset Offset at which to start looking for the length
	 * @return int The phone number length (defaults to full length if no specific end found)
	 */
	protected static function extractNumberLength(string $number, int $offset = 0): int {
		$num_len = strlen($number) - $offset;
		for ($i = $offset; $i < strlen($number); $i++) {
			if (preg_match(self::NON_DIGIT_REGEX, $number[$i]) === 1) {
				$num_len = $i - $offset;
				break;
			}
		}
		return $num_len;
	}

	protected static function extractPossibleCallingCodes(string $num, ?string $fallback_cc = null, bool $raw_guess_cc = false): string|array {
		// build calling code list from largest to smallest
		static $calling_code_list = null;
		if ($calling_code_list === null) {
			$calling_code_list = array_keys(self::METADATA);
			rsort($calling_code_list, SORT_NUMERIC);
			$calling_code_list = array_map('strval', $calling_code_list);
		}

		// find calling code
		$slice = '';
		$num_no_cc = '';
		$calling_code_matches = []; // (string)$calling_code => (int)$score
		foreach ($calling_code_list as $calling_code) {
			// update slice when needed, as cc gets shorter
			if (strlen($slice) !== strlen($calling_code)) {
				$slice = substr($num, 0, strlen($calling_code));
			}

			// update num_no_cc (remove cc from num)
			if (substr($num, 0, strlen($calling_code)) === $calling_code) {
				$num_no_cc = substr($num, strlen($calling_code));
			} else {
				$num_no_cc = $num;
			}

			// increase score if calling code matches
			if ($slice === $calling_code) {
				// initialize calling code matches
				if (!array_key_exists($calling_code, $calling_code_matches)) {
					$calling_code_matches[$calling_code] = 0;
				}
				$calling_code_matches[$calling_code]++;
			}
		}

		// guess calling code (if no exact match found)
		if ($raw_guess_cc && count($calling_code_matches) === 0) {
			$slice = '';
			$num_no_cc = '';
			foreach ($calling_code_list as $calling_code) {
				// update slice when needed, as cc gets shorter
				if (strlen($slice) !== strlen($calling_code)) {
					$slice = substr($num, 0, strlen($calling_code));
				}

				// update num_no_cc (remove cc from num)
				if (substr($num, 0, strlen($calling_code)) === $calling_code) {
					$num_no_cc = substr($num, strlen($calling_code));
				} else {
					$num_no_cc = $num;
				}

				// check that number matches the pattern of a country having
				// the current calling code
				foreach (self::METADATA[$calling_code]['countries'] as $country_code => $country) {
					if ($country['pattern'] === null) continue;

					// skip numbers not matching national number pattern
					if (
						preg_match($country['pattern'], $num_no_cc) !== 1 &&
						preg_match($country['pattern'], $num) !== 1
					) continue;

					// initialize calling code matches
					if (!array_key_exists($calling_code, $calling_code_matches)) {
						$calling_code_matches[$calling_code] = 0;
					}

					// attempt to match up to one usage type pattern
					foreach ($country['patterns'] as $typ => $pattern) {
						if ($pattern['pattern'] === null) continue;
						if (
							count($pattern['lengths']['national']) > 0 &&
							!in_array(strlen($num_no_cc), $pattern['lengths']['national'], true) &&
							!in_array(strlen($num), $pattern['lengths']['national'], true)
						) continue;

						// skip numbers not matching usage-specific number pattern
						if (
							preg_match($pattern['pattern'], $num_no_cc) !== 1 &&
							preg_match($pattern['pattern'], $num) !== 1
						) continue;

						// add points for usage-specific number pattern matches
						$calling_code_matches[$calling_code]++;
						break;
					}
				}
			}
		}

		// prefer fallback calling code, increase its score (if found)
		if ($fallback_cc !== null && array_key_exists($fallback_cc, $calling_code_matches)) {
			$calling_code_matches[$fallback_cc]++;
		}

		// return early if no calling codes match
		if (count($calling_code_matches) === 0) return [];

		$calling_code_matches_max_score = array_map('strval', array_keys($calling_code_matches, max($calling_code_matches)));
		if (count($calling_code_matches_max_score) === 1) {
			// return calling code with highest score, if exactly one found
			return $calling_code_matches_max_score[0];
		}

		// could not find one calling code with max score, return
		// list of all possible calling codes sorted by score
		$calling_codes_by_score_desc = array_keys($calling_code_matches);
		usort($calling_codes_by_score_desc, function($a, $b) use($calling_code_matches) {
			return $calling_code_matches[$a] <=> $calling_code_matches[$b];
		});
		return $calling_codes_by_score_desc;
	}

	protected static function findCountryCode(string $calling_code, string $num_no_cc): string|array {
		$country_code_matches = []; // (string)$country_code => (int)$score
		if (!array_key_exists($calling_code, self::METADATA)) return [];
		foreach (self::METADATA[$calling_code]['countries'] as $country_code => $country) {
			if ($country['pattern'] === null) continue;

			// skip numbers not matching national number pattern
			if (preg_match($country['pattern'], $num_no_cc) !== 1) continue;

			// initialize calling code matches
			if (!array_key_exists($country_code, $country_code_matches)) {
				$country_code_matches[$country_code] = 0;
			}

			// attempt to match up to one usage type pattern
			foreach ($country['patterns'] as $typ => $pattern) {
				if ($pattern['pattern'] === null) continue;
				if (
					count($pattern['lengths']['national']) > 0 &&
					!in_array(strlen($num_no_cc), $pattern['lengths']['national'], true)
				) continue;

				// skip numbers not matching usage-specific number pattern
				if (preg_match($pattern['pattern'], $num_no_cc) !== 1) continue;

				// add points for usage-specific number pattern matches
				$country_code_matches[$country_code]++;
				break;
			}
		}

		// return early if no country codes match
		if (count($country_code_matches) === 0) return [];

		$country_code_matches_max_score = array_keys($country_code_matches, max($country_code_matches));
		if (count($country_code_matches_max_score) === 1) {
			// return country code with highest score, if exactly one found
			return $country_code_matches_max_score[0];
		} elseif (in_array(self::METADATA[$calling_code]['main_country'], $country_code_matches_max_score, true)) {
			// return main country for calling code, if matched
			return self::METADATA[$calling_code]['main_country'];
		}

		// could not find one country code with max score, return
		// list of all possible country codes sorted by score
		$country_codes_by_score_desc = array_keys($country_code_matches);
		usort($country_codes_by_score_desc, function($a, $b) use($country_code_matches) {
			return $country_code_matches[$a] <=> $country_code_matches[$b];
		});
		return $country_codes_by_score_desc;
	}

	protected static function findType(string $calling_code, string $country_code, string $num_no_cc): ?string {
		if (!array_key_exists($calling_code, self::METADATA)) return null;
		if (!array_key_exists($country_code, self::METADATA[$calling_code]['countries'])) return null;
		$country = self::METADATA[$calling_code]['countries'][$country_code];
		if ($country['pattern'] === null) return null;

		// return early if number doesn't match national number pattern
		if (preg_match($country['pattern'], $num_no_cc) !== 1) return null;

		// return first usage type which pattern matches number
		foreach ($country['patterns'] as $typ => $pattern) {
			if ($pattern['pattern'] === null) continue;
			if (
				count($pattern['lengths']['national']) > 0 &&
				!in_array(strlen($num_no_cc), $pattern['lengths']['national'], true)
			) continue;

			// skip numbers not matching usage-specific number pattern
			if (preg_match($pattern['pattern'], $num_no_cc) !== 1) continue;

			return $typ;
		}

		return null;
	}

	public static function parse(string $number, ?string $fallback_cc = null, bool $raw_guess_cc = false): static {
		$res = new static;
		if (strlen($number) === 0) return $res;
		$offset = 0;

		// detect RFC 3966 encoding
		// https://datatracker.ietf.org/doc/html/rfc 3966#section-3
		if (strtolower(substr($number, 0, 4)) === 'tel:') {
			$res->fmt = self::FMT_TEL;
			$offset += 4;
		}

		// skip anything that isn't an international dialing prefix or digit
		for (; preg_match(self::NON_INT_PREFIX_OR_DIGIT_REGEX, substr($number, $offset, 1)) === 1 && $offset < strlen($number); $offset++) {}
		if ($offset >= strlen($number)) return $res;

		// detect international dialing prefix
		$slice = '';
		foreach (self::INT_PREFIXES as $prefix) {
			// update slice when needed, as prefix gets shorter
			if (strlen($slice) !== strlen($prefix)) {
				$slice = substr($number, $offset, strlen($prefix));
			}
			if ($slice === $prefix) {
				$res->int = $prefix;
				$offset += strlen($prefix);
				break;
			}
		}

		// skip anything that isn't a digit
		for (; preg_match(self::NON_DIGIT_REGEX, substr($number, $offset, 1)) === 1 && $offset < strlen($number); $offset++) {}
		if ($offset >= strlen($number)) return $res;

		// extract number as FMT_RAW (meaning that it includes calling code)
		$num = static::normalize(substr($number, $offset, static::extractNumberLength($number, $offset)));

		// find calling code if international dialing prefix was detected,
		// extracted number contains fallback calling code,
		// or guessing calling code was enabled
		if (strlen($res->int) !== 0 || $raw_guess_cc) {
			$calling_code = static::extractPossibleCallingCodes($num, $fallback_cc, $raw_guess_cc);
			if (is_string($calling_code)) {
				$res->calling_code = $calling_code;
			}
		}

		// force calling code to fallback if guessing is disabled
		if (strlen($res->calling_code) === 0 && $fallback_cc !== null && array_key_exists($fallback_cc, self::METADATA) && !$raw_guess_cc) {
			$res->calling_code = $fallback_cc;
			$res->country_code = self::METADATA[$res->calling_code]['main_country'];
		}

		if (strlen($res->calling_code) > 0) {
			if (substr($num, 0, strlen($res->calling_code)) === $res->calling_code) {
				$num_no_cc = substr($num, strlen($res->calling_code));
			} else {
				$num_no_cc = $num;
			}

			if (strlen($res->country_code) === 0) {
				$country_code = static::findCountryCode($res->calling_code, $num_no_cc);
				if (is_string($country_code)) {
					$res->country_code = $country_code;
				}
			}

			if (strlen($res->country_code) > 0) {
				$typ = static::findType($res->calling_code, $res->country_code, $num_no_cc);
				if (is_string($typ)) {
					$res->typ = $typ;
				}
			}

			if (array_key_exists($res->calling_code, self::METADATA) && array_key_exists($res->country_code, self::METADATA[$res->calling_code]['countries'])) {
				$country = self::METADATA[$res->calling_code]['countries'][$res->country_code];
				$num_is_raw = (
					preg_match($country['pattern'], $num_no_cc) === 1 &&
					preg_match($country['pattern'], $num) !== 1
				);
			} else {
				$num_is_raw = $num_no_cc !== $num;
			}

			// add calling code length to offset
			if ($num_is_raw) {
				$offset += strlen($res->calling_code);
			}

			// find calling code separator, detect format
			if ($res->fmt !== self::FMT_TEL) {
				switch (substr($number, $offset, 1)) {
				case ' ':
					$res->fmt = self::FMT_NAT;
					$offset++;
					break;
				case '.':
					$res->fmt = self::FMT_EPP;
					$offset++;
					break;
				default:
					$res->fmt = strlen($res->int) !== 0 ? self::FMT_INT : ($num_is_raw ? self::FMT_RAW : self::FMT_NUM);
					break;
				}
			}
		} elseif ($res->fmt !== self::FMT_TEL) {
			$res->fmt = self::FMT_NUM;
		}

		// skip anything that isn't a digit
		for (; preg_match(self::NON_DIGIT_REGEX, substr($number, $offset, 1)) === 1 && $offset < strlen($number); $offset++) {}
		if ($offset >= strlen($number)) return $res;

		// set number, detect format
		$num_len = static::extractNumberLength($number, $offset);
		$res->num = substr($number, $offset, $num_len);
		if ($res->fmt === self::FMT_NUM && strpos($res->num, ' ') !== false) {
			$res->fmt = self::FMT_LOC;
		}
		$res->num = static::normalize($res->num);
		$offset += $num_len;

		// parse extension, if present
		if (substr($number, $offset, 1) === '~') {
			$offset++;

			// find extension length by finding first non-digit char
			$ext_len = strlen($number) - $offset;
			for ($i = $offset; $i < strlen($number); $i++) {
				if (preg_match(self::NON_DIGIT_REGEX, $number[$i]) === 1) {
					$ext_len = $i - $offset;
					break;
				}
			}

			// set extension
			$res->ext = static::normalize(substr($number, $offset, $ext_len));
			$offset += $ext_len;
		}

		// parse params if RFC 3966
		if ($res->fmt === self::FMT_TEL) {
			// skip anything that isn't a semicolon (find first param)
			for (; substr($number, $offset, 1) !== ';' && $offset < strlen($number); $offset++) {}
			if ($offset >= strlen($number)) return $res;

			// parse params
			$res->params = array_merge_recursive(...array_map(function($pair) {
				$kv = explode('=', $pair, 2);
				if (empty($kv[0])) return []; // skip empty pairs
				return count($kv) > 1 ? [$kv[0] => rawurldecode($kv[1])] : [$kv[0]];
			}, explode(';', substr($number, $offset))));

			// extract extension from params
			if (array_key_exists('ext', $res->params)) {
				$res->ext = static::normalize($res->params['ext']);
				unset($res->params['ext']);
			}
		}

		return $res;
	}

	public function getMetadataFormat(): ?array {
		foreach (self::METADATA[$this->calling_code]['formats'] as $format) {
			if (
				preg_match($format['pattern'], $this->num) === 1 &&
				(
					!array_key_exists('leadingDigits', $format) ||
					preg_match($format['leadingDigits'], $this->num) === 1
				)
			) return $format;
		}
		return null;
	}

	public function stripDuplicateCC(): static {
		if (array_key_exists($this->calling_code, self::METADATA)) {
			$tmp_num = $this->num;
			while ($this->getMetadataFormat() === null && str_starts_with($this->num, $this->calling_code)) {
				$this->num = substr($this->num, strlen($this->calling_code));
			}
			if ($this->getMetadataFormat() === null) {
				// number still does not match any formats, restore original number
				$this->num = $tmp_num;
			}
		}
		return $this;
	}

	public function format(?string $fmt = null): string {
		$res = '';
		if ($fmt === null) {
			$fmt = $this->fmt;
		}

		// ensure number extension is stored in $this->ext and not params
		if (strlen($this->ext) === 0 && array_key_exists('ext', $this->params) && is_string($this->params['ext']) && strlen($this->params['ext']) !== 0) {
			$this->ext = $this->params['ext'];
		}
		if (array_key_exists('ext', $this->params)) {
			unset($this->params['ext']);
		}

		// add tel: prefix if format is RFC 3966
		if ($fmt === self::FMT_TEL) {
			$res .= 'tel:';
		}

		// add international dialing prefix and calling code, if a calling code
		// is specified and local formatting is not specified
		if (strlen($this->calling_code) !== 0 && $fmt !== self::FMT_LOC && $fmt !== self::FMT_NUM) {
			if ($fmt !== self::FMT_RAW) {
				$res .= strlen($this->int) === 0 ? '+' : $this->int;
			}
			$res .= $this->calling_code;
			if ($fmt === self::FMT_NAT) {
				$res .= ' ';
			} elseif ($fmt === self::FMT_EPP) {
				$res .= '.';
			}
		}

		// add phone number
		if (array_key_exists($this->calling_code, self::METADATA) && ($fmt === self::FMT_LOC || $fmt === self::FMT_NAT)) {
			// local formatting
			// find matching format
			$format = $this->getMetadataFormat();
			// apply formatting if possible
			if ($format !== null) {
				$res .= preg_replace($format['pattern'], $format['format'], $this->num);
			} else {
				$res .= $this->num;
			}
		} else {
			// international formatting
			$res .= $this->num;
		}

		// add phone number extension with tilde if format is not RFC 3966
		if (strlen($this->ext) !== 0 && $fmt !== self::FMT_TEL) {
			$res .= '~' . $this->ext;
		}

		// add phone number params if format is RFC 3966
		if ($fmt === self::FMT_TEL) {
			// rebuild array to ensure ext and isdn-subaddress always appear
			// first, as per RFC 3966 section 3
			$params = [];
			if (strlen($this->ext) !== 0) {
				$params['ext'] = $this->ext;
			}
			if (array_key_exists('isdn-subaddress', $this->params)) {
				$params['isdn-subaddress'] = $this->params['isdn-subaddress'];
			}
			$params = array_merge($params, $this->params);
			foreach ($params as $k => $v) {
				$res .= ';' . $k;
				if (is_string($v) && strlen($v) !== 0) {
					$res .= '=' . rawurlencode($v);
				}
			}
		}

		return $res;
	}

	protected static function normalize(string $number): string {
		// replace lowercase letters with uppercase letters
		$number = strtoupper($number);

		// replace letters and localized symbols with latin/dialable symbols
		$number = str_replace([
			"\u{FF10}", // Fullwidth digit 0
			"\u{FF11}", // Fullwidth digit 1
			"\u{FF12}", // Fullwidth digit 2
			"\u{FF13}", // Fullwidth digit 3
			"\u{FF14}", // Fullwidth digit 4
			"\u{FF15}", // Fullwidth digit 5
			"\u{FF16}", // Fullwidth digit 6
			"\u{FF17}", // Fullwidth digit 7
			"\u{FF18}", // Fullwidth digit 8
			"\u{FF19}", // Fullwidth digit 9
			"\u{0660}", // Arabic-indic digit 0
			"\u{0661}", // Arabic-indic digit 1
			"\u{0662}", // Arabic-indic digit 2
			"\u{0663}", // Arabic-indic digit 3
			"\u{0664}", // Arabic-indic digit 4
			"\u{0665}", // Arabic-indic digit 5
			"\u{0666}", // Arabic-indic digit 6
			"\u{0667}", // Arabic-indic digit 7
			"\u{0668}", // Arabic-indic digit 8
			"\u{0669}", // Arabic-indic digit 9
			"\u{06F0}", // Eastern-Arabic digit 0
			"\u{06F1}", // Eastern-Arabic digit 1
			"\u{06F2}", // Eastern-Arabic digit 2
			"\u{06F3}", // Eastern-Arabic digit 3
			"\u{06F4}", // Eastern-Arabic digit 4
			"\u{06F5}", // Eastern-Arabic digit 5
			"\u{06F6}", // Eastern-Arabic digit 6
			"\u{06F7}", // Eastern-Arabic digit 7
			"\u{06F8}", // Eastern-Arabic digit 8
			"\u{06F9}", // Eastern-Arabic digit 9
			'A', 'B', 'C',
			'D', 'E', 'F',
			'G', 'H', 'I',
			'J', 'K', 'L',
			'M', 'N', 'O',
			'P', 'Q', 'R', 'S',
			'T', 'U', 'V',
			'W', 'X', 'Y', 'Z',
		], [
			'0', // Fullwidth digit 0
			'1', // Fullwidth digit 1
			'2', // Fullwidth digit 2
			'3', // Fullwidth digit 3
			'4', // Fullwidth digit 4
			'5', // Fullwidth digit 5
			'6', // Fullwidth digit 6
			'7', // Fullwidth digit 7
			'8', // Fullwidth digit 8
			'9', // Fullwidth digit 9
			'0', // Arabic-indic digit 0
			'1', // Arabic-indic digit 1
			'2', // Arabic-indic digit 2
			'3', // Arabic-indic digit 3
			'4', // Arabic-indic digit 4
			'5', // Arabic-indic digit 5
			'6', // Arabic-indic digit 6
			'7', // Arabic-indic digit 7
			'8', // Arabic-indic digit 8
			'9', // Arabic-indic digit 9
			'0', // Eastern-Arabic digit 0
			'1', // Eastern-Arabic digit 1
			'2', // Eastern-Arabic digit 2
			'3', // Eastern-Arabic digit 3
			'4', // Eastern-Arabic digit 4
			'5', // Eastern-Arabic digit 5
			'6', // Eastern-Arabic digit 6
			'7', // Eastern-Arabic digit 7
			'8', // Eastern-Arabic digit 8
			'9', // Eastern-Arabic digit 9
			'2', '2', '2',      // A, B, C
			'3', '3', '3',      // D, E, F
			'4', '4', '4',      // G, H, I
			'5', '5', '5',      // J, K, L
			'6', '6', '6',      // M, N, O
			'7', '7', '7', '7', // P, Q, R; S
			'8', '8', '8',      // T, U, V
			'9', '9', '9', '9', // W, X, Y, Z
		], $number);

		// remove non-digits from number
		$number = preg_replace('/[^\d]/', '', $number);

		return $number;
	}

	public static function __set_state(array $properties): object {
		$res = new static;
		foreach ($properties as $k => $v) {
			if (property_exists($res, $k)) {
				$res->{$k} = $v;
			}
		}
		return $res;
	}

	// This data manually generated from:
	// https://github.com/google/libphonenumber/blob/0a45cfd96e71cad8edb0e162a70fcc8bd9728933/resources/PhoneNumberMetadata.xml
	const INT_PREFIXES = [
		'0011',
		'810',
		'119',
		'020',
		'011',
		'010',
		'009',
		'001',
		'000',
		'00',
		'0',
		'+',
		"\u{FF0B}"
	];

	// This data automatically generated from:
	// https://github.com/google/libphonenumber/blob/3fa578c0ea9fdff0e08819b90678d2153bcc6543/resources/PhoneNumberMetadata.xml
	const METADATA = [
		'247' => [
			'formats' => [],
			'countries' => [
				'AC' => [
					'pattern' => '/^(?:(?:[01589]\\d|[46])\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[2-467]\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0[1-9]|[1589]\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'376' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[135-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'AD' => [
					'pattern' => '/^(?:(?:1|6\\d)\\d{7}|[135-9]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[78]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:690\\d{6}|[356]\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:180[02]\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[19]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'971' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2,9}))$/i',
					'leadingDigits' => '/^(?:60|8)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[236]|[479][2-8])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d)(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[479])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'AE' => [
					'pattern' => '/^(?:(?:[4-7]\\d|9[0-689])\\d{7}|800\\d{2,9}|[2-4679]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:[2-4679][2-8]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[024-68]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:400\\d{6}|800\\d{2,9})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[02]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700[05]\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:600[25]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'93' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'AF' => [
					'pattern' => '/^(?:[2-7]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:[25][0-8]|[34][0-4]|6[0-5])[2-9]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:7\\d{8})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'1' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '($1) $2-$3',
					'intlFormat' => '$1-$2-$3',
				],
			],
			'countries' => [
				'AG' => [
					'pattern' => '/^(?:(?:268|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:268(?:4(?:6[0-38]|84)|56[0-2])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:268(?:464|7(?:1[3-9]|[28]\\d|3[0246]|64|7[0-689]))\\d{4})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:26840[69]\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:26848[01]\\d{4})$/i',
						],
					],
				],
				'AI' => [
					'pattern' => '/^(?:(?:264|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:264(?:292|4(?:6[12]|9[78]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:264(?:235|4(?:69|76)|5(?:3[6-9]|8[1-4])|7(?:29|72))\\d{4})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:264724\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'AS' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|684|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:6846(?:22|33|44|55|77|88|9[19])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:684(?:2(?:48|5[2468]|72)|7(?:3[13]|70|82))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'BB' => [
					'pattern' => '/^(?:(?:246|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:246(?:2(?:2[78]|7[0-4])|4(?:1[024-6]|2\\d|3[2-9])|5(?:20|[34]\\d|54|7[1-3])|6(?:2\\d|38)|7[35]7|9(?:1[89]|63))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:246(?:2(?:[3568]\\d|4[0-57-9])|45\\d|69[5-7]|8(?:[2-5]\\d|83))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:246976|900[2-9]\\d\\d)\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:24631\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:246(?:292|367|4(?:1[7-9]|3[01]|44|67)|7(?:36|53))\\d{4})$/i',
						],
					],
				],
				'BM' => [
					'pattern' => '/^(?:(?:441|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:441(?:[46]\\d\\d|5(?:4\\d|60|89))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:441(?:[2378]\\d|5[0-39])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'BS' => [
					'pattern' => '/^(?:(?:242|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:242(?:3(?:02|[236][1-9]|4[0-24-9]|5[0-68]|7[347]|8[0-4]|9[2-467])|461|502|6(?:0[1-4]|12|2[013]|[45]0|7[67]|8[78]|9[89])|7(?:02|88))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:242(?:3(?:5[79]|7[56]|95)|4(?:[23][1-9]|4[1-35-9]|5[1-8]|6[2-8]|7\\d|81)|5(?:2[45]|3[35]|44|5[1-46-9]|65|77)|6[34]6|7(?:27|38)|8(?:0[1-9]|1[02-9]|2\\d|[89]9))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:242300\\d{4}|8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:242225\\d{4})$/i',
						],
					],
				],
				'CA' => [
					'pattern' => '/^(?:(?:[2-8]\\d|90)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:2(?:04|[23]6|[48]9|50)|3(?:06|43|6[578])|4(?:03|1[68]|3[178]|50|74)|5(?:06|1[49]|48|79|8[17])|6(?:04|13|39|47|72)|7(?:0[59]|78|8[02])|8(?:[06]7|19|25|73)|90[25])[2-9]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:2(?:04|[23]6|[48]9|50)|3(?:06|43|6[578])|4(?:03|1[68]|3[178]|50|74)|5(?:06|1[49]|48|79|8[17])|6(?:04|13|39|47|72)|7(?:0[59]|78|8[02])|8(?:[06]7|19|25|73)|90[25])[2-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|(?:5(?:00|2[12]|33|44|66|77|88)|622)[2-9]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:600[2-9]\\d{6})$/i',
						],
					],
				],
				'DM' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|767|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:767(?:2(?:55|66)|4(?:2[01]|4[0-25-9])|50[0-4])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:767(?:2(?:[2-4689]5|7[5-7])|31[5-7]|61[1-8]|70[1-6])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'DO' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:8(?:[04]9[2-9]\\d\\d|29(?:2(?:[0-59]\\d|6[04-9]|7[0-27]|8[0237-9])|3(?:[0-35-9]\\d|4[7-9])|[45]\\d\\d|6(?:[0-27-9]\\d|[3-5][1-9]|6[0135-8])|7(?:0[013-9]|[1-37]\\d|4[1-35689]|5[1-4689]|6[1-57-9]|8[1-79]|9[1-8])|8(?:0[146-9]|1[0-48]|[248]\\d|3[1-79]|5[01589]|6[013-68]|7[124-8]|9[0-8])|9(?:[0-24]\\d|3[02-46-9]|5[0-79]|60|7[0169]|8[57-9]|9[02-9])))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:8[024]9[2-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00(?:14|[2-9]\\d)|(?:33|44|55|66|77|88)[2-9]\\d)\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'GD' => [
					'pattern' => '/^(?:(?:473|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:473(?:2(?:3[0-2]|69)|3(?:2[89]|86)|4(?:[06]8|3[5-9]|4[0-49]|5[5-79]|73|90)|63[68]|7(?:58|84)|800|938)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:473(?:4(?:0[2-79]|1[04-9]|2[0-5]|58)|5(?:2[01]|3[3-8])|901)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'GU' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|671|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:671(?:3(?:00|3[39]|4[349]|55|6[26])|4(?:00|56|7[1-9]|8[0236-9])|5(?:55|6[2-5]|88)|6(?:3[2-578]|4[24-9]|5[34]|78|8[235-9])|7(?:[0479]7|2[0167]|3[45]|8[7-9])|8(?:[2-57-9]8|6[48])|9(?:2[29]|6[79]|7[1279]|8[7-9]|9[78]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:671(?:3(?:00|3[39]|4[349]|55|6[26])|4(?:00|56|7[1-9]|8[0236-9])|5(?:55|6[2-5]|88)|6(?:3[2-578]|4[24-9]|5[34]|78|8[235-9])|7(?:[0479]7|2[0167]|3[45]|8[7-9])|8(?:[2-57-9]8|6[48])|9(?:2[29]|6[79]|7[1279]|8[7-9]|9[78]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'JM' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|658|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:8766060\\d{3}|(?:658(?:2(?:[0-8]\\d|9[0-46-9])|[3-9]\\d\\d)|876(?:52[35]|6(?:0[1-3579]|1[0237-9]|[23]\\d|40|5[06]|6[2-589]|7[05]|8[04]|9[4-9])|7(?:0[2-689]|[1-6]\\d|8[056]|9[45])|9(?:0[1-8]|1[02378]|[2-8]\\d|9[2-468])))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:658295|876(?:2(?:0[2-9]|[14-9]\\d|2[013-9]|3[3-9])|[348]\\d\\d|5(?:0[1-9]|[1-9]\\d)|6(?:4[89]|6[67])|7(?:0[07]|7\\d|8[1-47-9]|9[0-36-9])|9(?:[01]9|9[0579])))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'KN' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:869(?:2(?:29|36)|302|4(?:6[015-9]|70)|56[5-7])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:869(?:48[89]|55[6-8]|66\\d|76[02-7])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'KY' => [
					'pattern' => '/^(?:(?:345|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:345(?:2(?:22|3[23]|44|66)|333|444|6(?:23|38|40)|7(?:30|4[35-79]|6[6-9]|77)|8(?:00|1[45]|25|[48]8)|9(?:14|4[035-9]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:345(?:32[1-9]|42[0-4]|5(?:1[67]|2[5-79]|4[6-9]|50|76)|649|9(?:1[679]|2[2-9]|3[06-9]|90))\\d{4})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:345849\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:345976|900[2-9]\\d\\d)\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'LC' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|758|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:758(?:234|4(?:30|5\\d|6[2-9]|8[0-2])|57[0-2]|(?:63|75)8)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:758(?:28[4-7]|384|4(?:6[01]|8[4-9])|5(?:1[89]|20|84)|7(?:1[2-9]|2\\d|3[0-3])|812)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'MP' => [
					'pattern' => '/^(?:[58]\\d{9}|(?:67|90)0\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:670(?:2(?:3[3-7]|56|8[4-8])|32[1-38]|4(?:33|8[348])|5(?:32|55|88)|6(?:64|70|82)|78[3589]|8[3-9]8|989)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:670(?:2(?:3[3-7]|56|8[4-8])|32[1-38]|4(?:33|8[348])|5(?:32|55|88)|6(?:64|70|82)|78[3589]|8[3-9]8|989)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'MS' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|664|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:6644(?:1[0-3]|91)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:664(?:3(?:49|9[1-6])|49[2-6])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'PR' => [
					'pattern' => '/^(?:(?:[589]\\d\\d|787)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:787|939)[2-9]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:787|939)[2-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'SX' => [
					'pattern' => '/^(?:7215\\d{6}|(?:[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:7215(?:4[2-8]|8[239]|9[056])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:7215(?:1[02]|2\\d|5[034679]|8[014-8])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'TC' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|649|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:649(?:266|712|9(?:4\\d|50))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:649(?:2(?:3[129]|4[1-79])|3\\d\\d|4[34][1-3])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:649(?:71[01]|966)\\d{4})$/i',
						],
					],
				],
				'TT' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:868(?:2(?:0[13]|1[89]|[23]\\d|4[0-2])|6(?:0[7-9]|1[02-8]|2[1-9]|[3-69]\\d|7[0-79])|82[124])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:868(?:(?:2[5-9]|3\\d)\\d|4(?:3[0-6]|[6-9]\\d)|6(?:20|78|8\\d)|7(?:0[1-9]|1[02-9]|[2-9]\\d))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:868619\\d{4})$/i',
						],
					],
				],
				'US' => [
					'pattern' => '/^(?:[2-9]\\d{9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:5(?:05(?:[2-57-9]\\d\\d|6(?:[0-35-9]\\d|44))|82(?:2(?:0[0-3]|[268]2)|3(?:0[02]|33)|4(?:00|4[24]|65|82)|5(?:00|29|83)|6(?:00|66|82)|777|8(?:00|88)|9(?:00|9[89])))\\d{4}|(?:2(?:0[1-35-9]|1[02-9]|2[03-589]|3[149]|4[08]|5[1-46]|6[0279]|7[0269]|8[13])|3(?:0[1-57-9]|1[02-9]|2[01356]|3[0-24679]|4[167]|5[12]|6[014]|8[056])|4(?:0[124-9]|1[02-579]|2[3-5]|3[0245]|4[023578]|58|6[39]|7[0589]|8[04])|5(?:0[1-47-9]|1[0235-8]|20|3[0149]|4[01]|5[19]|6[1-47]|7[0-5]|8[056])|6(?:0[1-35-9]|1[024-9]|2[03689]|[34][016]|5[0179]|6[0-279]|78|8[0-29])|7(?:0[1-46-8]|1[2-9]|2[04-7]|3[1247]|4[037]|5[47]|6[02359]|7[0-59]|8[156])|8(?:0[1-68]|1[02-8]|2[08]|3[0-289]|4[03578]|5[046-9]|6[02-5]|7[028])|9(?:0[1346-9]|1[02-9]|2[0589]|3[0146-8]|4[01579]|5[12469]|7[0-389]|8[04-69]))[2-9]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:5(?:05(?:[2-57-9]\\d\\d|6(?:[0-35-9]\\d|44))|82(?:2(?:0[0-3]|[268]2)|3(?:0[02]|33)|4(?:00|4[24]|65|82)|5(?:00|29|83)|6(?:00|66|82)|777|8(?:00|88)|9(?:00|9[89])))\\d{4}|(?:2(?:0[1-35-9]|1[02-9]|2[03-589]|3[149]|4[08]|5[1-46]|6[0279]|7[0269]|8[13])|3(?:0[1-57-9]|1[02-9]|2[01356]|3[0-24679]|4[167]|5[12]|6[014]|8[056])|4(?:0[124-9]|1[02-579]|2[3-5]|3[0245]|4[023578]|58|6[39]|7[0589]|8[04])|5(?:0[1-47-9]|1[0235-8]|20|3[0149]|4[01]|5[19]|6[1-47]|7[0-5]|8[056])|6(?:0[1-35-9]|1[024-9]|2[03689]|[34][016]|5[0179]|6[0-279]|78|8[0-29])|7(?:0[1-46-8]|1[2-9]|2[04-7]|3[1247]|4[037]|5[47]|6[02359]|7[0-59]|8[156])|8(?:0[1-68]|1[02-8]|2[08]|3[0-289]|4[03578]|5[046-9]|6[02-5]|7[028])|9(?:0[1346-9]|1[02-9]|2[0589]|3[0146-8]|4[01579]|5[12469]|7[0-389]|8[04-69]))[2-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'VC' => [
					'pattern' => '/^(?:(?:[58]\\d\\d|784|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:784(?:266|3(?:6[6-9]|7\\d|8[0-6])|4(?:38|5[0-36-8]|8[0-8])|5(?:55|7[0-2]|93)|638|784)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:784(?:4(?:3[0-5]|5[45]|89|9[0-8])|5(?:2[6-9]|3[0-4])|720)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'VG' => [
					'pattern' => '/^(?:(?:284|[58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:284496[0-5]\\d{3}|284(?:229|4(?:22|9[45])|774|8(?:52|6[459]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:284496[6-9]\\d{3}|284(?:245|3(?:0[0-3]|4[0-7]|68|9[34])|4(?:4[0-6]|68|99)|5(?:4[0-7]|68|9[69]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
				'VI' => [
					'pattern' => '/^(?:[58]\\d{9}|(?:34|90)0\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:340(?:2(?:0[0-38]|2[06-8]|4[49]|77)|3(?:32|44)|4(?:2[23]|44|7[34]|89)|5(?:1[34]|55)|6(?:2[56]|4[23]|77|9[023])|7(?:1[2-57-9]|2[57]|7\\d)|884|998)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:340(?:2(?:0[0-38]|2[06-8]|4[49]|77)|3(?:32|44)|4(?:2[23]|44|7[34]|89)|5(?:1[34]|55)|6(?:2[56]|4[23]|77|9[023])|7(?:1[2-57-9]|2[57]|7\\d)|884|998)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[12]|33|44|66|77|88)[2-9]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => 'US',
		],
		'355' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:80|9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:4[2-6])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2358][2-5]|4)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[23578])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'AL' => [
					'pattern' => '/^(?:(?:700\\d\\d|900)\\d{3}|8\\d{5,7}|(?:[2-5]|6\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [
									5,
									6,
									7,
								],
							],
							'pattern' => '/^(?:4505[0-2]\\d{3}|(?:[2358][16-9]\\d[2-9]|4410)\\d{4}|(?:[2358][2-5][2-9]|4(?:[2-57-9][2-9]|6\\d))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6(?:[78][2-9]|9\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[1-9]\\d\\d)$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:808[1-9]\\d\\d)$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700[2-9]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'374' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[89]0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:2|3[12])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:1|47)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[3-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'AM' => [
					'pattern' => '/^(?:(?:[1-489]\\d|55|60|77)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:(?:(?:1[0-25]|47)\\d|2(?:2[2-46]|3[1-8]|4[2-69]|5[2-7]|6[1-9]|8[1-7])|3[12]2)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:33|4[1349]|55|77|88|9[13-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[016]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[1-4]\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:60(?:2[78]|3[5-9]|4[02-9]|5[0-46-9]|[6-8]\\d|9[01])\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'244' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[29])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'AO' => [
					'pattern' => '/^(?:[29]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2\\d(?:[0134][25-9]|[25-9]\\d)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[1-49]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'54' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3}))$/i',
					'leadingDigits' => '/^(?:0|1(?:0[0-35-7]|1[02-5]|2[015]|3[47]|4[478])|911)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-8])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-8])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2(?:[23]02|6(?:[25]|4(?:64|[78]))|9(?:[02356]|4(?:[0268]|5[2-6])|72|8[23]))|3(?:3[28]|4(?:[04679]|3(?:5(?:4[0-25689]|[56])|[78])|58|8[2379])|5(?:[2467]|3[237]|8(?:[23]|4(?:[45]|60)|5(?:4[0-39]|5|64)))|7[1-578]|8(?:[2469]|3[278]|54(?:4|5[13-7]|6[89])|86[3-6]))|2(?:2[24-9]|3[1-59]|47)|38(?:[58][78]|7[378])|3(?:454|85[56])[46]|3(?:4(?:36|5[56])|8(?:[38]5|76))[4-6])/i',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[68])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[23])/i',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9(?:2(?:[23]02|6(?:[25]|4(?:64|[78]))|9(?:[02356]|4(?:[0268]|5[2-6])|72|8[23]))|3(?:3[28]|4(?:[04679]|3(?:5(?:4[0-25689]|[56])|[78])|5(?:4[46]|8)|8[2379])|5(?:[2467]|3[237]|8(?:[23]|4(?:[45]|60)|5(?:4[0-39]|5|64)))|7[1-578]|8(?:[2469]|3[278]|5(?:4(?:4|5[13-7]|6[89])|[56][46]|[78])|7[378]|8(?:6[3-6]|[78]))))|92(?:2[24-9]|3[1-59]|47)|93(?:4(?:36|5[56])|8(?:[38]5|76))[4-6])/i',
					'format' => '$2 15-$3-$4',
					'intlFormat' => '$1 $2 $3-$4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:91)/i',
					'format' => '$2 15-$3-$4',
					'intlFormat' => '$1 $2 $3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$2 15-$3-$4',
					'intlFormat' => '$1 $2 $3-$4',
				],
			],
			'countries' => [
				'AR' => [
					'pattern' => '/^(?:(?:11|[89]\\d\\d)\\d{8}|[2368]\\d{9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [
									6,
									7,
									8,
								],
							],
							'pattern' => '/^(?:3888[013-9]\\d{5}|(?:29(?:54|66)|3(?:777|865))[2-8]\\d{5}|3(?:7(?:1[15]|81)|8(?:21|4[16]|69|9[12]))[46]\\d{5}|(?:2(?:2(?:2[59]|44|52)|3(?:26|44)|473|9(?:[07]2|2[26]|34|46))|3327)[45]\\d{5}|(?:2(?:284|302|657|920)|3(?:4(?:8[27]|92)|541|755|878))[2-7]\\d{5}|(?:2(?:(?:26|62)2|32[03]|477|9(?:42|83))|3(?:329|4(?:[47]6|62|89)|564))[2-6]\\d{5}|(?:(?:11[1-8]|670)\\d|2(?:2(?:0[45]|1[2-6]|3[3-6])|3(?:[06]4|7[45])|494|6(?:04|1[2-8]|[36][45]|4[3-6])|80[45]|9(?:[17][4-6]|[48][45]|9[3-6]))|3(?:364|4(?:1[2-7]|[235][4-6]|84)|5(?:1[2-8]|[38][4-6])|6(?:2[45]|44)|7[069][45]|8(?:[03][45]|[17][2-6]|[58][3-6])))\\d{6}|2(?:2(?:21|4[23]|6[145]|7[1-4]|8[356]|9[267])|3(?:16|3[13-8]|43|5[346-8]|9[3-5])|475|6(?:2[46]|4[78]|5[1568])|9(?:03|2[1457-9]|3[1356]|4[08]|[56][23]|82))4\\d{5}|(?:2(?:2(?:57|81)|3(?:24|46|92)|9(?:01|23|64))|3(?:4(?:42|71)|5(?:25|37|4[347]|71)|7(?:18|5[17])))[3-6]\\d{5}|(?:2(?:2(?:02|2[3467]|4[156]|5[45]|6[6-8]|91)|3(?:1[47]|25|[45][25]|96)|47[48]|625|932)|3(?:38[2578]|4(?:0[0-24-9]|3[78]|4[457]|58|6[03-9]|72|83|9[136-8])|5(?:2[124]|[368][23]|4[2689]|7[2-6])|7(?:16|2[15]|3[145]|4[13]|5[468]|7[2-5]|8[26])|8(?:2[5-7]|3[278]|4[3-5]|5[78]|6[1-378]|[78]7|94)))[4-6]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [
									6,
									7,
									8,
								],
							],
							'pattern' => '/^(?:93888[013-9]\\d{5}|9(?:29(?:54|66)|3(?:777|865))[2-8]\\d{5}|93(?:7(?:1[15]|81)|8(?:21|4[16]|69|9[12]))[46]\\d{5}|9(?:2(?:2(?:2[59]|44|52)|3(?:26|44)|473|9(?:[07]2|2[26]|34|46))|3327)[45]\\d{5}|9(?:2(?:284|302|657|920)|3(?:4(?:8[27]|92)|541|755|878))[2-7]\\d{5}|9(?:2(?:(?:26|62)2|32[03]|477|9(?:42|83))|3(?:329|4(?:[47]6|62|89)|564))[2-6]\\d{5}|(?:675\\d|9(?:11[1-8]\\d|2(?:2(?:0[45]|1[2-6]|3[3-6])|3(?:[06]4|7[45])|494|6(?:04|1[2-8]|[36][45]|4[3-6])|80[45]|9(?:[17][4-6]|[48][45]|9[3-6]))|3(?:364|4(?:1[2-7]|[235][4-6]|84)|5(?:1[2-8]|[38][4-6])|6(?:2[45]|44)|7[069][45]|8(?:[03][45]|[17][2-6]|[58][3-6]))))\\d{6}|92(?:2(?:21|4[23]|6[145]|7[1-4]|8[356]|9[267])|3(?:16|3[13-8]|43|5[346-8]|9[3-5])|475|6(?:2[46]|4[78]|5[1568])|9(?:03|2[1457-9]|3[1356]|4[08]|[56][23]|82))4\\d{5}|9(?:2(?:2(?:57|81)|3(?:24|46|92)|9(?:01|23|64))|3(?:4(?:42|71)|5(?:25|37|4[347]|71)|7(?:18|5[17])))[3-6]\\d{5}|9(?:2(?:2(?:02|2[3467]|4[156]|5[45]|6[6-8]|91)|3(?:1[47]|25|[45][25]|96)|47[48]|625|932)|3(?:38[2578]|4(?:0[0-24-9]|3[78]|4[457]|58|6[03-9]|72|83|9[136-8])|5(?:2[124]|[368][23]|4[2689]|7[2-6])|7(?:16|2[15]|3[145]|4[13]|5[468]|7[2-5]|8[26])|8(?:2[5-7]|3[278]|4[3-5]|5[78]|6[1-378]|[78]7|94)))[4-6]\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7,8})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:60[04579]\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:810\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'43' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3,12}))$/i',
					'leadingDigits' => '/^(?:1(?:11|[2-9]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:517)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,5}))$/i',
					'leadingDigits' => '/^(?:5[079])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,10}))$/i',
					'leadingDigits' => '/^(?:(?:31|4)6|51|6(?:5[0-3579]|[6-9])|7(?:20|32|8)|[89])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3,9}))$/i',
					'leadingDigits' => '/^(?:[2-467]|5[2-6])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4,7}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'AT' => [
					'pattern' => '/^(?:1\\d{3,12}|2\\d{6,12}|43(?:(?:0\\d|5[02-9])\\d{3,9}|2\\d{4,5}|[3467]\\d{4}|8\\d{4,6}|9\\d{4,7})|5\\d{4,12}|8\\d{7,12}|9\\d{8,12}|(?:[367]\\d|4[0-24-9])\\d{4,11})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									4,
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [3],
							],
							'pattern' => '/^(?:1(?:11\\d|[2-9]\\d{3,11})|(?:316|463|(?:51|66|73)2)\\d{3,10}|(?:2(?:1[467]|2[13-8]|5[2357]|6[1-46-8]|7[1-8]|8[124-7]|9[1458])|3(?:1[1-578]|3[23568]|4[5-7]|5[1378]|6[1-38]|8[3-68])|4(?:2[1-8]|35|7[1368]|8[2457])|5(?:2[1-8]|3[357]|4[147]|5[12578]|6[37])|6(?:13|2[1-47]|4[135-8]|5[468])|7(?:2[1-8]|35|4[13478]|5[68]|6[16-8]|7[1-6]|9[45]))\\d{4,10})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6(?:5[0-3579]|6[013-9]|[7-9]\\d)\\d{4,10})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6,10})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:0[01]|3[019])\\d{6,10})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:10|2[018])\\d{6,10}|828\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:0[1-9]|17|[79]\\d)\\d{2,10}|7[28]0\\d{6,10})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'61' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:16)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:13)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:19)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1802)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:19)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2,4}))$/i',
					'leadingDigits' => '/^(?:16)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:14|4)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2378])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1(?:30|[89]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:130)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
			],
			'countries' => [
				'AU' => [
					'pattern' => '/^(?:1(?:[0-79]\\d{7}(?:\\d(?:\\d{2})?)?|8[0-24-9]\\d{7})|[2-478]\\d{8}|1\\d{4,7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [8],
							],
							'pattern' => '/^(?:(?:(?:2(?:[0-26-9]\\d|3[0-8]|4[02-9]|5[0135-9])|3(?:[0-3589]\\d|4[0-578]|6[1-9]|7[0-35-9])|7(?:[013-57-9]\\d|2[0-8]))\\d{3}|8(?:51(?:0(?:0[03-9]|[12479]\\d|3[2-9]|5[0-8]|6[1-9]|8[0-7])|1(?:[0235689]\\d|1[0-69]|4[0-589]|7[0-47-9])|2(?:0[0-79]|[18][13579]|2[14-9]|3[0-46-9]|[4-6]\\d|7[89]|9[0-4]))|(?:6[0-8]|[78]\\d)\\d{3}|9(?:[02-9]\\d{3}|1(?:(?:[0-58]\\d|6[0135-9])\\d|7(?:0[0-24-9]|[1-9]\\d)|9(?:[0-46-9]\\d|5[0-79])))))\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4(?:83[0-38]|93[0-6])\\d{5}|4(?:[0-3]\\d|4[047-9]|5[0-25-9]|6[06-9]|7[02-9]|8[0-24-9]|9[0-27-9])\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:163\\d{2,6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:180(?:0\\d{3}|2)\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:190[0-26]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									6,
									8,
									10,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:13(?:00\\d{6}(?:\\d{2})?|45[0-4]\\d{3})|13\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:14(?:5(?:1[0458]|[23][458])|71\\d)\\d{4})$/i',
						],
					],
				],
				'CC' => [
					'pattern' => '/^(?:1(?:[0-79]\\d{8}(?:\\d{2})?|8[0-24-9]\\d{7})|[148]\\d{8}|1\\d{5,7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [8],
							],
							'pattern' => '/^(?:8(?:51(?:0(?:02|31|60|89)|1(?:18|76)|223)|91(?:0(?:1[0-2]|29)|1(?:[28]2|50|79)|2(?:10|64)|3(?:[06]8|22)|4[29]8|62\\d|70[23]|959))\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4(?:83[0-38]|93[0-6])\\d{5}|4(?:[0-3]\\d|4[047-9]|5[0-25-9]|6[06-9]|7[02-9]|8[0-24-9]|9[0-27-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:180(?:0\\d{3}|2)\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:190[0-26]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									6,
									8,
									10,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:13(?:00\\d{6}(?:\\d{2})?|45[0-4]\\d{3})|13\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:14(?:5(?:1[0458]|[23][458])|71\\d)\\d{4})$/i',
						],
					],
				],
				'CX' => [
					'pattern' => '/^(?:1(?:[0-79]\\d{8}(?:\\d{2})?|8[0-24-9]\\d{7})|[148]\\d{8}|1\\d{5,7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [8],
							],
							'pattern' => '/^(?:8(?:51(?:0(?:01|30|59|88)|1(?:17|46|75)|2(?:22|35))|91(?:00[6-9]|1(?:[28]1|49|78)|2(?:09|63)|3(?:12|26|75)|4(?:56|97)|64\\d|7(?:0[01]|1[0-2])|958))\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4(?:83[0-38]|93[0-6])\\d{5}|4(?:[0-3]\\d|4[047-9]|5[0-25-9]|6[06-9]|7[02-9]|8[0-24-9]|9[0-27-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:180(?:0\\d{3}|2)\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:190[0-26]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									6,
									8,
									10,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:13(?:00\\d{6}(?:\\d{2})?|45[0-4]\\d{3})|13\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:14(?:5(?:1[0458]|[23][458])|71\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => 'AU',
		],
		'297' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[25-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'AW' => [
					'pattern' => '/^(?:(?:[25-79]\\d\\d|800)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:2\\d|8[1-9])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:290|5[69]\\d|6(?:[03]0|22|4[0-2]|[69]\\d)|7(?:[34]\\d|7[07])|9(?:6[45]|9[4-8]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:28\\d|501)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'358' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5}))$/i',
					'leadingDigits' => '/^(?:75[12])/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4,9}))$/i',
					'leadingDigits' => '/^(?:[2568][1-8]|3(?:0[1-9]|[1-9])|9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:11)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,7}))$/i',
					'leadingDigits' => '/^(?:[12]00|[368]|70[07-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4,8}))$/i',
					'leadingDigits' => '/^(?:[1245]|7[135])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6,10}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'AX' => [
					'pattern' => '/^(?:2\\d{4,9}|35\\d{4,5}|(?:60\\d\\d|800)\\d{4,6}|7\\d{5,11}|(?:[14]\\d|3[0-46-9]|50)\\d{4,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:18[1-8]\\d{3,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4946\\d{2,6}|(?:4[0-8]|50)\\d{4,8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4,6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[67]00\\d{5,6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20\\d{4,8}|60[12]\\d{5,6}|7(?:099\\d{4,5}|5[03-9]\\d{3,7})|20[2-59]\\d\\d|(?:606|7(?:0[78]|1|3\\d))\\d{7}|(?:10|29|3[09]|70[1-5]\\d)\\d{4,8})$/i',
						],
					],
				],
				'FI' => [
					'pattern' => '/^(?:[1-35689]\\d{4}|7\\d{10,11}|(?:[124-7]\\d|3[0-46-9])\\d{8}|[1-9]\\d{5,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[3-79][1-8]|[235689][1-8]\\d)\\d{2,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4946\\d{2,6}|(?:4[0-8]|50)\\d{4,8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4,6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[67]00\\d{5,6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20\\d{4,8}|60[12]\\d{5,6}|7(?:099\\d{4,5}|5[03-9]\\d{3,7})|20[2-59]\\d\\d|(?:606|7(?:0[78]|1|3\\d))\\d{7}|(?:10|29|3[09]|70[1-5]\\d)\\d{4,8})$/i',
						],
					],
				],
			],
			'main_country' => 'FI',
		],
		'994' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:90)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:1[28]|2|365(?:4|5[02])|46)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[13-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'AZ' => [
					'pattern' => '/^(?:365\\d{6}|(?:[124579]\\d|60|88)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:2[12]428|3655[02])\\d{4}|(?:2(?:22[0-79]|63[0-28])|3654)\\d{5}|(?:(?:1[28]|46)\\d|2(?:[014-6]2|[23]3))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:36554\\d{4}|(?:[16]0|4[04]|5[015]|7[07]|99)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:88\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900200\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'387' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:6[1-3]|[7-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[3-5]|6[56])/i',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BA' => [
					'pattern' => '/^(?:6\\d{8}|(?:[35689]\\d|49|70)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:(?:3(?:[05-79][2-9]|1[4579]|[23][24-9]|4[2-4689]|8[2457-9])|49[2-579]|5(?:0[2-49]|[13][2-9]|[268][2-4679]|4[4689]|5[2-79]|7[2-69]|9[2-4689]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6040\\d{5}|6(?:03|[1-356]|44|7\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[08]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[0246]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[12]\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:703[235]0\\d{3}|70(?:2[0-5]|3[0146]|[56]0)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'880' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:31[5-8]|[459]1)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,7}))$/i',
					'leadingDigits' => '/^(?:3(?:[67]|8[013-9])|4(?:6[168]|7|[89][18])|5(?:6[128]|9)|6(?:28|4[14]|5)|7[2-589]|8(?:0[014-9]|[12])|9[358]|(?:3[2-5]|4[235]|5[2-578]|6[0389]|76|8[3-7]|9[24])1|(?:44|66)[01346-9])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3,6}))$/i',
					'leadingDigits' => '/^(?:[13-9]|22)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{7,8}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1-$2',
				],
			],
			'countries' => [
				'BD' => [
					'pattern' => '/^(?:[1-469]\\d{9}|8[0-79]\\d{7,8}|[2-79]\\d{8}|[2-9]\\d{7}|[3-9]\\d{6}|[57-9]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4(?:31\\d\\d|423)|5222)\\d{3}(?:\\d{2})?|8332[6-9]\\d\\d|(?:3(?:03[56]|224)|4(?:22[25]|653))\\d{3,4}|(?:3(?:42[47]|529|823)|4(?:027|525|65(?:28|8))|562|6257|7(?:1(?:5[3-5]|6[12]|7[156]|89)|22[589]56|32|42675|52(?:[25689](?:56|8)|[347]8)|71(?:6[1267]|75|89)|92374)|82(?:2[59]|32)56|9(?:03[23]56|23(?:256|373)|31|5(?:1|2[4589]56)))\\d{3}|(?:3(?:02[348]|22[35]|324|422)|4(?:22[67]|32[236-9]|6(?:2[46]|5[57])|953)|5526|6(?:024|6655)|81)\\d{4,5}|(?:2(?:7(?:1[0-267]|2[0-289]|3[0-29]|4[01]|5[1-3]|6[013]|7[0178]|91)|8(?:0[125]|1[1-6]|2[0157-9]|3[1-69]|41|6[1-35]|7[1-5]|8[1-8]|9[0-6])|9(?:0[0-2]|1[0-4]|2[568]|3[3-6]|5[5-7]|6[0136-9]|7[0-7]|8[014-9]))|3(?:0(?:2[025-79]|3[2-4])|181|22[12]|32[2356]|824)|4(?:02[09]|22[348]|32[045]|523|6(?:27|54))|666(?:22|53)|7(?:22[57-9]|42[56]|82[35])8|8(?:0[124-9]|2(?:181|2[02-4679]8)|4[12]|[5-7]2)|9(?:[04]2|2(?:2|328)|81))\\d{4}|(?:2(?:222|[45]\\d)\\d|3(?:1(?:2[5-7]|[5-7])|425|822)|4(?:033|1\\d|[257]1|332|4(?:2[246]|5[25])|6(?:2[35]|56|62)|8(?:23|54)|92[2-5])|5(?:02[03489]|22[457]|32[35-79]|42[46]|6(?:[18]|53)|724|826)|6(?:023|2(?:2[2-5]|5[3-5]|8)|32[3478]|42[34]|52[47]|6(?:[18]|6(?:2[34]|5[24]))|[78]2[2-5]|92[2-6])|7(?:02|21\\d|[3-589]1|6[12]|72[24])|8(?:217|3[12]|[5-7]1)|9[24]1)\\d{5}|(?:(?:3[2-8]|5[2-57-9]|6[03-589])1|4[4689][18])\\d{5}|[59]1\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[13-9]\\d|644)\\d{7}|(?:3[78]|44|66)[02-9]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[03]\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:96(?:0[469]|1[0-47]|3[389]|6[69]|7[78])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'32' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:(?:80|9)0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[239]|4[23])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[15-8])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:4)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BE' => [
					'pattern' => '/^(?:4\\d{8}|[1-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[2-8]\\d{5}|(?:1[0-69]|[23][2-8]|4[23]|5\\d|6[013-57-9]|71|8[1-79]|9[2-4])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4[5-9]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800[1-9]\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:70(?:2[0-57]|3[04-7]|44|69|7[0579])|90(?:0[0-8]|1[36]|2[0-3568]|3[013-689]|[47][2-68]|5[1-68]|6[0-378]|9[34679]))\\d{4})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7879\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:78(?:0[57]|1[0458]|2[25]|3[15-8]|48|[56]0|7[078]|9\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'226' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[025-7])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BF' => [
					'pattern' => '/^(?:[025-7]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:0(?:49|5[23]|6[56]|9[016-9])|4(?:4[569]|5[4-6]|6[56]|7[0179])|5(?:[34]\\d|50|6[5-7]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0[1267]|5[1-8]|[67]\\d)\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'359' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d)(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:43[1-6]|70[1-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:[356]|4[124-7]|7[1-9]|8[1-6]|9[1-7])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:(?:70|8)0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:43[1-7]|7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[48]|9[08])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'BG' => [
					'pattern' => '/^(?:[2-7]\\d{6,7}|[89]\\d{6,8}|2\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
								],
								'localOnly' => [
									'4',
									'5',
								],
							],
							'pattern' => '/^(?:2\\d{5,7}|(?:43[1-6]|70[1-9])\\d{4,5}|(?:[36]\\d|4[124-7]|[57][1-9]|8[1-6]|9[1-7])\\d{5,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:43[07-9]\\d{5}|(?:48|8[7-9]\\d|9(?:8\\d|9[69]))\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'973' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[13679]|8[047])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'BH' => [
					'pattern' => '/^(?:[136-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:3[1356]|6[0156]|7\\d)\\d|6(?:1[16]\\d|500|6(?:0\\d|3[12]|44|7[7-9]|88)|9[69][69])|7(?:1(?:11|78)|7\\d\\d))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3(?:[1-79]\\d|8[0-47-9])\\d|6(?:3(?:00|33|6[16])|6(?:3[03-9]|[69]\\d|7[0-6])))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:87|9[014578])\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:84\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'257' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2367])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BI' => [
					'pattern' => '/^(?:(?:[267]\\d|31)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:22\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:29|31|6[1289]|7[125-9])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'229' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[25689])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BJ' => [
					'pattern' => '/^(?:[25689]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:02|1[037]|2[45]|3[68])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:5[1-35-8]|6\\d|9[013-9])\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:857[58]\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:81\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'590' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[569])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BL' => [
					'pattern' => '/^(?:(?:590|(?:69|80)\\d|976)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:590(?:2[7-9]|5[12]|87)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:69(?:0\\d\\d|1(?:2[2-9]|3[0-5]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:976[01]\\d{5})$/i',
						],
					],
				],
				'GP' => [
					'pattern' => '/^(?:(?:590|(?:69|80)\\d|976)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:590(?:0[1-68]|1[0-2]|2[0-68]|3[1289]|4[0-24-9]|5[3-579]|6[0189]|7[08]|8[0-689]|9\\d)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:69(?:0\\d\\d|1(?:2[2-9]|3[0-5]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:976[01]\\d{5})$/i',
						],
					],
				],
				'MF' => [
					'pattern' => '/^(?:(?:590|(?:69|80)\\d|976)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:590(?:0[079]|[14]3|[27][79]|30|5[0-268]|87)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:69(?:0\\d\\d|1(?:2[2-9]|3[0-5]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:976[01]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => 'GP',
		],
		'673' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-578])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'BN' => [
					'pattern' => '/^(?:[2-578]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:22[0-7]\\d{4}|(?:2[013-9]|[34]\\d|5[0-25-9])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:22[89]|[78]\\d\\d)\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[34]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'591' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[23]|4[46])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{8}))$/i',
					'leadingDigits' => '/^(?:[67])/i',
					'format' => '$1',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'BO' => [
					'pattern' => '/^(?:(?:[2-467]\\d\\d|8001)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:2(?:2\\d\\d|5(?:11|[258]\\d|9[67])|6(?:12|2\\d|9[34])|8(?:2[34]|39|62))|3(?:3\\d\\d|4(?:6\\d|8[24])|8(?:25|42|5[257]|86|9[25])|9(?:[27]\\d|3[2-4]|4[248]|5[24]|6[2-6]))|4(?:4\\d\\d|6(?:11|[24689]\\d|72)))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[67]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8001[07]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'599' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[3467])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9[4-8])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'BQ' => [
					'pattern' => '/^(?:(?:[34]1|7\\d)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:318[023]|41(?:6[023]|70)|7(?:1[578]|2[05]|50)\\d)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:31(?:8[14-8]|9[14578])|416[14-9]|7(?:0[01]|7[07]|8\\d|9[056])\\d)\\d{3})$/i',
						],
					],
				],
				'CW' => [
					'pattern' => '/^(?:(?:[34]1|60|(?:7|9\\d)\\d)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:4(?:3[0-5]|4[14]|6\\d)|50\\d|7(?:2[014]|3[02-9]|4[4-9]|6[357]|77|8[7-9])|8(?:3[39]|[46]\\d|7[01]|8[57-9]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:953[01]\\d{4}|9(?:5[12467]|6[5-9])\\d{5})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:955\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:60[0-2]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => 'CW',
		],
		'55' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3,6}))$/i',
					'leadingDigits' => '/^(?:1(?:1[25-8]|2[357-9]|3[02-68]|4[12568]|5|6[0-8]|8[015]|9[0-47-9])|321|610)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:4(?:02|37)0|[34]00)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2357]|4(?:[0-24-9]|3(?:[0-689]|7[1-9])))/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2,3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:(?:[358]|90)0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:(?:[14689][1-9]|2[12478]|3[1-578]|5[13-5]|7[13-579])[2-57])/i',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[16][1-9]|[2-57-9])/i',
					'format' => '$1 $2-$3',
				],
			],
			'countries' => [
				'BR' => [
					'pattern' => '/^(?:(?:[1-46-9]\\d\\d|5(?:[0-46-9]\\d|5[0-24679]))\\d{8}|[1-9]\\d{9}|[3589]\\d{8}|[34]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [8],
							],
							'pattern' => '/^(?:(?:[14689][1-9]|2[12478]|3[1-578]|5[13-5]|7[13-579])[2-5]\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [
									'8',
									'9',
								],
							],
							'pattern' => '/^(?:(?:[14689][1-9]|2[12478]|3[1-578]|5[13-5]|7[13-579])(?:7|9\\d)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6,7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:300\\d{6}|[59]00\\d{6,7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									8,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:300\\d{7}|[34]00\\d{5}|4(?:02|37)0\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'975' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-68]|7[246])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:1[67]|7)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'BT' => [
					'pattern' => '/^(?:[17]\\d{7}|[2-8]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:(?:2[3-6]|[34][5-7]|5[236]|6[2-46]|7[246]|8[2-4])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[67]|77)\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'267' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:90)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[24-6]|3[15-79])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[37])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'BW' => [
					'pattern' => '/^(?:(?:0800|(?:[37]|800)\\d)\\d{6}|(?:[2-6]\\d|90)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:4[0-48]|6[0-24]|9[0578])|3(?:1[0-35-9]|55|[69]\\d|7[013])|4(?:6[03]|7[1267]|9[0-5])|5(?:3[03489]|4[0489]|7[1-47]|88|9[0-49])|6(?:2[1-35]|5[149]|8[067]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:321|7(?:[1-7]\\d|8[01]))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0800|800\\d)\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:79(?:1(?:[01]\\d|20)|2[0-2]\\d)\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'375' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:800)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2,4}))$/i',
					'leadingDigits' => '/^(?:800)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1(?:5[169]|6(?:3[1-3]|4|5[125])|7(?:1[3-9]|7[0-24-6]|9[2-7]))|2(?:1[35]|2[34]|3[3-5]))/i',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:1(?:[56]|7[467])|2[1-3])/i',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[1-4])/i',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'BY' => [
					'pattern' => '/^(?:(?:[12]\\d|33|44|902)\\d{7}|8(?:0[0-79]\\d{5,7}|[1-7]\\d{9})|8(?:1[0-489]|[5-79]\\d)\\d{7}|8[1-79]\\d{6,7}|8[0-79]\\d{5}|8\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [
									5,
									6,
									7,
								],
							],
							'pattern' => '/^(?:(?:1(?:5(?:1[1-5]|[24]\\d|6[2-4]|9[1-7])|6(?:[235]\\d|4[1-7])|7\\d\\d)|2(?:1(?:[246]\\d|3[0-35-9]|5[1-9])|2(?:[235]\\d|4[0-8])|3(?:[26]\\d|3[02-79]|4[024-7]|5[03-7])))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:5[5-79]|9[1-9])|(?:33|44)\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{3,7}|8(?:0[13]|20\\d)\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:810|902)\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:249\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'501' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-8])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1-$2-$3-$4',
				],
			],
			'countries' => [
				'BZ' => [
					'pattern' => '/^(?:(?:0800\\d|[2-8])\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:[02]\\d|36|[68]0)|[3-58](?:[02]\\d|[68]0)|7(?:[02]\\d|32|[68]0))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[0-35-7]\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0800\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'243' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:88)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[1-6])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'CD' => [
					'pattern' => '/^(?:[189]\\d{8}|[1-68]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:12\\d{7}|[1-6]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:88\\d{5}|(?:8[0-59]|9[017-9])\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'236' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[278])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'CF' => [
					'pattern' => '/^(?:(?:[27]\\d{3}|8776)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[12]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[0257]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8776\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'242' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[02])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'CG' => [
					'pattern' => '/^(?:(?:0\\d\\d|222|800)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:222[1-589]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:026(?:1[0-5]|6[6-9])\\d{4}|0(?:[14-6]\\d\\d|2(?:40|5[5-8]|6[07-9]))\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'41' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8[047]|90)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2-79]|81)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
			'countries' => [
				'CH' => [
					'pattern' => '/^(?:8\\d{11}|[2-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[12467]|3[1-4]|4[134]|5[256]|6[12]|[7-9]1)\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[35-9]\\d{7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:74[0248]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[016]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:84[0248]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:878\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[18]\\d{7})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [12],
								'localOnly' => [],
							],
							'pattern' => '/^(?:860\\d{9})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'225' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d)(\\d{5}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'CI' => [
					'pattern' => '/^(?:[02]\\d{9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[15]\\d{3}|7(?:2(?:0[23]|1[2357]|[23][45]|4[3-5])|3(?:06|1[69]|[2-6]7)))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0704[0-7]\\d{5}|0(?:[15]\\d\\d|7(?:0[0-37-9]|[4-9][7-9]))\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'682' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-578])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'CK' => [
					'pattern' => '/^(?:[2-578]\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2\\d|3[13-7]|4[1-5])\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[578]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'56' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1(?:[03-589]|21)|[29]0|78)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2196)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:44)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2[1-3])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9[2-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:3[2-5]|[47]|5[1-3578]|6[13-57]|8(?:0[1-9]|[1-9]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:60|8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:60)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'CL' => [
					'pattern' => '/^(?:12300\\d{6}|6\\d{9,10}|[2-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:1982[0-6]|3314[05-9])\\d{3}|(?:2(?:1(?:160|962)|3(?:2\\d\\d|3(?:[034]\\d|1[0-35-9]|2[1-9]|5[0-2])|600))|80[1-9]\\d\\d|9(?:3(?:[0-57-9]\\d\\d|6(?:0[02-9]|[1-9]\\d))|6(?:[0-8]\\d\\d|9(?:[02-79]\\d|1[05-9]))|7[1-9]\\d\\d|9(?:[03-9]\\d\\d|1(?:[0235-9]\\d|4[0-24-9])|2(?:[0-79]\\d|8[0-46-9]))))\\d{4}|(?:22|3[2-5]|[47][1-35]|5[1-3578]|6[13-57]|8[1-9]|9[2458])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:1982[0-6]|3314[05-9])\\d{3}|(?:2(?:1(?:160|962)|3(?:2\\d\\d|3(?:[034]\\d|1[0-35-9]|2[1-9]|5[0-2])|600))|80[1-9]\\d\\d|9(?:3(?:[0-57-9]\\d\\d|6(?:0[02-9]|[1-9]\\d))|6(?:[0-8]\\d\\d|9(?:[02-79]\\d|1[05-9]))|7[1-9]\\d\\d|9(?:[03-9]\\d\\d|1(?:[0235-9]\\d|4[0-24-9])|2(?:[0-79]\\d|8[0-46-9]))))\\d{4}|(?:22|3[2-5]|[47][1-35]|5[1-3578]|6[13-57]|8[1-9]|9[2458])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:123|8)00\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:600\\d{7,8})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:44\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'237' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:88)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[26]|88)/i',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
			'countries' => [
				'CM' => [
					'pattern' => '/^(?:[26]\\d{8}|88\\d{6,7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:22|33)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:24[23]|6[5-9]\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:88\\d{6,7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'86' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:96)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:(?:10|2[0-57-9])(?:100|9[56]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1[1-9]|26|[3-9]|(?:10|2[0-57-9])(?:[02-8]|1(?:0[1-9]|[1-9])|9[0-47-9]))/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:16[08])/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:85[23](?:100|95)|(?:3(?:[157]\\d|35|49|9[1-68])|4(?:[17]\\d|2[179]|[35][1-9]|6[47-9]|8[23])|5(?:[1357]\\d|2[37]|4[36]|6[1-46]|80|9[1-9])|6(?:3[1-5]|6[0238]|9[12])|7(?:01|[1579]\\d|2[248]|3[014-9]|4[3-6]|6[023689])|8(?:1[236-8]|2[5-7]|[37]\\d|5[14-9]|8[36-8]|9[1-8])|9(?:0[1-3689]|1[1-79]|[379]\\d|4[13]|5[1-5]))(?:100|9[56]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:26|3(?:[0268]|3[0-46-9]|4[0-8]|9[079])|4(?:[049]|2[02-68]|[35]0|6[0-356]|8[014-9])|5(?:0|2[0-24-689]|4[0-2457-9]|6[057-9]|90)|6(?:[0-24578]|3[06-9]|6[14-79]|9[03-9])|7(?:0[02-9]|2[0135-79]|3[23]|4[0-27-9]|6[1457]|8)|8(?:[046]|1[01459]|2[0-489]|5(?:0|[23](?:[02-8]|1[1-9]|9[0-46-9]))|8[0-2459]|9[09])|9(?:0[0457]|1[08]|[268]|4[024-9]|5[06-9])|(?:1|58|85[23]10)[1-9]|(?:10|2[0-57-9])(?:[0-8]|9[0-47-9])|(?:3(?:[157]\\d|35|49|9[1-68])|4(?:[17]\\d|2[179]|[35][1-9]|6[47-9]|8[23])|5(?:[1357]\\d|2[37]|4[36]|6[1-46]|80|9[1-9])|6(?:3[1-5]|6[0238]|9[12])|7(?:01|[1579]\\d|2[248]|3[014-9]|4[3-6]|6[023689])|8(?:1[236-8]|2[5-7]|[37]\\d|5[14-9]|8[36-8]|9[1-8])|9(?:0[1-3689]|1[1-79]|[379]\\d|4[13]|5[1-5]))(?:[02-8]|1(?:0[1-9]|[1-9])|9[0-47-9]))/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:(?:4|80)0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:10[0-79]|2(?:[02-57-9]|1[1-79])|(?:10|21)8(?:0[1-9]|[1-9]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:3(?:[3-59]|7[02-68])|4(?:[26-8]|3[3-9]|5[2-9])|5(?:3[03-9]|[468]|7[028]|9[2-46-9])|6|7(?:[0-247]|3[04-9]|5[0-4689]|6[2368])|8(?:[1-358]|9[1-7])|9(?:[013479]|5[1-5])|(?:[34]1|55|79|87)[02-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7,8}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:80)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[3-578])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1[3-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[12])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'CN' => [
					'pattern' => '/^(?:1[127]\\d{8,9}|2\\d{9}(?:\\d{2})?|[12]\\d{6,7}|86\\d{6}|(?:1[03-689]\\d|6)\\d{7,9}|(?:[3-579]\\d|8[0-57-9])\\d{6,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:(?:10(?:[02-79]\\d\\d|[18](?:0[1-9]|[1-9]\\d))|21(?:[18](?:0[1-9]|[1-9]\\d)|[2-79]\\d\\d))\\d{5}|(?:43[35]|754)\\d{7,8}|8(?:078\\d{7}|51\\d{7,8})|(?:10|(?:2|85)1|43[35]|754)(?:100\\d\\d|95\\d{3,4})|(?:2[02-57-9]|3(?:11|7[179])|4(?:[15]1|3[12])|5(?:1\\d|2[37]|3[12]|51|7[13-79]|9[15])|7(?:[39]1|5[57]|6[09])|8(?:71|98))(?:[02-8]\\d{7}|1(?:0(?:0\\d\\d(?:\\d{3})?|[1-9]\\d{5})|[1-9]\\d{6})|9(?:[0-46-9]\\d{6}|5\\d{3}(?:\\d(?:\\d{2})?)?))|(?:3(?:1[02-9]|35|49|5\\d|7[02-68]|9[1-68])|4(?:1[02-9]|2[179]|3[46-9]|5[2-9]|6[47-9]|7\\d|8[23])|5(?:3[03-9]|4[36]|5[02-9]|6[1-46]|7[028]|80|9[2-46-9])|6(?:3[1-5]|6[0238]|9[12])|7(?:01|[17]\\d|2[248]|3[04-9]|4[3-6]|5[0-3689]|6[2368]|9[02-9])|8(?:1[236-8]|2[5-7]|3\\d|5[2-9]|7[02-9]|8[36-8]|9[1-7])|9(?:0[1-3689]|1[1-79]|[379]\\d|4[13]|5[1-5]))(?:[02-8]\\d{6}|1(?:0(?:0\\d\\d(?:\\d{2})?|[1-9]\\d{4})|[1-9]\\d{5})|9(?:[0-46-9]\\d{5}|5\\d{3,5})))$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1740[0-5]\\d{6}|1(?:[38]\\d|4[57]|5[0-35-9]|6[25-7]|7[0-35-8]|9[0135-9])\\d{8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:10|21)8|8)00\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:16[08]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:400\\d{7}|950\\d{7,8}|(?:10|2[0-57-9]|3(?:[157]\\d|35|49|9[1-68])|4(?:[17]\\d|2[179]|[35][1-9]|6[47-9]|8[23])|5(?:[1357]\\d|2[37]|4[36]|6[1-46]|80|9[1-9])|6(?:3[1-5]|6[0238]|9[12])|7(?:01|[1579]\\d|2[248]|3[014-9]|4[3-6]|6[023689])|8(?:1[236-8]|2[5-7]|[37]\\d|5[14-9]|8[36-8]|9[1-8])|9(?:0[1-3689]|1[1-79]|[379]\\d|4[13]|5[1-5]))96\\d{3,4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'57' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[146][2-9]|[2578])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[39])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => '$1 $2 $3',
				],
			],
			'countries' => [
				'CO' => [
					'pattern' => '/^(?:(?:(?:1\\d|[36])\\d{3}|9101)\\d{6}|[124-8]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									10,
								],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:60[124-8][2-9]\\d{6}|[124-8][2-9]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3333(?:0(?:0\\d|1[0-5])|[4-9]\\d\\d)\\d{3}|(?:3(?:24[2-6]|3(?:00|3[0-24-9]))|9101)\\d{6}|3(?:0[0-5]|1\\d|2[0-3]|5[01]|70)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:19(?:0[01]|4[78])\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'506' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7]|8[3-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1-$2-$3',
				],
			],
			'countries' => [
				'CR' => [
					'pattern' => '/^(?:(?:8\\d|90)\\d{8}|(?:[24-8]\\d{3}|3005)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:210[7-9]\\d{4}|2(?:[024-7]\\d|1[1-9])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3005\\d|6500[01])\\d{3}|(?:5[07]|6[0-4]|7[0-3]|8[3-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[059]\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:210[0-6]|4\\d{3}|5100)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'53' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:2[1-4]|[34])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{6,7}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{7}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'CU' => [
					'pattern' => '/^(?:[27]\\d{6,7}|[34]\\d{5,7}|(?:5|8\\d\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									10,
								],
								'localOnly' => [
									'4',
									'5',
								],
							],
							'pattern' => '/^(?:(?:3[23]|48)\\d{4,6}|(?:31|4[36]|8(?:0[25]|78)\\d)\\d{6}|(?:2[1-4]|4[1257]|7\\d)\\d{5,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:807\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'238' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2-589])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'CV' => [
					'pattern' => '/^(?:(?:[2-59]\\d\\d|800)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:2[1-7]|3[0-8]|4[12]|5[1256]|6\\d|7[1-3]|8[1-5])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[34][36]|5[1-389]|9\\d)\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'357' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[257-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'CY' => [
					'pattern' => '/^(?:(?:[279]\\d|[58]0)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[2-6]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[4-79]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[09]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[1-9]\\d{5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:50|77)\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'420' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-8]|9[015-7])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:96)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'CZ' => [
					'pattern' => '/^(?:(?:[2-578]\\d|60)\\d{7}|9\\d{8,11})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2\\d|3[1257-9]|4[16-9]|5[13-9])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:60[1-8]|7(?:0[2-5]|[2379]\\d))\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:0[05689]|76)\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[134]\\d{7})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70[01]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[17]0\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:5\\d|7[2-4])\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:3\\d{9}|6\\d{7,10}))$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'49' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,13}))$/i',
					'leadingDigits' => '/^(?:3[02]|40|[68]9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,12}))$/i',
					'leadingDigits' => '/^(?:2(?:0[1-389]|12[0-8])|3(?:[35-9][15]|4[015])|906|2(?:[13][14]|2[18])|(?:2[4-9]|4[2-9]|[579][1-9]|[68][1-8])1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2,11}))$/i',
					'leadingDigits' => '/^(?:[24-6]|3(?:3(?:0[1-467]|2[127-9]|3[124578]|7[1257-9]|8[1256]|9[145])|4(?:2[135]|4[13578]|9[1346])|5(?:0[14]|2[1-3589]|6[1-4]|7[13468]|8[13568])|6(?:2[1-489]|3[124-6]|6[13]|7[12579]|8[1-356]|9[135])|7(?:2[1-7]|4[145]|6[1-5]|7[1-4])|8(?:21|3[1468]|6|7[1467]|8[136])|9(?:0[12479]|2[1358]|4[134679]|6[1-9]|7[136]|8[147]|9[1468]))|70[2-8]|8(?:0[2-9]|[1-8])|90[7-9]|[79][1-9]|3[68]4[1347]|3(?:47|60)[1356]|3(?:3[46]|46|5[49])[1246]|3[4579]3[1357])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:138)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{2,10}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5,11}))$/i',
					'leadingDigits' => '/^(?:181)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d)(\\d{4,10}))$/i',
					'leadingDigits' => '/^(?:1(?:3|80)|9)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7,8}))$/i',
					'leadingDigits' => '/^(?:1[67])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7,12}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:18500)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:18[68])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:15[0568])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:15[1279])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{8}))$/i',
					'leadingDigits' => '/^(?:18)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{7,8}))$/i',
					'leadingDigits' => '/^(?:1(?:6[023]|7))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:15[279])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{8}))$/i',
					'leadingDigits' => '/^(?:15)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'DE' => [
					'pattern' => '/^(?:[2579]\\d{5,14}|49(?:[34]0|69|8\\d)\\d\\d?|49(?:37|49|60|7[089]|9\\d)\\d{1,3}|49(?:1\\d|2[02-9]|3[2-689]|7[1-7])\\d{1,8}|(?:1|[368]\\d|4[0-8])\\d{3,13}|49(?:[05]\\d|[23]1|[46][1-8])\\d{1,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
									13,
									14,
									15,
								],
								'localOnly' => [
									2,
									3,
									4,
								],
							],
							'pattern' => '/^(?:32\\d{9,11}|49[2-6]\\d{10}|49[0-7]\\d{3,9}|(?:[34]0|[68]9)\\d{3,13}|(?:2(?:0[1-689]|[1-3569]\\d|4[0-8]|7[1-7]|8[0-7])|3(?:[3569]\\d|4[0-79]|7[1-7]|8[1-8])|4(?:1[02-9]|[2-48]\\d|5[0-6]|6[0-8]|7[0-79])|5(?:0[2-8]|[124-6]\\d|[38][0-8]|[79][0-7])|6(?:0[02-9]|[1-358]\\d|[47][0-8]|6[1-9])|7(?:0[2-8]|1[1-9]|[27][0-7]|3\\d|[4-6][0-8]|8[0-5]|9[013-7])|8(?:0[2-9]|1[0-79]|2\\d|3[0-46-9]|4[0-6]|5[013-9]|6[1-8]|7[0-8]|8[0-24-6])|9(?:0[6-9]|[1-4]\\d|[589][0-7]|6[0-8]|7[0-467]))\\d{3,12})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:15[0-25-9]\\d{8}|1(?:6[023]|7\\d)\\d{7,8})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [
									4,
									5,
									6,
									7,
									8,
									9,
									10,
									11,
									12,
									13,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:16(?:4\\d{1,10}|[89]\\d{1,11}))$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									11,
									12,
									13,
									14,
									15,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7,12})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:137[7-9]|900(?:[135]|9\\d))\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
									12,
									13,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:180\\d{5,11}|13(?:7[1-6]\\d\\d|8)\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
									11,
									12,
									13,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:18(?:1\\d{5,11}|[2-9]\\d{8}))$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:6(?:013|255|399)|7(?:(?:[015]1|[69]3)3|[2-4]55|[78]99))\\d{7,8}|15(?:(?:[03-68]00|113)\\d|2\\d55|7\\d99|9\\d33)\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'253' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[27])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'DJ' => [
					'pattern' => '/^(?:(?:2\\d|77)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:1[2-5]|7[45])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:77\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'45' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'DK' => [
					'pattern' => '/^(?:[2-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[2-7]\\d|8[126-9]|9[1-46-9])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[2-7]\\d|8[126-9]|9[1-46-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'213' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[1-4])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[5-8])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'DZ' => [
					'pattern' => '/^(?:(?:[1-4]|[5-79]\\d|80)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9619\\d{5}|(?:1\\d|2[013-79]|3[0-8]|4[0135689])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:5(?:4[0-29]|5\\d|6[0-2])|6(?:[569]\\d|7[0-6])|7[7-9]\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[3-689]1\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[12]1\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:98[23]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'593' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2-$3',
					'intlFormat' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'EC' => [
					'pattern' => '/^(?:1\\d{9,10}|(?:[2-7]|9\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:[2-7][2-7]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:964[0-2]\\d{5}|9(?:39|[57][89]|6[0-36-9]|[89]\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{7}|1[78]00\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[2-7]890\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'372' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[369]|4[3-8]|5(?:[02]|1(?:[0-8]|95)|5[0-478]|6(?:4[0-4]|5[1-589]))|7[1-9]|88)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[45]|8(?:00[1-9]|[1-49]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'EE' => [
					'pattern' => '/^(?:8\\d{9}|[4578]\\d{7}|(?:[3-8]\\d|90)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3[23589]|4[3-8]|6\\d|7[1-9]|88)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:[0-35-9]\\d{6}|4(?:[0-57-9]\\d{5}|6(?:[0-24-9]\\d{4}|3(?:[0-35-9]\\d{3}|4000))))|8(?:1(?:0(?:000|[3-9]\\d\\d)|(?:1(?:0[236]|1\\d)|(?:23|[3-79]\\d)\\d)\\d)|2(?:0(?:000|(?:19|[24-7]\\d)\\d)|(?:(?:[124-6]\\d|3[5-9])\\d|7(?:[679]\\d|8[13-9])|8(?:[2-6]\\d|7[01]))\\d)|[349]\\d{4})\\d\\d|5(?:(?:[02]\\d|5[0-478])\\d|1(?:[0-8]\\d|95)|6(?:4[0-4]|5[1-589]))\\d{3})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800(?:(?:0\\d\\d|1)\\d|[2-9])\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:40\\d\\d|900)\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70[0-2]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'20' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{7,8}))$/i',
					'leadingDigits' => '/^(?:[23])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6,7}))$/i',
					'leadingDigits' => '/^(?:1[35]|[4-6]|8[2468]|9[235-7])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[189])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'EG' => [
					'pattern' => '/^(?:[189]\\d{8,9}|[24-6]\\d{8}|[135]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:13[23]\\d{6}|(?:15|57)\\d{6,7}|(?:2[2-4]|3|4[05-8]|5[05]|6[24-689]|8[2468]|9[235-7])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1[0-25]\\d{8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'212' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:5(?:29|38)[89]0)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:5[45])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:5(?:2(?:[2-49]|8[235-9])|3[5-9]|9)|892)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[5-7])/i',
					'format' => '$1-$2',
				],
			],
			'countries' => [
				'EH' => [
					'pattern' => '/^(?:[5-8]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:528[89]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6(?:[0-79]\\d|8[0-247-9])|7(?:0\\d|1[0-5]|6[1267]|7[0-57]))\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:89\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:592(?:4[0-2]|93)\\d{4})$/i',
						],
					],
				],
				'MA' => [
					'pattern' => '/^(?:[5-8]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:29(?:[189][05]|2[29]|3[01])|38[89][05])\\d{4}|5(?:2(?:[0-25-7]\\d|3[1-578]|4[02-46-8]|8[0235-7]|90)|3(?:[0-47]\\d|5[02-9]|6[02-8]|80|9[3-9])|(?:4[067]|5[03])\\d)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6(?:[0-79]\\d|8[0-247-9])|7(?:0\\d|1[0-5]|6[1267]|7[0-57]))\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:89\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:592(?:4[0-2]|93)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => 'MA',
		],
		'291' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[178])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'ER' => [
					'pattern' => '/^(?:[178]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:(?:1(?:1[12568]|[24]0|55|6[146])|8\\d\\d)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:17[1-3]|7\\d\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'34' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4}))$/i',
					'leadingDigits' => '/^(?:905)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[79]9)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[89]00)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[5-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'ES' => [
					'pattern' => '/^(?:[5-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:96906(?:0[0-8]|1[1-9]|[2-9]\\d)\\d\\d|9(?:69(?:0[0-57-9]|[1-9]\\d)|73(?:[0-8]\\d|9[1-9]))\\d{4}|(?:8(?:[1356]\\d|[28][0-8]|[47][1-9])|9(?:[135]\\d|[268][0-8]|4[1-9]|7[124-9]))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:590[16]00\\d|9(?:6906(?:09|10)|7390\\d\\d))\\d\\d|(?:6\\d|7[1-48])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[89]00\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[367]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[12]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:51\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'251' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-59])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'ET' => [
					'pattern' => '/^(?:(?:11|[2-59]\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:11667[01]\\d{3}|(?:11(?:1(?:1[124]|2[2-7]|3[1-5]|5[5-8]|8[6-8])|2(?:13|3[6-8]|5[89]|7[05-9]|8[2-6])|3(?:2[01]|3[0-289]|4[1289]|7[1-4]|87)|4(?:1[69]|3[2-49]|4[0-3]|6[5-8])|5(?:1[578]|44|5[0-4])|6(?:1[78]|2[69]|39|4[5-7]|5[1-5]|6[0-59]|8[015-8]))|2(?:2(?:11[1-9]|22[0-7]|33\\d|44[1467]|66[1-68])|5(?:11[124-6]|33[2-8]|44[1467]|55[14]|66[1-3679]|77[124-79]|880))|3(?:3(?:11[0-46-8]|(?:22|55)[0-6]|33[0134689]|44[04]|66[01467])|4(?:44[0-8]|55[0-69]|66[0-3]|77[1-5]))|4(?:6(?:119|22[0-24-7]|33[1-5]|44[13-69]|55[14-689]|660|88[1-4])|7(?:(?:11|22)[1-9]|33[13-7]|44[13-6]|55[1-689]))|5(?:7(?:227|55[05]|(?:66|77)[14-8])|8(?:11[149]|22[013-79]|33[0-68]|44[013-8]|550|66[1-5]|77\\d)))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9\\d{8})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'679' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[235-9]|45)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'FJ' => [
					'pattern' => '/^(?:45\\d{5}|(?:0800\\d|[235-9])\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:603\\d{4}|(?:3[0-5]|6[25-7]|8[58])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[279]\\d|45|5[01568]|8[034679])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0800\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'500' => [
			'formats' => [],
			'countries' => [
				'FK' => [
					'pattern' => '/^(?:[2-7]\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[2-47]\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[56]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'691' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[389])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'FM' => [
					'pattern' => '/^(?:(?:[39]\\d\\d|820)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:31(?:00[67]|208|309)\\d\\d|(?:3(?:[2357]0[1-9]|602|804|905)|(?:820|9[2-6]\\d)\\d)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:31(?:00[67]|208|309)\\d\\d|(?:3(?:[2357]0[1-9]|602|804|905)|(?:820|9[2-7]\\d)\\d)\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'298' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1',
				],
			],
			'countries' => [
				'FO' => [
					'pattern' => '/^(?:[2-9]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:20|[34]\\d|8[19])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[27][1-9]|5\\d|91)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[257-9]\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90(?:[13-5][15-7]|2[125-7]|9\\d)\\d\\d)$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6[0-36]|88)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'33' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4}))$/i',
					'leadingDigits' => '/^(?:10)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[1-79])/i',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
			'countries' => [
				'FR' => [
					'pattern' => '/^(?:[1-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[1-35]\\d|4[1-9])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6(?:[0-24-8]\\d|3[0-8]|9[589])|7(?:00|[3-9]\\d))\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:836(?:0[0-36-9]|[1-9]\\d)\\d{4}|8(?:1[2-9]|2[2-47-9]|3[0-57-9]|[569]\\d|8[0-35-9])\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:1[01]|2[0156]|84)\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[6-9]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'241' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:11|[67])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'GA' => [
					'pattern' => '/^(?:(?:[067]\\d|11)\\d{6}|[2-7]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[01]1\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:0[2-7]\\d|6(?:0[0-4]|10|[256]\\d))\\d|7(?:[47]\\d\\d|658))\\d{4}|[2-7]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'44' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8001111)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:845464)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:800)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:1(?:3873|5(?:242|39[4-6])|(?:697|768)[347]|9467))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:1(?:[2-69][02-9]|[78]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[25]|7(?:0|6(?:[03-9]|2[356])))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1389])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'GB' => [
					'pattern' => '/^(?:[1-357-9]\\d{9}|[18]\\d{8}|8\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [
									4,
									5,
									6,
									7,
									8,
								],
							],
							'pattern' => '/^(?:(?:1(?:1(?:3(?:[0-58]\\d\\d|73[0235])|4(?:[0-5]\\d\\d|69[7-9]|70[0359])|(?:5[0-26-9]|[78][0-49])\\d\\d|6(?:[0-4]\\d\\d|50[0-24-69]))|2(?:(?:0[024-9]|2[3-9]|3[3-79]|4[1-689]|[58][02-9]|6[0-47-9]|7[013-9]|9\\d)\\d\\d|1(?:[0-7]\\d\\d|8(?:[02]\\d|1[0-278])))|(?:3(?:0\\d|1[0-8]|[25][02-9]|3[02-579]|[468][0-46-9]|7[1-35-79]|9[2-578])|4(?:0[03-9]|[137]\\d|[28][02-57-9]|4[02-69]|5[0-8]|[69][0-79])|5(?:0[1-35-9]|[16]\\d|2[024-9]|3[015689]|4[02-9]|5[03-9]|7[0-35-9]|8[0-468]|9[0-57-9])|6(?:0[034689]|1\\d|2[0-35689]|[38][013-9]|4[1-467]|5[0-69]|6[13-9]|7[0-8]|9[0-24578])|7(?:0[0246-9]|2\\d|3[0236-8]|4[03-9]|5[0-46-9]|6[013-9]|7[0-35-9]|8[024-9]|9[02-9])|8(?:0[35-9]|2[1-57-9]|3[02-578]|4[0-578]|5[124-9]|6[2-69]|7\\d|8[02-9]|9[02569])|9(?:0[02-589]|[18]\\d|2[02-689]|3[1-57-9]|4[2-9]|5[0-579]|6[2-47-9]|7[0-24578]|9[2-57]))\\d\\d)|2(?:0[013478]|3[0189]|4[017]|8[0-46-9]|9[0-2])\\d{3})\\d{4}|1(?:2(?:0(?:46[1-4]|87[2-9])|545[1-79]|76(?:2\\d|3[1-8]|6[1-6])|9(?:7(?:2[0-4]|3[2-5])|8(?:2[2-8]|7[0-47-9]|8[3-5])))|3(?:6(?:38[2-5]|47[23])|8(?:47[04-9]|64[0157-9]))|4(?:044[1-7]|20(?:2[23]|8\\d)|6(?:0(?:30|5[2-57]|6[1-8]|7[2-8])|140)|8(?:052|87[1-3]))|5(?:2(?:4(?:3[2-79]|6\\d)|76\\d)|6(?:26[06-9]|686))|6(?:06(?:4\\d|7[4-79])|295[5-7]|35[34]\\d|47(?:24|61)|59(?:5[08]|6[67]|74)|9(?:55[0-4]|77[23]))|7(?:26(?:6[13-9]|7[0-7])|(?:442|688)\\d|50(?:2[0-3]|[3-68]2|76))|8(?:27[56]\\d|37(?:5[2-5]|8[239])|843[2-58])|9(?:0(?:0(?:6[1-8]|85)|52\\d)|3583|4(?:66[1-8]|9(?:2[01]|81))|63(?:23|3[1-4])|9561))\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:457[0-57-9]|700[01]|911[028])\\d{5}|7(?:[1-3]\\d\\d|4(?:[0-46-9]\\d|5[0-689])|5(?:0[0-8]|[13-9]\\d|2[0-35-9])|7(?:0[1-9]|[1-7]\\d|8[02-9]|9[0-689])|8(?:[014-9]\\d|[23][0-8])|9(?:[024-9]\\d|1[02-9]|3[0-689]))\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:76(?:464|652)\\d{5}|76(?:0[0-2]|2[356]|34|4[01347]|5[49]|6[0-369]|77|81|9[139])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[08]\\d{7}|800\\d{6}|8001111)$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:8(?:4[2-5]|7[0-3])|9(?:[01]\\d|8[2-49]))\\d{7}|845464\\d)$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{8})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:56\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3[0347]|55)\\d{8})$/i',
						],
					],
				],
				'GG' => [
					'pattern' => '/^(?:(?:1481|[357-9]\\d{3})\\d{6}|8\\d{6}(?:\\d{2})?)$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:1481[25-9]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:(?:781|839)\\d|911[17])\\d{5})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:76(?:464|652)\\d{5}|76(?:0[0-2]|2[356]|34|4[01347]|5[49]|6[0-369]|77|81|9[139])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[08]\\d{7}|800\\d{6}|8001111)$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:8(?:4[2-5]|7[0-3])|9(?:[01]\\d|8[0-3]))\\d{7}|845464\\d)$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{8})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:56\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3[0347]|55)\\d{8})$/i',
						],
					],
				],
				'IM' => [
					'pattern' => '/^(?:1624\\d{6}|(?:[3578]\\d|90)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:1624(?:230|[5-8]\\d\\d)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:76245[06]\\d{4}|7(?:4576|[59]24\\d|624[0-4689])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:808162\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:440[49]06|72299\\d)\\d{3}|(?:8(?:45|70)|90[0167])624\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{8})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:56\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3440[49]06\\d{3}|(?:3(?:08162|3\\d{4}|45624|7(?:0624|2299))|55\\d{4})\\d{4})$/i',
						],
					],
				],
				'JE' => [
					'pattern' => '/^(?:1534\\d{6}|(?:[3578]\\d|90)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:1534[0-24-8]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:(?:(?:50|82)9|937)\\d|7(?:00[378]|97[7-9]))\\d{5})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:76(?:464|652)\\d{5}|76(?:0[0-2]|2[356]|34|4[01347]|5[49]|6[0-369]|77|81|9[139])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:07(?:35|81)|8901)\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:8(?:4(?:4(?:4(?:05|42|69)|703)|5(?:041|800))|7(?:0002|1206))|90(?:066[59]|1810|71(?:07|55)))\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:701511\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:56\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3(?:0(?:07(?:35|81)|8901)|3\\d{4}|4(?:4(?:4(?:05|42|69)|703)|5(?:041|800))|7(?:0002|1206))|55\\d{4})\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => 'GB',
		],
		'995' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:70)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:32)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[57])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[348])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'GE' => [
					'pattern' => '/^(?:(?:[3-57]\\d\\d|800)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:3(?:[256]\\d|4[124-9]|7[0-4])|4(?:1\\d|2[2-7]|3[1-79]|4[2-8]|7[239]|9[1-7]))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:(?:0555|1177)[5-9]|757(?:7[7-9]|8[01]))\\d{3}|5(?:0070|(?:11|33)33|[25]222)[0-4]\\d{3}|5(?:00(?:0\\d|50)|11(?:00|1\\d|2[0-4])|5200|75(?:00|[57]5)|8(?:0(?:[01]\\d|2[0-4])|58[89]|8(?:55|88)))\\d{4}|(?:5(?:[14]4|5[0157-9]|68|7[0147-9]|9[1-35-9])|790)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70[67]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'594' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[569])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'GF' => [
					'pattern' => '/^(?:(?:[56]94|80\\d|976)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:594(?:[023]\\d|1[01]|4[03-9]|5[6-9]|6[0-3]|80|9[0-4])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:694(?:[0-249]\\d|3[0-48])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:976\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'233' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[237]|8[0-2])/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[235])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'GH' => [
					'pattern' => '/^(?:(?:[235]\\d{3}|800)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:3082[0-5]\\d{4}|3(?:0(?:[237]\\d|8[01])|[167](?:2[0-6]|7\\d|80)|2(?:2[0-5]|7\\d|80)|3(?:2[0-3]|7\\d|80)|4(?:2[013-9]|3[01]|7\\d|80)|5(?:2[0-7]|7\\d|80)|8(?:2[0-2]|7\\d|80)|9(?:[28]0|7\\d))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:[0346-8]\\d|5[67])|5(?:[0457]\\d|6[01]|9[1-9]))\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'350' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'GI' => [
					'pattern' => '/^(?:(?:[25]\\d\\d|606)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:21(?:6[24-7]\\d|90[0-2])\\d{3}|2(?:00|2[25])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:5[146-8]\\d|606)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'299' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:19|[2-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'GL' => [
					'pattern' => '/^(?:(?:19|[2-689]\\d|70)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:19|3[1-7]|6[14689]|70|8[14-79]|9\\d)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[245]\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[89]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'220' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'GM' => [
					'pattern' => '/^(?:[2-9]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4(?:[23]\\d\\d|4(?:1[024679]|[6-9]\\d))|5(?:5(?:3\\d|4[0-7])|6[67]\\d|7(?:1[04]|2[035]|3[58]|48))|8\\d{3})\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[23679]\\d|5[0-389])\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'224' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[67])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'GN' => [
					'pattern' => '/^(?:722\\d{6}|(?:3|6\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3(?:0(?:24|3[12]|4[1-35-7]|5[13]|6[189]|[78]1|9[1478])|1\\d\\d)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[0-356]\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:722\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'240' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[235])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'GQ' => [
					'pattern' => '/^(?:222\\d{6}|(?:3\\d|55|[89]0)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:33[0-24-9]\\d[46]\\d{4}|3(?:33|5\\d)\\d[7-9]\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:222|55\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d[1-9]\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d[1-9]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'30' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:21|7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:2(?:2|3[2-57-9]|4[2-469]|5[2-59]|6[2-9]|7[2-69]|8[2-49])|5)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2689])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'GR' => [
					'pattern' => '/^(?:5005000\\d{3}|8\\d{9,11}|(?:[269]\\d|70)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:1\\d\\d|2(?:2[1-46-9]|[36][1-8]|4[1-7]|5[1-4]|7[1-5]|[89][1-9])|3(?:1\\d|2[1-57]|[35][1-3]|4[13]|7[1-7]|8[124-6]|9[1-79])|4(?:1\\d|2[1-8]|3[1-4]|4[13-5]|6[1-578]|9[1-5])|5(?:1\\d|[29][1-4]|3[1-5]|4[124]|5[1-6])|6(?:1\\d|[269][1-6]|3[1245]|4[1-7]|5[13-9]|7[14]|8[1-5])|7(?:1\\d|2[1-5]|3[1-6]|4[1-7]|5[1-57]|6[135]|9[125-7])|8(?:1\\d|2[1-5]|[34][1-4]|9[1-57]))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:68[57-9]\\d{7}|(?:69|94)\\d{8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7,9})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[19]\\d{7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:0[16]|12|[27]5|50)\\d{7})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5005000\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'502' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'GT' => [
					'pattern' => '/^(?:(?:1\\d{3}|[2-7])\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[267][2-9]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[3-5]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:18[01]\\d{8})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:19\\d{9})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'245' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:40)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[49])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'GW' => [
					'pattern' => '/^(?:[49]\\d{8}|4\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:443\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:5\\d|6[569]|77)\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:40\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'592' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-46-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'GY' => [
					'pattern' => '/^(?:(?:862\\d|9008)\\d{3}|(?:[2-46]\\d|77)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:1[6-9]|2[0-35-9]|3[1-4]|5[3-9]|6\\d|7[0-24-79])|3(?:2[25-9]|3\\d)|4(?:4[0-24]|5[56])|77[1-57])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:289|862)\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9008\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'852' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2,5}))$/i',
					'leadingDigits' => '/^(?:9003)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7]|8[1-4]|9(?:0[1-9]|[1-8]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'HK' => [
					'pattern' => '/^(?:8[0-46-9]\\d{6,7}|9\\d{4}(?:\\d(?:\\d(?:\\d{4})?)?)?|(?:[235-79]\\d|46)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:[13-9]\\d|2[013-9])\\d|3(?:(?:[1569][0-24-9]|4[0-246-9]|7[0-24-69])\\d|8(?:4[0-8]|5[0-5]|9\\d))|58(?:0[1-8]|1[2-9]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:46(?:[07][0-7]|1[0-6]|4[0-57-9]|5[0-8]|6[0-4])|573[0-6]|6(?:26[013-7]|66[0-3])|70(?:7[1-5]|8[0-4])|848[015-9]|929[03-9])\\d{4}|(?:46[238]|5(?:[1-59][0-46-9]|6[0-4689]|7[0-2469])|6(?:0[1-9]|[13-59]\\d|[268][0-57-9]|7[0-79])|84[09]|9(?:0[1-9]|1[02-9]|[2358][0-8]|[467]\\d))\\d{5})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:1(?:0[0-38]|1[0-3679]|3[013]|69|9[0136])|2(?:[02389]\\d|1[18]|7[27-9])|3(?:[0-38]\\d|7[0-369]|9[2357-9])|47\\d|5(?:[178]\\d|5[0-5])|6(?:0[0-7]|2[236-9]|[35]\\d)|7(?:[27]\\d|8[7-9])|8(?:[23689]\\d|7[1-9])|9(?:[025]\\d|6[0-246-8]|7[0-36-9]|8[238]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900(?:[0-24-9]\\d{7}|3\\d{1,4}))$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:1[0-4679]\\d|2(?:[0-36]\\d|7[0-4])|3(?:[034]\\d|2[09]|70))\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:30(?:0[1-9]|[15-7]\\d|2[047]|89)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'504' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[237-9])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
			],
			'countries' => [
				'HN' => [
					'pattern' => '/^(?:8\\d{10}|[237-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:2(?:0[0-39]|1[1-367]|[23]\\d|4[03-6]|5[57]|6[245]|7[0135689]|8[01346-9]|9[0-2])|4(?:0[78]|2[3-59]|3[13-9]|4[0-68]|5[1-35])|5(?:0[7-9]|16|4[03-5]|5\\d|6[014-6]|74|80)|6(?:[056]\\d|17|2[067]|3[04]|4[0-378]|[78][0-8]|9[01])|7(?:6[46-9]|7[02-9]|8[034]|91)|8(?:79|8[0-357-9]|9[1-57-9]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[37-9]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8002\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'385' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:6[01])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[67])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[2-5])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'HR' => [
					'pattern' => '/^(?:(?:[24-69]\\d|3[0-79])\\d{7}|80\\d{5,7}|[1-79]\\d{7}|6\\d{5,6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:1\\d{7}|(?:2[0-3]|3[1-5]|4[02-47-9]|5[1-3])\\d{6,7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:751\\d{5}|8\\d{6,7})|9(?:0[1-9]|[1259]\\d|7[0679])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[01]\\d{4,6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[01459]\\d{6}|6[01]\\d{4,5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[45]\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:62\\d{6,7}|72\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'509' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-489])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'HT' => [
					'pattern' => '/^(?:[2-489]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:2\\d|5[1-5]|81|9[149])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[34]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:[67][0-4]|8[0-3589]|9\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'36' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[27][2-9]|3[2-7]|4[24-9]|5[2-79]|6|8[2-57-9]|9[2-69])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'HU' => [
					'pattern' => '/^(?:[235-7]\\d{8}|[1-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:1\\d|[27][2-9]|3[2-7]|4[24-9]|5[2-79]|6[23689]|8[2-57-9]|9[2-69])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[257]0|3[01])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[48]0\\d|6802)\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[01]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:21\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:38\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'62' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:15)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5,9}))$/i',
					'leadingDigits' => '/^(?:2[124]|[36]1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5,7}))$/i',
					'leadingDigits' => '/^(?:800)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5,8}))$/i',
					'leadingDigits' => '/^(?:[2-79])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8[1-35-9])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6,8}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:804)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:80)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:001)/i',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
			],
			'countries' => [
				'ID' => [
					'pattern' => '/^(?:(?:(?:00[1-9]|8\\d)\\d{4}|[1-36])\\d{6}|00\\d{10}|[1-9]\\d{8,10}|[2-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:2[124]\\d{7,8}|619\\d{8}|2(?:1(?:14|500)|2\\d{3})\\d{3}|61\\d{5,8}|(?:2(?:[35][1-4]|6[0-8]|7[1-6]|8\\d|9[1-8])|3(?:1|[25][1-8]|3[1-68]|4[1-3]|6[1-3568]|7[0-469]|8\\d)|4(?:0[1-589]|1[01347-9]|2[0-36-8]|3[0-24-68]|43|5[1-378]|6[1-5]|7[134]|8[1245])|5(?:1[1-35-9]|2[25-8]|3[124-9]|4[1-3589]|5[1-46]|6[1-8])|6(?:[25]\\d|3[1-69]|4[1-6])|7(?:02|[125][1-9]|[36]\\d|4[1-8]|7[0-36-9])|9(?:0[12]|1[013-8]|2[0-479]|5[125-8]|6[23679]|7[159]|8[01346]))\\d{5,8})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[1-35-9]\\d{7,10})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:00[17]803\\d{7}|(?:177\\d|800)\\d{5,7}|001803\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:809\\d{7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:804\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1500|8071\\d{3})\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'353' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:2[24-9]|47|58|6[237-9]|9[35-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[45]0)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[2569]|4[1-69]|7[14])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:70)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:81)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[78])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:4)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'IE' => [
					'pattern' => '/^(?:(?:1\\d|[2569])\\d{6,8}|4\\d{6,9}|7\\d{8}|8\\d{8,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
								],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:(?:1\\d|21)\\d{6,7}|(?:2[24-9]|4(?:0[24]|5\\d|7)|5(?:0[45]|1\\d|8)|6(?:1\\d|[237-9])|9(?:1\\d|[35-9]))\\d{5}|(?:23|4(?:[1-469]|8\\d)|5[23679]|6[4-6]|7[14]|9[04])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:22|[35-9]\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:15(?:1[2-8]|[2-8]0|9[089])\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:18[59]0\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:76\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:818\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:88210[1-9]\\d{4}|8(?:[35-79]5\\d\\d|8(?:[013-9]\\d\\d|2(?:[01][1-9]|[2-9]\\d)))\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'972' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:125)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:121)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-489])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[57])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:12)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:159)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1[7-9])/i',
					'format' => '$1-$2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{1,2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:15)/i',
					'format' => '$1-$2 $3-$4',
				],
			],
			'countries' => [
				'IL' => [
					'pattern' => '/^(?:1\\d{6}(?:\\d{3,5})?|[57]\\d{8}|[1-489]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									11,
									12,
								],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:153\\d{8,9}|29[1-9]\\d{5}|(?:2[0-8]|[3489]\\d)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:(?:[02368]\\d|[19][2-9]|4[1-9])\\d|5(?:01|1[79]|2[2-9]|3[0-3]|4[34]|5[015689]|6[6-8]|7[0-267]|8[7-9]|9[1-9]))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:255|80[019]\\d{3})\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									8,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1212\\d{4}|1(?:200|9(?:0[01]|19))\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1700\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:380|8(?:33|55|77|81))\\d{5}|7(?:18|2[23]|3[237]|47|6[58]|7\\d|82|9[235-9])\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1599\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:151\\d{8,9})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'91' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{7}))$/i',
					'leadingDigits' => '/^(?:575)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{8}))$/i',
					'leadingDigits' => '/^(?:5(?:0|2(?:21|3)|3(?:0|3[23])|616|717|8888))/i',
					'format' => '$1',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:1800)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:140)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:11|2[02]|33|4[04]|79(?:[124-6]|3(?:[02-9]|1[0-24-9])|7(?:1|9[1-6]))|80(?:[2-4]|6[0-589]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1(?:2[0-24]|3[0-25]|4[145]|[59][14]|6[1-9]|7[1257]|8[1-57-9])|2(?:1[257]|3[013]|4[01]|5[0137]|6[058]|78|8[1568]|9[14])|3(?:26|4[1-3]|5[34]|6[01489]|7[02-46]|8[159])|4(?:1[36]|2[1-47]|3[15]|5[12]|6[0-26-9]|7[0-24-9]|8[013-57]|9[014-7])|5(?:1[025]|22|[36][25]|4[28]|[578]1|9[15])|6(?:12(?:[2-6]|7[0-8])|74[2-7])|7(?:(?:2[14]|5[15])[2-6]|3171|61[346]|88(?:[2-7]|82))|8(?:70[2-6]|84(?:[2356]|7[19])|91(?:[3-6]|7[19]))|73[134][2-6]|(?:74[47]|8(?:16|2[014]|3[126]|6[136]|7[78]|83))(?:[2-6]|7[19])|(?:1(?:29|60|8[06])|261|552|6(?:[2-4]1|5[17]|6[13]|7(?:1|4[0189])|80)|7(?:12|88[01]))[2-7])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1(?:[2-479]|5(?:[0236-9]|5[013-9]))|[2-5]|6(?:2(?:84|95)|355|83)|73179|807(?:1|9[1-3])|(?:1552|6(?:1[1358]|2[2457]|3[2-4]|4[235-7]|5[2-689]|6[24578]|7[235689]|8[124-6])\\d|7(?:1(?:[013-8]\\d|9[6-9])|28[6-8]|3(?:2[0-49]|9[2-57])|4(?:1[2-4]|[29][0-7]|3[0-8]|[56]\\d|8[0-24-7])|5(?:2[1-3]|9[0-6])|6(?:0[5689]|2[5-9]|3[02-8]|4\\d|5[0-367])|70[13-7]))[2-7])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[6-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1(?:6|8[06]0))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:18)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'IN' => [
					'pattern' => '/^(?:(?:000800|[2-9]\\d\\d)\\d{7}|1\\d{7,12})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [
									6,
									7,
									8,
								],
							],
							'pattern' => '/^(?:2717(?:[2-7]\\d|95)\\d{4}|(?:271[0-689]|782[0-6])[2-7]\\d{5}|(?:170[24]|2(?:(?:[02][2-79]|90)\\d|80[13468])|(?:3(?:23|80)|683|79[1-7])\\d|4(?:20[24]|72[2-8])|552[1-7])\\d{6}|(?:11|33|4[04]|80)[2-7]\\d{7}|(?:342|674|788)(?:[0189][2-7]|[2-7]\\d)\\d{5}|(?:1(?:2[0-249]|3[0-25]|4[145]|[59][14]|6[014]|7[1257]|8[01346])|2(?:1[257]|3[013]|4[01]|5[0137]|6[0158]|78|8[1568]|9[14])|3(?:26|4[13]|5[34]|6[01489]|7[02-46]|8[159])|4(?:1[36]|2[1-47]|3[15]|5[12]|6[0-26-9]|7[014-9]|8[013-57]|9[014-7])|5(?:1[025]|22|[36][25]|4[28]|[578]1|9[15])|6(?:12|[2-47]1|5[17]|6[13]|80)|7(?:12|2[14]|3[134]|4[47]|5[15]|[67]1)|8(?:16|2[014]|3[126]|6[136]|7[078]|8[34]|91))[2-7]\\d{6}|(?:1(?:2[35-8]|3[346-9]|4[236-9]|[59][0235-9]|6[235-9]|7[34689]|8[257-9])|2(?:1[134689]|3[24-8]|4[2-8]|5[25689]|6[2-4679]|7[3-79]|8[2-479]|9[235-9])|3(?:01|1[79]|2[1245]|4[5-8]|5[125689]|6[235-7]|7[157-9]|8[2-46-8])|4(?:1[14578]|2[5689]|3[2-467]|5[4-7]|6[35]|73|8[2689]|9[2389])|5(?:[16][146-9]|2[14-8]|3[1346]|4[14-69]|5[46]|7[2-4]|8[2-8]|9[246])|6(?:1[1358]|2[2457]|3[2-4]|4[235-7]|5[2-689]|6[24578]|7[235689]|8[124-6])|7(?:1[013-9]|2[0235-9]|3[2679]|4[1-35689]|5[2-46-9]|[67][02-9]|8[013-7]|9[089])|8(?:1[1357-9]|2[235-8]|3[03-57-9]|4[0-24-9]|5\\d|6[2457-9]|7[1-6]|8[1256]|9[2-4]))\\d[2-7]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:61279|7(?:887[02-9]|9(?:313|79[07-9]))|8(?:079[04-9]|(?:84|91)7[02-8]))\\d{5}|(?:6(?:12|[2-47]1|5[17]|6[13]|80)[0189]|7(?:1(?:2[0189]|9[0-5])|2(?:[14][017-9]|8[0-59])|3(?:2[5-8]|[34][017-9]|9[016-9])|4(?:1[015-9]|[29][89]|39|8[389])|5(?:[15][017-9]|2[04-9]|9[7-9])|6(?:0[0-47]|1[0-257-9]|2[0-4]|3[19]|5[4589])|70[0289]|88[089]|97[02-8])|8(?:0(?:6[67]|7[02-8])|70[017-9]|84[01489]|91[0-289]))\\d{6}|(?:7(?:31|4[47])|8(?:16|2[014]|3[126]|6[136]|7[78]|83))(?:[0189]\\d|7[02-8])\\d{5}|(?:6(?:[09]\\d|1[04679]|2[03689]|3[05-9]|4[0489]|50|6[069]|7[07]|8[7-9])|7(?:0\\d|2[0235-79]|3[05-8]|40|5[0346-8]|6[6-9]|7[1-9]|8[0-79]|9[089])|8(?:0[01589]|1[0-57-9]|2[235-9]|3[03-57-9]|[45]\\d|6[02457-9]|7[1-69]|8[0-25-9]|9[02-9])|9\\d\\d)\\d{7}|(?:6(?:(?:1[1358]|2[2457]|3[2-4]|4[235-7]|5[2-689]|6[24578]|8[124-6])\\d|7(?:[235689]\\d|4[0189]))|7(?:1(?:[013-8]\\d|9[6-9])|28[6-8]|3(?:2[0-49]|9[2-5])|4(?:1[2-4]|[29][0-7]|3[0-8]|[56]\\d|8[0-24-7])|5(?:2[1-3]|9[0-6])|6(?:0[5689]|2[5-9]|3[02-8]|4\\d|5[0-367])|70[13-7]|881))[0189]\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:000800\\d{7}|1(?:600\\d{6}|80(?:0\\d{4,9}|3\\d{9})))$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [13],
								'localOnly' => [],
							],
							'pattern' => '/^(?:186[12]\\d{9})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1860\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:140\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'246' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'IO' => [
					'pattern' => '/^(?:3\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:37\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:38\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'964' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[2-6])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'IQ' => [
					'pattern' => '/^(?:(?:1|7\\d\\d)\\d{7}|[2-6]\\d{7,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:1\\d{7}|(?:2[13-5]|3[02367]|4[023]|5[03]|6[026])\\d{6,7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[3-9]\\d{8})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'98' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:96)/i',
					'format' => '$1',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:(?:1[137]|2[13-68]|3[1458]|4[145]|5[1468]|6[16]|7[1467]|8[13467])[12689])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-8])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'IR' => [
					'pattern' => '/^(?:[1-9]\\d{9}|(?:[1-8]\\d\\d|9)\\d{3,4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									10,
								],
								'localOnly' => [
									'4',
									'5',
									'8',
								],
							],
							'pattern' => '/^(?:(?:1[137]|2[13-68]|3[1458]|4[145]|5[1468]|6[16]|7[1467]|8[13467])(?:[03-57]\\d{7}|[16]\\d{3}(?:\\d{4})?|[289]\\d{3}(?:\\d(?:\\d{3})?)?)|94(?:000[09]|2(?:121|[2689]0\\d)|30[0-2]\\d|4(?:111|40\\d))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:(?:0(?:[0-35]\\d|4[4-6])|(?:[13]\\d|2[0-3])\\d)\\d|9(?:(?:[0-3]\\d|4[0145])\\d|5[15]0|8(?:1\\d|88)|9(?:0[013]|[19]\\d|21|77|8[7-9])))\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									4,
									5,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:96(?:0[12]|2[16-8]|3(?:08|[14]5|[23]|66)|4(?:0|80)|5[01]|6[89]|86|9[19]))$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'354' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[4-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'IS' => [
					'pattern' => '/^(?:(?:38\\d|[4-9])\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4(?:1[0-24-69]|2[0-7]|[37][0-8]|4[0-245]|5[0-68]|6\\d|8[0-36-8])|5(?:05|[156]\\d|2[02578]|3[0-579]|4[03-7]|7[0-2578]|8[0-35-9]|9[013-689])|872)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:38[589]\\d\\d|6(?:1[1-8]|2[0-6]|3[027-9]|4[014679]|5[0159]|6[0-69]|70|8[06-8]|9\\d)|7(?:5[057]|[6-9]\\d)|8(?:2[0-59]|[3-69]\\d|8[28]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[08]\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90(?:0\\d|1[5-79]|2[015-79]|3[135-79]|4[125-7]|5[25-79]|7[1-37]|8[0-35-7])\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:49[0-24-79]\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:809\\d{4})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:689|8(?:7[18]|80)|95[48])\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'39' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:1(?:0|9(?:2[2-9]|[46])))/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:1(?:1|92))/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:0[26])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,6}))$/i',
					'leadingDigits' => '/^(?:0[13-57-9][0159]|8(?:03|4[17]|9(?:2|3[04]|[45][0-4])))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2,6}))$/i',
					'leadingDigits' => '/^(?:0(?:[13-579][2-46-8]|8[236-8]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:894)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0[26]|5)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:1(?:44|[679])|[38])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0[13-57-9][0159]|14)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:0[26])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'IT' => [
					'pattern' => '/^(?:0\\d{5,10}|1\\d{8,10}|3(?:[0-8]\\d{7,10}|9\\d{7,8})|55\\d{8}|8\\d{5}(?:\\d{2,4})?)$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0669[0-79]\\d{1,6}|0(?:1(?:[0159]\\d|[27][1-5]|31|4[1-4]|6[1356]|8[2-57])|2\\d\\d|3(?:[0159]\\d|2[1-4]|3[12]|[48][1-6]|6[2-59]|7[1-7])|4(?:[0159]\\d|[23][1-9]|4[245]|6[1-5]|7[1-4]|81)|5(?:[0159]\\d|2[1-5]|3[2-6]|4[1-79]|6[4-6]|7[1-578]|8[3-8])|6(?:[0-57-9]\\d|6[0-8])|7(?:[0159]\\d|2[12]|3[1-7]|4[2-46]|6[13569]|7[13-6]|8[1-59])|8(?:[0159]\\d|2[3-578]|3[1-356]|[6-8][1-5])|9(?:[0159]\\d|[238][1-5]|4[12]|6[1-8]|7[1-6]))\\d{2,7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[1-9]\\d{8}|3[2-9]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:0\\d{3}|3)\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									6,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0878\\d{3}|89(?:2\\d|3[04]|4(?:[0-4]|[5-9]\\d\\d)|5[0-4]))\\d\\d|(?:1(?:44|6[346])|89(?:38|5[5-9]|9))\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:84(?:[08]\\d{3}|[17])\\d{3})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:78\\d|99)\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:55\\d{8})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[2-8]\\d{9,10})$/i',
						],
					],
				],
				'VA' => [
					'pattern' => '/^(?:0\\d{5,10}|3[0-8]\\d{7,10}|55\\d{8}|8\\d{5}(?:\\d{2,4})?|(?:1\\d|39)\\d{7,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:06698\\d{1,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[1-9]\\d{8}|3[2-9]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:0\\d{3}|3)\\d{3})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									6,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0878\\d{3}|89(?:2\\d|3[04]|4(?:[0-4]|[5-9]\\d\\d)|5[0-4]))\\d\\d|(?:1(?:44|6[346])|89(?:38|5[5-9]|9))\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:84(?:[08]\\d{3}|[17])\\d{3})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:78\\d|99)\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:55\\d{8})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[2-8]\\d{9,10})$/i',
						],
					],
				],
			],
			'main_country' => 'IT',
		],
		'962' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2356]|87)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:70)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'JO' => [
					'pattern' => '/^(?:(?:(?:[2689]|7\\d)\\d|32|53)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:87(?:000|90[01])\\d{3}|(?:2(?:6(?:2[0-35-9]|3[0-578]|4[24-7]|5[0-24-8]|[6-8][023]|9[0-3])|7(?:0[1-79]|10|2[014-7]|3[0-689]|4[019]|5[0-3578]))|32(?:0[1-69]|1[1-35-7]|2[024-7]|3\\d|4[0-3]|[5-7][023])|53(?:0[0-3]|[13][023]|2[0-59]|49|5[0-35-9]|6[15]|7[45]|8[1-6]|9[0-36-9])|6(?:2(?:[05]0|22)|3(?:00|33)|4(?:0[0-25]|1[2-7]|2[0569]|[38][07-9]|4[025689]|6[0-589]|7\\d|9[0-2])|5(?:[01][056]|2[034]|3[0-57-9]|4[178]|5[0-69]|6[0-35-9]|7[1-379]|8[0-68]|9[0239]))|87(?:20|7[078]|99))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:[78][0-25-9]|9\\d)\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:74(?:66|77)\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9\\d{7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:85\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:10|8\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'81' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:00777[01])/i',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:(?:12|57|99)0)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d)(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1(?:267|3(?:7[247]|9[278])|466|5(?:47|58|64)|6(?:3[245]|48|5[4-68]))|499[2468]|5(?:769|979[2-69])|7468|8(?:3(?:8[7-9]|96[2457-9])|477|51[2-9]|636[457-9])|9(?:496|802|9(?:1[23]|69))|1(?:45|58)[67])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:60)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[36]|4(?:2(?:0|9[02-69])|7(?:0[019]|1)))/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1(?:1|5(?:4[018]|5[017])|77|88|9[69])|2(?:2[127]|3[0-269]|4[59]|5(?:[1-3]|5[0-69]|7[015-9]|9(?:17|99))|6(?:2|4[016-9])|7(?:[1-35]|8[0189])|8(?:[16]|3[0134]|9[0-5])|9(?:[028]|17|3[015-9]))|4(?:2(?:[13-79]|8[014-6])|3[0-57]|[45]|6[248]|7[2-47]|9[29])|5(?:2|3[045]|4[0-369]|5[29]|8[02389]|9[0-3])|7(?:2[02-46-9]|34|[58]|6[0249]|7[57]|9(?:[23]|4[0-59]|5[01569]|6[0167]))|8(?:2(?:[1258]|4[0-39]|9(?:[019]|4[1-3]|6(?:[0-47-9]|5[01346-9])))|3(?:[29]|7(?:[017-9]|6[6-8]))|49|51|6(?:[0-24]|36[23]|5(?:[0-389]|5[23])|6(?:[01]|9[178])|72|9[0145])|7[0-468]|8[68])|9(?:4[15]|5[138]|6[1-3]|7[156]|8[189]|9(?:[1289]|3(?:31|4[357])|4[0178]))|(?:223|8699)[014-9]|(?:25[0468]|422|838)[01]|(?:48|829(?:2|66)|9[23])[1-9]|(?:47[59]|59[89]|8(?:68|9))[019])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[14]|[289][2-9]|5[3-9]|7[2-4679])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:0077)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:008)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:800)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[257-9])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{6})(\\d{6,7}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
			],
			'countries' => [
				'JP' => [
					'pattern' => '/^(?:00[1-9]\\d{6,14}|[257-9]\\d{9}|(?:00|[1-9]\\d\\d)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:1[235-8]|2[3-6]|3[3-9]|4[2-6]|[58][2-8]|6[2-7]|7[2-9]|9[1-9])|(?:2[2-9]|[36][1-9])\\d|4(?:[2-578]\\d|6[02-8]|9[2-59])|5(?:[2-589]\\d|6[1-9]|7[2-8])|7(?:[25-9]\\d|3[4-9]|4[02-9])|8(?:[2679]\\d|3[2-9]|4[5-9]|5[1-9]|8[03-9])|9(?:[2-58]\\d|[679][1-9]))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[7-9]0[1-9]\\d{7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20\\d{8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
									11,
									12,
									13,
									14,
									15,
									16,
									17,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:00777(?:[01]|5\\d)\\d\\d|(?:00(?:7778|882[1245])|(?:120|800\\d)\\d\\d)\\d{4}|00(?:37|66|78)\\d{6,13})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:990\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:60\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:50[1-9]\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:570\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'254' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5,7}))$/i',
					'leadingDigits' => '/^(?:[24-6])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[17])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'KE' => [
					'pattern' => '/^(?:(?:[17]\\d\\d|900)\\d{6}|(?:2|80)0\\d{6,7}|[4-6]\\d{6,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4[245]|5[1-79]|6[01457-9])\\d{5,7}|(?:4[136]|5[08]|62)\\d{7}|(?:[24]0|66)\\d{6,7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:0[0-6]|1[0-5]|2[014])|7\\d\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800[24-8]\\d{5,6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[02-9]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'996' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:3(?:1[346]|[24-79]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[235-79]|88)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d)(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'KG' => [
					'pattern' => '/^(?:8\\d{9}|(?:[235-8]\\d|99)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:312(?:5[0-79]\\d|9(?:[0-689]\\d|7[0-24-9]))\\d{3}|(?:3(?:1(?:2[0-46-8]|3[1-9]|47|[56]\\d)|2(?:22|3[0-479]|6[0-7])|4(?:22|5[6-9]|6\\d)|5(?:22|3[4-7]|59|6\\d)|6(?:22|5[35-7]|6\\d)|7(?:22|3[468]|4[1-9]|59|[67]\\d)|9(?:22|4[1-8]|6\\d))|6(?:09|12|2[2-4])\\d)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:312(?:58\\d|973)\\d{3}|(?:2(?:0[0-35]|2\\d)|5[0-24-7]\\d|7(?:[07]\\d|55)|880|99[05-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6,7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'855' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'KH' => [
					'pattern' => '/^(?:1\\d{9}|[1-9]\\d{7,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:23(?:4(?:[2-4]|[56]\\d)|[568]\\d\\d)\\d{4}|23[236-9]\\d{5}|(?:2[4-6]|3[2-6]|4[2-4]|[5-7][2-5])(?:(?:[237-9]|4[56]|5\\d)\\d{5}|6\\d{5,6}))$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:1[28]|3[18]|9[67])\\d|6[016-9]|7(?:[07-9]|[16]\\d)|8(?:[013-79]|8\\d))\\d{6}|(?:1\\d|9[0-57-9])\\d{6}|(?:2[3-6]|3[2-6]|4[2-4]|[5-7][2-5])48\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800(?:1\\d|2[019])\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1900(?:1\\d|2[09])\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'686' => [
			'formats' => [],
			'countries' => [
				'KI' => [
					'pattern' => '/^(?:(?:[37]\\d|6[0-79])\\d{6}|(?:[2-48]\\d|50)\\d{3})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									5,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[24]\\d|3[1-9]|50|65(?:02[12]|12[56]|22[89]|[3-5]00)|7(?:27\\d\\d|3100|5(?:02[12]|12[56]|22[89]|[34](?:00|81)|500))|8[0-5])\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:63\\d{3}|73(?:0[0-5]\\d|140))\\d{3}|[67]200[01]\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:30(?:0[01]\\d\\d|12(?:11|20))\\d\\d)$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'269' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[3478])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'KM' => [
					'pattern' => '/^(?:[3478]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [4],
							],
							'pattern' => '/^(?:7[4-7]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[34]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'850' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'KP' => [
					'pattern' => '/^(?:85\\d{6}|(?:19\\d|[2-7])\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									10,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:(?:195|2)\\d|3[19]|4[159]|5[37]|6[17]|7[39]|85)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:19[1-3]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'82' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5}))$/i',
					'leadingDigits' => '/^(?:1[016-9]114)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:(?:3[1-3]|[46][1-4]|5[1-5])1)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:60|8)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1346]|5[1-5])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[57])/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:0030)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
			],
			'countries' => [
				'KR' => [
					'pattern' => '/^(?:00[1-9]\\d{8,11}|(?:[12]|5\\d{3})\\d{7}|[13-6]\\d{9}|(?:[1-6]\\d|80)\\d{7}|[3-6]\\d{4,5}|(?:00|7)0\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									5,
									6,
									8,
									9,
									10,
								],
								'localOnly' => [
									'3',
									'4',
									'7',
								],
							],
							'pattern' => '/^(?:(?:2|3[1-3]|[46][1-4]|5[1-5])[1-9]\\d{6,7}|(?:3[1-3]|[46][1-4]|5[1-5])1\\d{2,3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:05(?:[0-8]\\d|9[0-6])|22[13]\\d)\\d{4,5}|1(?:0[1-46-9]|[16-9]\\d|2[013-9])\\d{6,7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:15\\d{7,8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									11,
									12,
									13,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:00(?:308\\d{6,7}|798\\d{7,9})|(?:00368|80)\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:60[2-9]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:50\\d{8,9})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:5(?:22|33|44|66|77|88|99)|6(?:[07]0|44|6[16]|88)|8(?:00|33|55|77|99))\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'965' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[169]|2(?:[235]|4[1-35-9])|52)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[245])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'KW' => [
					'pattern' => '/^(?:18\\d{5}|(?:[2569]\\d|41)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[23]\\d\\d|4(?:[1-35-9]\\d|44)|5(?:0[034]|[2-46]\\d|5[1-3]|7[1-7]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:41\\d\\d|5(?:(?:[05]\\d|1[0-7]|6[56])\\d|2(?:22|5[25])|7(?:55|77)|88[58])|6(?:(?:0[034679]|5[015-9]|6\\d)\\d|222|333|444|7(?:0[013-9]|[67]\\d)|888|9(?:[069]\\d|3[039]))|9(?:(?:0[09]|22|[4679]\\d|8[057-9])\\d|1(?:1[01]|99)|3(?:00|33)|5(?:00|5\\d)))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:18\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'7' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[0-79])/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:7(?:1(?:[0-6]2|7|8[27])|2(?:13[03-69]|62[013-9]))|72[1-57-9]2)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d)(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:7(?:1(?:0(?:[356]|4[023])|[18]|2(?:3[013-9]|5)|3[45]|43[013-79]|5(?:3[1-8]|4[1-7]|5)|6(?:3[0-35-9]|[4-6]))|2(?:1(?:3[178]|[45])|[24-689]|3[35]|7[457]))|7(?:14|23)4[0-8]|71(?:33|45)[1-79])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[349]|8(?:[02-7]|1[1-8]))/i',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'KZ' => [
					'pattern' => '/^(?:(?:33622|8\\d{8})\\d{5}|[78]\\d{9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [
									5,
									6,
									7,
								],
							],
							'pattern' => '/^(?:(?:33622|7(?:1(?:0(?:[23]\\d|4[0-3]|59|63)|1(?:[23]\\d|4[0-79]|59)|2(?:[23]\\d|59)|3(?:2\\d|3[0-79]|4[0-35-9]|59)|4(?:[24]\\d|3[013-9]|5[1-9])|5(?:2\\d|3[1-9]|4[0-7]|59)|6(?:[2-4]\\d|5[19]|61)|72\\d|8(?:[27]\\d|3[1-46-9]|4[0-5]))|2(?:1(?:[23]\\d|4[46-9]|5[3469])|2(?:2\\d|3[0679]|46|5[12679])|3(?:[2-4]\\d|5[139])|4(?:2\\d|3[1-35-9]|59)|5(?:[23]\\d|4[0-246-8]|59|61)|6(?:2\\d|3[1-9]|4[0-4]|59)|7(?:[2379]\\d|40|5[279])|8(?:[23]\\d|4[0-3]|59)|9(?:2\\d|3[124578]|59))))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:0[0-25-8]|47|6[0-4]|7[15-8]|85)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|108\\d{3})\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:809\\d{7})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:808\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:751\\d{7})$/i',
						],
					],
				],
				'RU' => [
					'pattern' => '/^(?:8\\d{13}|[347-9]\\d{9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:3(?:0[12]|4[1-35-79]|5[1-3]|65|8[1-58]|9[0145])|4(?:01|1[1356]|2[13467]|7[1-5]|8[1-7]|9[1-689])|8(?:1[1-8]|2[01]|3[13-6]|4[0-8]|5[15]|6[1-35-79]|7[1-37-9]))\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9\\d{9})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:0[04]|108\\d{3})\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[39]\\d{7})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:808\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => 'RU',
		],
		'856' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2[13]|3[14]|[4-8])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:30[013-9])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[23])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'LA' => [
					'pattern' => '/^(?:[23]\\d{9}|3\\d{8}|(?:[235-8]\\d|41)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:(?:2[13]|[35-7][14]|41|8[1468])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:20(?:[239]\\d|5[24-9]|7[6-8])|302\\d)\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:30[013-9]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'961' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[13-69]|7(?:[2-57]|62|8[0-7]|9[04-9])|8[02-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[27-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'LB' => [
					'pattern' => '/^(?:[27-9]\\d{7}|[13-9]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:62|8[0-7]|9[04-9])\\d{4}|(?:[14-69]\\d|2(?:[14-69]\\d|[78][1-9])|7[2-57]|8[02-9])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:793(?:[01]\\d|2[0-4])\\d{3}|(?:(?:3|81)\\d|7(?:[01]\\d|6[013-9]|8[89]|9[12]))\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[01]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'423' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[237-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:69)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'LI' => [
					'pattern' => '/^(?:90\\d{5}|(?:[2378]|6\\d\\d)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:01|1[27]|2[02]|3\\d|6[02-578]|96)|3(?:[24]0|33|7[0135-7]|8[048]|9[0269]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6(?:4(?:5[4-9]|[6-9]\\d)|5[0-4]\\d|6(?:[0245]\\d|[17]0|3[7-9]))\\d|7(?:[37-9]\\d|42|56))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:02[28]|9\\d\\d)\\d\\d)$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90(?:02[258]|1(?:23|3[14])|66[136])\\d\\d)$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:870(?:28|87)\\d\\d)$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:697(?:42|56|[78]\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'94' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[1-689])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'LK' => [
					'pattern' => '/^(?:[1-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:12[2-9]|602|8[12]\\d|9(?:1\\d|22|9[245]))\\d{6}|(?:11|2[13-7]|3[1-8]|4[157]|5[12457]|6[35-7])[2-57]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:[0-25-8]\\d|4[01])\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1973\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'231' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[4-6])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[3578])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'LR' => [
					'pattern' => '/^(?:(?:2|33|5\\d|77|88)\\d{7}|[4-6]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2\\d{3}|33333)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:330|555|(?:77|88)\\d)\\d|4[67])\\d{5}|[56]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:332(?:02|[34]\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'266' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2568])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'LS' => [
					'pattern' => '/^(?:(?:[256]\\d\\d|800)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[56]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800[256]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'370' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:52[0-7])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[7-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:37|4(?:[15]|6[1-8]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[3-6])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'LT' => [
					'pattern' => '/^(?:(?:[3469]\\d|52|[78]0)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3[1478]|4[124-6]|52)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[02]\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:0[0239]|10)\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:808\\d{5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70[05]\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[89]01\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70[67]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'352' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2(?:0[2-689]|[2-9])|[3-57]|8(?:0[2-9]|[13-9])|9(?:0[89]|[2-579]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:2(?:0[2-689]|[2-9])|[3-57]|8(?:0[2-9]|[13-9])|9(?:0[89]|[2-579]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:20[2-689])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{1,2}))$/i',
					'leadingDigits' => '/^(?:2(?:[0367]|4[3-8]))/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:80[01]|90[015])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:20)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{1,2}))$/i',
					'leadingDigits' => '/^(?:2(?:[0367]|4[3-8]))/i',
					'format' => '$1 $2 $3 $4 $5',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{1,5}))$/i',
					'leadingDigits' => '/^(?:[3-57]|8[13-9]|9(?:0[89]|[2-579])|(?:2|80)[2-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'LU' => [
					'pattern' => '/^(?:35[013-9]\\d{4,8}|6\\d{8}|35\\d{2,4}|(?:[2457-9]\\d|3[0-46-9])\\d{2,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									4,
									5,
									6,
									7,
									8,
									9,
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:35[013-9]|80[2-9]|90[89])\\d{1,8}|(?:2[2-9]|3[0-46-9]|[457]\\d|8[13-9]|9[2-579])\\d{2,9})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6(?:[269][18]|5[1568]|7[189]|81)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[015]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:801\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [
									4,
									5,
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20(?:1\\d{5}|[2-689]\\d{1,7}))$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'371' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[269]|8[01])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'LV' => [
					'pattern' => '/^(?:(?:[268]\\d|90)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:81\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'218' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1-$2',
				],
			],
			'countries' => [
				'LY' => [
					'pattern' => '/^(?:[2-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:2(?:0[56]|[1-6]\\d|7[124579]|8[124])|3(?:1\\d|2[2356])|4(?:[17]\\d|2[1-357]|5[2-4]|8[124])|5(?:[1347]\\d|2[1-469]|5[13-5]|8[1-4])|6(?:[1-479]\\d|5[2-57]|8[1-5])|7(?:[13]\\d|2[13-79])|8(?:[124]\\d|5[124]|84))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[1-6]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'377' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:87)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:4)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[389])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
			'countries' => [
				'MC' => [
					'pattern' => '/^(?:(?:[3489]|6\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:870|9[2-47-9]\\d)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4(?:[46]\\d|5[1-9])\\d{5}|(?:3|6\\d)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:800|90\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'373' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:22|3)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[25-7])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'MD' => [
					'pattern' => '/^(?:(?:[235-7]\\d|[89]0)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:2[1-9]|3[1-79])\\d|5(?:33|5[257]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:562\\d{5}|(?:6\\d|7[16-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[056]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:808\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[08]\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:803\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'382' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'ME' => [
					'pattern' => '/^(?:(?:20|[3-79]\\d)\\d{6}|80\\d{6,7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:(?:20[2-8]|3(?:[0-2][2-7]|3[24-7])|4(?:0[2-467]|1[2467])|5(?:0[2467]|1[24-7]|2[2-467]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6(?:[07-9]\\d|3[024]|6[0-25])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:[0-2578]|9\\d)\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:4[1568]|5[178])\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:78[1-49]\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:77[1-9]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'261' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[23])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'MG' => [
					'pattern' => '/^(?:[23]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:2072[29]\\d{4}|20(?:2\\d|4[47]|5[3467]|6[279]|7[35]|8[268]|9[245])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3[2-489]\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:22\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'692' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-6])/i',
					'format' => '$1-$2',
				],
			],
			'countries' => [
				'MH' => [
					'pattern' => '/^(?:329\\d{4}|(?:[256]\\d|45)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:247|528|625)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:23|54)5|329|45[56])\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:635\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'389' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[347])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d)(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[58])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'MK' => [
					'pattern' => '/^(?:[2-578]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:2(?:[23]\\d|5[0-24578]|6[01]|82)|3(?:1[3-68]|[23][2-68]|4[23568])|4(?:[23][2-68]|4[3-68]|5[2568]|6[25-8]|7[24-68]|8[4-68]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:3555|4(?:60\\d|747)|94(?:[01]\\d|2[0-4]))\\d{3}|7(?:[0-25-8]\\d|3[2-4]|42|9[23])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[02-9]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:0[1-9]|[1-9]\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'223' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4}))$/i',
					'leadingDigits' => '/^(?:67(?:0[09]|[59]9|77|8[89])|74(?:0[02]|44|55))/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[24-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'ML' => [
					'pattern' => '/^(?:[24-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:07[0-8]|12[67])\\d{4}|(?:2(?:02|1[4-689])|4(?:0[0-4]|4[1-39]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:0(?:01|79)|17\\d)\\d{4}|(?:5[01]|[679]\\d|8[239])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'95' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:16|2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[45]|6(?:0[23]|[1-689]|7[235-7])|7(?:[0-4]|5[2-7])|8[1-6])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[12])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[4-7]|8[1-35])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:9(?:2[0-4]|[35-9]|4[137-9]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:92)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'MM' => [
					'pattern' => '/^(?:1\\d{5,7}|95\\d{6}|(?:[4-7]|9[0-46-9])\\d{6,8}|(?:2|8\\d)\\d{5,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
								],
								'localOnly' => [5],
							],
							'pattern' => '/^(?:(?:1(?:(?:2\\d|3[56]|[89][0-6])\\d|4(?:2[2-469]|39|46|6[25]|7[0-3]|83)|6)|2(?:2(?:00|8[34])|4(?:0\\d|2[246]|39|46|62|7[0-3]|83)|51\\d\\d)|4(?:2(?:2\\d\\d|48[0-3])|3(?:20\\d|4(?:70|83)|56)|420\\d|5470)|6(?:0(?:[23]|88\\d)|(?:124|[56]2\\d)\\d|247[23]|3(?:20\\d|470)|4(?:2[04]\\d|47[23])|7(?:(?:3\\d|8[01459])\\d|4(?:39|60|7[013]))))\\d{4}|5(?:2(?:2\\d{5,6}|47[023]\\d{4})|(?:347[23]|4(?:2(?:1|86)|470)|522\\d|6(?:20\\d|483)|7(?:20\\d|48[0-2])|8(?:20\\d|47[02])|9(?:20\\d|47[01]))\\d{4})|7(?:(?:0470|4(?:25\\d|470)|5(?:202|470|96\\d))\\d{4}|1(?:20\\d{4,5}|4(?:70|83)\\d{4}))|8(?:1(?:2\\d{5,6}|4(?:10|7[01]\\d)\\d{3})|2(?:2\\d{5,6}|(?:320|490\\d)\\d{3})|(?:3(?:2\\d\\d|470)|4[24-7]|5(?:2\\d|4[1-9]|51)\\d|6[23])\\d{4})|(?:1[2-6]\\d|4(?:2[24-8]|3[2-7]|[46][2-6]|5[3-5])|5(?:[27][2-8]|3[2-68]|4[24-8]|5[23]|6[2-4]|8[24-7]|9[2-7])|6(?:[19]20|42[03-6]|(?:52|7[45])\\d)|7(?:[04][24-8]|[15][2-7]|22|3[2-4])|8(?:1[2-689]|2[2-8]|[35]2\\d))\\d{4}|25\\d{5,6}|(?:2[2-9]|6(?:1[2356]|[24][2-6]|3[24-6]|5[2-4]|6[2-8]|7[235-7]|8[245]|9[24])|8(?:3[24]|5[245]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:17[01]|9(?:2(?:[0-4]|[56]\\d\\d)|(?:3(?:[0-36]|4\\d)|(?:6\\d|8[89]|9[4-8])\\d|7(?:3|40|[5-9]\\d))\\d|4(?:(?:[0245]\\d|[1379])\\d|88)|5[0-6])\\d)\\d{4}|9[69]1\\d{6}|9(?:[68]\\d|9[089])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80080(?:[01][1-9]|2\\d)\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1333\\d{4}|[12]468\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'976' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[12]1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[57-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:[12]2[1-3])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:[12](?:27|3[2-8]|4[2-68]|5[1-4689])[0-3])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:[12])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'MN' => [
					'pattern' => '/^(?:[12]\\d{7,9}|[57-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
								],
								'localOnly' => [
									4,
									5,
									6,
								],
							],
							'pattern' => '/^(?:[12]2[1-3]\\d{5,6}|7(?:0[0-5]\\d|128)\\d{4}|(?:[12](?:1|27)|5[368])\\d{6}|[12](?:3[2-8]|4[2-68]|5[1-4689])\\d{6,7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:83[01]|920)\\d{5}|(?:5[05]|8[05689]|9[013-9])\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:712[0-79]\\d{4}|7(?:1[013-9]|[5-8]\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'853' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[268])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'MO' => [
					'pattern' => '/^(?:0800\\d{3}|(?:28|[68]\\d)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:28[2-9]|8(?:11|[2-57-9]\\d))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6800[0-79]\\d{3}|6(?:[235]\\d\\d|6(?:0[0-5]|[1-9]\\d)|8(?:0[1-9]|[14-8]\\d|2[5-9]|[39][0-4]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0800\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'596' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[569])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'MQ' => [
					'pattern' => '/^(?:(?:69|80)\\d{7}|(?:59|97)6\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:596(?:[04-7]\\d|10|2[7-9]|3[04-9]|8[09]|9[4-9])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:69(?:6(?:[0-46-9]\\d|5[0-6])|727)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:976(?:6\\d|7[0-367])\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'222' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2-48])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'MR' => [
					'pattern' => '/^(?:(?:[2-4]\\d\\d|800)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:25[08]|35\\d|45[1-7])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[2-4][0-46-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'356' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2357-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'MT' => [
					'pattern' => '/^(?:3550\\d{4}|(?:[2579]\\d\\d|800)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20(?:3[1-4]|6[059])\\d{4}|2(?:0[19]|[1-357]\\d|60)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7(?:210|[79]\\d\\d)|9(?:[29]\\d\\d|69[67]|8(?:1[1-3]|89|97)))\\d{4})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7117\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800[3467]\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:0(?:0(?:37|43)|(?:6\\d|70|9[0168])\\d)|[12]\\d0[1-5])\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3550\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:501\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'230' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-46]|8[013])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'MU' => [
					'pattern' => '/^(?:(?:5|8\\d\\d)\\d{7}|[2-468]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:[0346-8]\\d|1[0-7])|4(?:[013568]\\d|2[4-7])|54(?:[3-5]\\d|71)|6\\d\\d|8(?:14|3[129]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5(?:4(?:2[1-389]|7[1-9])|87[15-8])\\d{4}|5(?:2[5-9]|4[3-589]|[57]\\d|8[0-689]|9[0-8])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:802\\d{7}|80[0-2]\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:30\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3(?:20|9\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'960' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[3467]|9[13-9])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'MV' => [
					'pattern' => '/^(?:(?:800|9[0-57-9]\\d)\\d{7}|[34679]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3(?:0[0-3]|3[0-59])|6(?:[57][02468]|6[024-68]|8[024689]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:46[46]\\d{4}|(?:7\\d|9[13-9])\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4[05]0\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'265' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1[2-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[137-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'MW' => [
					'pattern' => '/^(?:(?:[19]\\d|[23]1|77|88)\\d{7}|1\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[2-9]|21\\d\\d)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:111\\d{6}|(?:31|77|88|9[89])\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'52' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5}))$/i',
					'leadingDigits' => '/^(?:53)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:33|5[56]|81)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1(?:33|5[56]|81))/i',
					'format' => '$2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$2 $3 $4',
				],
			],
			'countries' => [
				'MX' => [
					'pattern' => '/^(?:1(?:(?:44|99)[1-9]|65[0-689])\\d{7}|(?:1(?:[017]\\d|[235][1-9]|4[0-35-9]|6[0-46-9]|8[1-79]|9[1-8])|[2-9]\\d)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [
									'7',
									'8',
								],
							],
							'pattern' => '/^(?:6571\\d{6}|(?:2(?:0[01]|2[1-9]|3[1-35-8]|4[13-9]|7[1-689]|8[1-578]|9[467])|3(?:1[1-79]|[2458][1-9]|3\\d|7[1-8]|9[1-5])|4(?:1[1-57-9]|[25-7][1-9]|3[1-8]|4\\d|8[1-35-9]|9[2-689])|5(?:[56]\\d|88|9[1-79])|6(?:1[2-68]|[2-4][1-9]|5[1-3689]|6[1-57-9]|7[1-7]|8[67]|9[4-8])|7(?:[1-467][1-9]|5[13-9]|8[1-69]|9[17])|8(?:1\\d|2[13-689]|3[1-6]|4[124-6]|6[1246-9]|7[1-378]|9[12479])|9(?:1[346-9]|2[1-4]|3[2-46-8]|5[1348]|6[1-9]|7[12]|8[1-8]|9\\d))\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [
									'7',
									'8',
								],
							],
							'pattern' => '/^(?:6571\\d{6}|(?:1(?:2(?:2[1-9]|3[1-35-8]|4[13-9]|7[1-689]|8[1-578]|9[467])|3(?:1[1-79]|[2458][1-9]|3\\d|7[1-8]|9[1-5])|4(?:1[1-57-9]|[24-7][1-9]|3[1-8]|8[1-35-9]|9[2-689])|5(?:[56]\\d|88|9[1-79])|6(?:1[2-68]|[2-4][1-9]|5[1-3689]|6[1-57-9]|7[1-7]|8[67]|9[4-8])|7(?:[1-467][1-9]|5[13-9]|8[1-69]|9[17])|8(?:1\\d|2[13-689]|3[1-6]|4[124-6]|6[1246-9]|7[1-378]|9[12479])|9(?:1[346-9]|2[1-4]|3[2-46-8]|5[1348]|[69][1-9]|7[12]|8[1-8]))|2(?:2[1-9]|3[1-35-8]|4[13-9]|7[1-689]|8[1-578]|9[467])|3(?:1[1-79]|[2458][1-9]|3\\d|7[1-8]|9[1-5])|4(?:1[1-57-9]|[25-7][1-9]|3[1-8]|4\\d|8[1-35-9]|9[2-689])|5(?:[56]\\d|88|9[1-79])|6(?:1[2-68]|[2-4][1-9]|5[1-3689]|6[1-57-9]|7[1-7]|8[67]|9[4-8])|7(?:[1-467][1-9]|5[13-9]|8[1-69]|9[17])|8(?:1\\d|2[13-689]|3[1-6]|4[124-6]|6[1246-9]|7[1-378]|9[12479])|9(?:1[346-9]|2[1-4]|3[2-46-8]|5[1348]|6[1-9]|7[12]|8[1-8]|9\\d))\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00|88)\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:300\\d{7})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:500\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'60' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[4-79])/i',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:1(?:[02469]|[378][1-9])|8)/i',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1[36-8])/i',
					'format' => '$1-$2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:15)/i',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1-$2 $3',
				],
			],
			'countries' => [
				'MY' => [
					'pattern' => '/^(?:1\\d{8,9}|(?:3\\d|[4-9])\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:3(?:2[0-36-9]|3[0-368]|4[0-278]|5[0-24-8]|6[0-467]|7[1246-9]|8\\d|9[0-57])\\d|4(?:2[0-689]|[3-79]\\d|8[1-35689])|5(?:2[0-589]|[3468]\\d|5[0-489]|7[1-9]|9[23])|6(?:2[2-9]|3[1357-9]|[46]\\d|5[0-6]|7[0-35-9]|85|9[015-8])|7(?:[2579]\\d|3[03-68]|4[0-8]|6[5-9]|8[0-35-9])|8(?:[24][2-8]|3[2-5]|5[2-7]|6[2-589]|7[2-578]|[89][2-9])|9(?:0[57]|13|[25-7]\\d|[3489][0-8]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:1888[69]|4400|8(?:47|8[27])[0-4])\\d{4}|1(?:0(?:[23568]\\d|4[0-6]|7[016-9]|9[0-8])|1(?:[1-5]\\d\\d|6(?:0[5-9]|[1-9]\\d)|7(?:[0134]\\d|2[1-9]|5[0-6]))|(?:(?:[269]|59)\\d|[37][1-9]|4[235-9])\\d|8(?:1[23]|[236]\\d|4[06]|5[7-9]|7[016-9]|8[01]|9[0-8]))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1[378]00\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1600\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:15(?:4(?:6[0-4]\\d|8(?:0[125]|[17]\\d|21|3[01]|4[01589]|5[014]|6[02]))|6(?:32[0-6]|78\\d))\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'258' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:2|8[2-79])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'MZ' => [
					'pattern' => '/^(?:(?:2|8\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[1346]\\d|5[0-2]|[78][12]|93)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[2-79]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'264' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:88)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:87)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'NA' => [
					'pattern' => '/^(?:[68]\\d{7,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:64426\\d{3}|6(?:1(?:2[2-7]|3[01378]|4[0-4])|254|32[0237]|4(?:27|41|5[25])|52[236-8]|626|7(?:2[2-4]|30))\\d{4,5}|6(?:1(?:(?:0\\d|2[0189]|3[24-69]|4[5-9])\\d|17|69|7[014])|2(?:17|5[0-36-8]|69|70)|3(?:17|2[14-689]|34|6[289]|7[01]|81)|4(?:17|2[0-2]|4[06]|5[0137]|69|7[01])|5(?:17|2[0459]|69|7[01])|6(?:17|25|38|42|69|7[01])|7(?:17|2[569]|3[13]|6[89]|7[01]))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:60|8[1245])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8701\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:3\\d\\d|86)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'687' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3}))$/i',
					'leadingDigits' => '/^(?:5[6-8])/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2-57-9])/i',
					'format' => '$1.$2.$3',
				],
			],
			'countries' => [
				'NC' => [
					'pattern' => '/^(?:[2-57-9]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[03-9]|3[0-5]|4[1-7]|88)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:5[0-4]|[79]\\d|8[0-79])\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:36\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'227' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:08)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[089]|2[013]|7[04])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'NE' => [
					'pattern' => '/^(?:[027-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:0(?:20|3[1-8]|4[13-5]|5[14]|6[14578]|7[1-578])|1(?:4[145]|5[14]|6[14-68]|7[169]|88))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:23|7[04]|[89]\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:08\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:09\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'672' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1[0-3])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[13])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'NF' => [
					'pattern' => '/^(?:[13]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [5],
							],
							'pattern' => '/^(?:(?:1(?:06|17|28|39)|3[0-2]\\d)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [5],
							],
							'pattern' => '/^(?:(?:14|3[58])\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'234' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:78)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[12]|9(?:0[3-9]|[1-9]))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:[3-7]|8[2-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[7-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:[78])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5})(\\d{5,6}))$/i',
					'leadingDigits' => '/^(?:[78])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'NG' => [
					'pattern' => '/^(?:(?:[124-7]|9\\d{3})\\d{6}|[1-9]\\d{7}|[78]\\d{9,13})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:(?:(?:[1-356]\\d|4[02-8]|8[2-9])\\d|9(?:0[3-9]|[1-9]\\d))\\d{5}|7(?:0(?:[013-689]\\d|2[0-24-9])\\d{3,4}|[1-79]\\d{6})|(?:[12]\\d|4[147]|5[14579]|6[1578]|7[1-3578])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:702[0-24-9]|8(?:01|19)[01])\\d{6}|(?:70[13-689]|8(?:0[2-9]|1[0-8])|9(?:0[1-9]|1[2356]))\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									11,
									12,
									13,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7,11})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									10,
									11,
									12,
									13,
									14,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:700\\d{7,11})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'505' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[125-8])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'NI' => [
					'pattern' => '/^(?:(?:1800|[25-8]\\d{3})\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:5(?:5[0-7]|[78]\\d)|6(?:20|3[035]|4[045]|5[05]|77|8[1-9]|9[059])|(?:7[5-8]|8\\d)\\d)\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'31' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1[238]|[34])/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:14)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4,7}))$/i',
					'leadingDigits' => '/^(?:[89]0)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:66)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{8}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1[16-8]|2[259]|3[124]|4[17-9]|5[124679])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-57-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'NL' => [
					'pattern' => '/^(?:(?:[124-7]\\d\\d|3(?:[02-9]\\d|1[0-8]))\\d{6}|[89]\\d{6,9}|1\\d{4,5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:[035]\\d|1[13-578]|6[124-8]|7[24]|8[0-467])|2(?:[0346]\\d|2[2-46-9]|5[125]|9[479])|3(?:[03568]\\d|1[3-8]|2[01]|4[1-8])|4(?:[0356]\\d|1[1-368]|7[58]|8[15-8]|9[23579])|5(?:[0358]\\d|[19][1-9]|2[1-57-9]|4[13-8]|6[126]|7[0-3578])|7\\d\\d)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[1-58]\\d{7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:66\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4,7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[069]\\d{4,7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:85|91)\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:140(?:1[035]|2[0346]|3[03568]|4[0356]|5[0358]|8[458])|(?:140(?:1[16-8]|2[259]|3[124]|4[17-9]|5[124679]|7)|8[478]\\d{6})\\d)$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'47' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[489]|59)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[235-7])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'NO' => [
					'pattern' => '/^(?:(?:0|[2-9]\\d{3})\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[1-4]|3[1-3578]|5[1-35-7]|6[1-4679]|7[0-8])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4[015-8]|59|9\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[01]\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:82[09]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:810(?:0[0-6]|[2-8]\\d)\\d{3})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:880\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:85[0-5]\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0[2-9]|81(?:0(?:0[7-9]|1\\d)|5\\d\\d))\\d{3})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:81[23]\\d{5})$/i',
						],
					],
				],
				'SJ' => [
					'pattern' => '/^(?:0\\d{4}|(?:[489]\\d|[57]9)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:79\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4[015-8]|59|9\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[01]\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:82[09]\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:810(?:0[0-6]|[2-8]\\d)\\d{3})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:880\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:85[0-5]\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:0[2-9]|81(?:0(?:0[7-9]|1\\d)|5\\d\\d))\\d{3})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:81[23]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => 'NO',
		],
		'977' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{7}))$/i',
					'leadingDigits' => '/^(?:1[2-6])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:1[01]|[2-8]|9(?:[1-579]|6[2-6]))/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
			],
			'countries' => [
				'NP' => [
					'pattern' => '/^(?:(?:1\\d|9)\\d{9}|[1-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:1[0-6]\\d|99[02-6])\\d{5}|(?:2[13-79]|3[135-8]|4[146-9]|5[135-7]|6[13-9]|7[15-9]|8[1-46-9]|9[1-7])[2-6]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:6[0-3]|7[245]|8[0-24-68])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:66001|800\\d\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'674' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[4-68])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'NR' => [
					'pattern' => '/^(?:(?:444|(?:55|8\\d)\\d|666)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:444\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:55[3-9]|666|8\\d\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'683' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'NU' => [
					'pattern' => '/^(?:(?:[47]|888\\d)\\d{3})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [4],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[47]\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:888[4-9]\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'64' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,8}))$/i',
					'leadingDigits' => '/^(?:8[1-579])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:50(?:[0367]|88)|[89]0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:24|[346]|7[2-57-9]|9[2-9])/i',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:2(?:10|74)|[59]|80)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1|2[028])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,5}))$/i',
					'leadingDigits' => '/^(?:2(?:[169]|7[0-35-9])|7|86)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'NZ' => [
					'pattern' => '/^(?:[29]\\d{7,9}|50\\d{5}(?:\\d{2,3})?|6[0-35-9]\\d{6}|7\\d{7,8}|8\\d{4,9}|(?:11\\d|[34])\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:24099\\d{3}|(?:3[2-79]|[49][2-9]|6[235-9]|7[2-57-9])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[0-27-9]\\d{7,8}|21\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[28]6\\d{6,7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:508\\d{6,7}|80\\d{6,8})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:11\\d{5}|50(?:0[08]|30|66|77|88))\\d{3}|90\\d{6,8})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:1[6-9]|22|3\\d|4[045]|5[459]|7[0-3579]|90)\\d{2,7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'968' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:[58])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[179])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'OM' => [
					'pattern' => '/^(?:(?:1505|[279]\\d{3}|500)\\d{4}|800\\d{5,6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[2-6]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1505\\d{4}|(?:7(?:[1289]\\d|70)|9(?:0[1-9]|[1-9]\\d))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8007\\d{4,5}|(?:500|800[05])\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'507' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-57-9])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[68])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'PA' => [
					'pattern' => '/^(?:(?:00800|8\\d{3})\\d{6}|[68]\\d{7}|[1-57-9]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:0\\d|1[479]|2[37]|3[0137]|4[17]|5[05]|6[58]|7[0167]|8[258]|9[139])|2(?:[0235-79]\\d|1[0-7]|4[013-9]|8[026-9])|3(?:[089]\\d|1[014-7]|2[0-5]|33|4[0-79]|55|6[068]|7[03-8])|4(?:00|3[0-579]|4\\d|7[0-57-9])|5(?:[01]\\d|2[0-7]|[56]0|79)|7(?:0[09]|2[0-26-8]|3[03]|4[04]|5[05-9]|6[056]|7[0-24-9]|8[6-9]|90)|8(?:09|2[89]|3\\d|4[0-24-689]|5[014]|8[02])|9(?:0[5-9]|1[0135-8]|2[036-9]|3[35-79]|40|5[0457-9]|6[05-9]|7[04-9]|8[35-8]|9\\d))\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[16]1|21[89]|6(?:[02-9]\\d|1[0-8])\\d|8(?:1[01]|7[23]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									8,
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4,5}|(?:00800|800\\d)\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:8(?:22|55|60|7[78]|86)|9(?:00|81))\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'51' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:80)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{7}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[4-8])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'PE' => [
					'pattern' => '/^(?:(?:[14-8]|9\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:(?:(?:4[34]|5[14])[0-8]\\d|7(?:173|3[0-8]\\d)|8(?:10[05689]|6(?:0[06-9]|1[6-9]|29)|7(?:0[569]|[56]0)))\\d{4}|(?:1[0-8]|4[12]|5[236]|6[1-7]|7[246]|8[2-4])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9\\d{8})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:805\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:801\\d{5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[24]\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'689' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:44)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:4|8[7-9])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'PF' => [
					'pattern' => '/^(?:4\\d{5}(?:\\d{2})?|8\\d{7,8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4(?:0[4-689]|9[4-68])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[7-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:499\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:44\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'675' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:18|[2-69]|85)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[78])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'PG' => [
					'pattern' => '/^(?:(?:180|[78]\\d{3})\\d{4}|(?:[2-589]\\d|64)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:3[0-2]|4[257]|5[34]|9[78])\\d|64[1-9]|85[02-46-9])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7\\d|8[18])\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:27[01]\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:180\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:0[0-47]|7[568])\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'63' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{5}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:3(?:230|397|461)|4(?:2(?:35|[46]4|51)|396|4(?:22|63)|59[347]|76[15])|5(?:221|446)|642[23]|8(?:622|8(?:[24]2|5[13])))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:3469|4(?:279|9(?:30|56))|8834)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[3-7]|8[2-8])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{1,2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'PH' => [
					'pattern' => '/^(?:(?:[2-7]|9\\d)\\d{8}|2\\d{5}|(?:1800|8)\\d{7,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									8,
									9,
									10,
								],
								'localOnly' => [
									'4',
									'5',
									'7',
								],
							],
							'pattern' => '/^(?:(?:(?:2[3-8]|3[2-68]|4[2-9]|5[2-6]|6[2-58]|7[24578])\\d{3}|88(?:22\\d\\d|42))\\d{4}|(?:2|8[2-8]\\d\\d)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:8(?:1[37]|9[5-8])|9(?:0[5-9]|1[0-24-9]|[2357]\\d|4[2-9]|6[0-35-9]|8[135-9]|9[1-9]))\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									11,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{7,9})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'92' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2,7}))$/i',
					'leadingDigits' => '/^(?:[89]0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6,7}))$/i',
					'leadingDigits' => '/^(?:9(?:2[3-8]|98)|(?:2(?:3[2358]|4[2-4]|9[2-8])|45[3479]|54[2-467]|60[468]|72[236]|8(?:2[2-689]|3[23578]|4[3478]|5[2356])|9(?:22|3[27-9]|4[2-6]|6[3569]|9[25-7]))[2-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7,8}))$/i',
					'leadingDigits' => '/^(?:(?:2[125]|4[0-246-9]|5[1-35-7]|6[1-8]|7[14]|8[16]|91)[2-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:58)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:3)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2[125]|4[0-246-9]|5[1-35-7]|6[1-8]|7[14]|8[16]|91)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[24-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'PK' => [
					'pattern' => '/^(?:122\\d{6}|[24-8]\\d{10,11}|9(?:[013-9]\\d{8,10}|2(?:[01]\\d\\d|2(?:[06-8]\\d|1[01]))\\d{7})|(?:[2-8]\\d{3}|92(?:[0-7]\\d|8[1-9]))\\d{6}|[24-9]\\d{8}|[89]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [
									5,
									6,
									7,
									8,
								],
							],
							'pattern' => '/^(?:(?:(?:21|42)[2-9]|58[126])\\d{7}|(?:2[25]|4[0146-9]|5[1-35-7]|6[1-8]|7[14]|8[16]|91)[2-9]\\d{6,7}|(?:2(?:3[2358]|4[2-4]|9[2-8])|45[3479]|54[2-467]|60[468]|72[236]|8(?:2[2-689]|3[23578]|4[3478]|5[2356])|9(?:2[2-8]|3[27-9]|4[2-6]|6[3569]|9[25-8]))[2-9]\\d{5,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3(?:[014]\\d|2[0-5]|3[0-7]|55|64)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5}(?:\\d{3})?)$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:122\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:[125]|3[2358]|4[2-4]|9[2-8])|4(?:[0-246-9]|5[3479])|5(?:[1-35-7]|4[2-467])|6(?:0[468]|[1-8])|7(?:[14]|2[236])|8(?:[16]|2[2-689]|3[23578]|4[3478]|5[2356])|9(?:1|22|3[27-9]|4[2-6]|6[3569]|9[2-7]))111\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'48' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5}))$/i',
					'leadingDigits' => '/^(?:19)/i',
					'format' => '$1',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:11|64)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:(?:1[2-8]|2[2-69]|3[2-4]|4[1-468]|5[24-689]|6[1-3578]|7[14-7]|8[1-79]|9[145])19)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:64)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:21|39|45|5[0137]|6[0469]|7[02389]|8(?:0[14]|8))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:1[2-8]|[2-7]|8[1-79]|9[145])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'PL' => [
					'pattern' => '/^(?:6\\d{5}(?:\\d{2})?|8\\d{9}|[1-9]\\d{6}(?:\\d{2})?)$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:47\\d{7}|(?:1[2-8]|2[2-69]|3[2-4]|4[1-468]|5[24-689]|6[1-3578]|7[14-7]|8[1-79]|9[145])(?:[02-9]\\d{6}|1(?:[0-8]\\d{5}|9\\d{3}(?:\\d{2})?)))$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:211(?:1\\d|3[1-5])\\d{4}|(?:45|5[0137]|6[069]|7[2389]|88)\\d{7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:64\\d{4,7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6,7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70[01346-8]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:801\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:39\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:804\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'508' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[45])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'PM' => [
					'pattern' => '/^(?:(?:[45]|80\\d\\d)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4[1-3]|50)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4[02-4]|5[05])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'970' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2489])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'PS' => [
					'pattern' => '/^(?:[2489]2\\d{6}|(?:1\\d|5)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:22[2-47-9]|42[45]|82[014-68]|92[3569])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[69]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1700\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'351' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2[12])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:16|[236-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'PT' => [
					'pattern' => '/^(?:1693\\d{5}|(?:[26-9]\\d|30)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[12]\\d|[35][1-689]|4[1-59]|6[1-35689]|7[1-9]|8[1-69]|9[1256])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[0356]92(?:30|9\\d)\\d{3}|(?:(?:16|6[0356])93|9(?:[1-36]\\d\\d|480))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[02]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6(?:0[178]|4[68])\\d|76(?:0[1-57]|1[2-47]|2[237]))\\d{5})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:8\\d|9[1579])\\d{5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:884[0-4689]\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:30\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70(?:7\\d|8[17])\\d{5})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:600\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'680' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'PW' => [
					'pattern' => '/^(?:(?:[24-8]\\d\\d|345|900)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:55|77)|345|488|5(?:35|44|87)|6(?:22|54|79)|7(?:33|47)|8(?:24|55|76)|900)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:46[0-5]|6[2-4689]0)\\d{4}|(?:45|77|88)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'595' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,6}))$/i',
					'leadingDigits' => '/^(?:[2-9]0)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[26]1|3[289]|4[1246-8]|7[1-3]|8[1-36])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:2[279]|3[13-5]|4[359]|5|6(?:[34]|7[1-46-8])|7[46-8]|85)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:2[14-68]|3[26-9]|4[1246-8]|6(?:1|75)|7[1-35]|8[1-36])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:87)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:9(?:[5-79]|8[1-6]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-8])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'PY' => [
					'pattern' => '/^(?:59\\d{4,6}|9\\d{5,10}|(?:[2-46-8]\\d|5[0-8])\\d{4,7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [
									'5',
									'6',
								],
							],
							'pattern' => '/^(?:(?:[26]1|3[289]|4[1246-8]|7[1-3]|8[1-36])\\d{5,7}|(?:2(?:2[4-68]|[4-68]\\d|7[15]|9[1-5])|3(?:18|3[167]|4[2357]|51|[67]\\d)|4(?:3[12]|5[13]|9[1-47])|5(?:[1-4]\\d|5[02-4])|6(?:3[1-3]|44|7[1-8])|7(?:4[0-4]|5\\d|6[1-578]|75|8[0-8])|858)\\d{5,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:51|6[129]|[78][1-6]|9[1-5])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9800\\d{5,7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8700[0-4]\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[2-9]0\\d{4,7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'974' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2[126]|8)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-7])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'QA' => [
					'pattern' => '/^(?:[2-7]\\d{7}|800\\d{4}(?:\\d{2})?|2\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4141\\d{4}|(?:23|4[04])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:28|[35-7]\\d)\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[12]\\d|61)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4}(?:\\d{2})?)$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'262' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2689])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'RE' => [
					'pattern' => '/^(?:9769\\d{5}|(?:26|[68]\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:26(?:2\\d\\d|30[0-5])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:69(?:2\\d\\d|3(?:[06][0-46]|1[013]|2[0-2]|3[0-39]|4\\d|5[0-5]|7[0-27]|8[0-8]|9[0-479]))|9769\\d)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:89[1-37-9]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:1[019]|2[0156]|84|90)\\d{6})$/i',
						],
					],
				],
				'YT' => [
					'pattern' => '/^(?:80\\d{7}|(?:26|63)9\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:269(?:0[67]|5[0-3]|6\\d|[78]0)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:639(?:0[0-79]|1[019]|[267]\\d|3[09]|40|5[05-9]|9[04-79])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => 'RE',
		],
		'40' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2[3-6]\\d9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:219|31)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[23]1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[237-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'RO' => [
					'pattern' => '/^(?:(?:[2378]\\d|90)\\d{7}|[23]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[23][13-6]\\d{7}|(?:2(?:19\\d|[3-6]\\d9)|31\\d\\d)\\d\\d)$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7020\\d{5}|7(?:0[013-9]|1[0-3]|[2-7]\\d|8[03-8]|9[019])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[0136]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:801\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:37\\d|80[578])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'381' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,9}))$/i',
					'leadingDigits' => '/^(?:(?:2[389]|39)0|[7-9])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5,10}))$/i',
					'leadingDigits' => '/^(?:[1-36])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'RS' => [
					'pattern' => '/^(?:38[02-9]\\d{6,9}|6\\d{7,9}|90\\d{4,8}|38\\d{5,6}|(?:7\\d\\d|800)\\d{3,9}|(?:[12]\\d|3[0-79])\\d{5,10})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [
									4,
									5,
									6,
								],
							],
							'pattern' => '/^(?:(?:11[1-9]\\d|(?:2[389]|39)(?:0[2-9]|[2-9]\\d))\\d{3,8}|(?:1[02-9]|2[0-24-7]|3[0-8])[2-9]\\d{4,9})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6(?:[0-689]|7\\d)\\d{6,7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{3,9})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:78\\d|90[0169])\\d{3,7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[06]\\d{4,10})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'250' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[7-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'RW' => [
					'pattern' => '/^(?:(?:06|[27]\\d\\d|[89]00)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:06|2[23568]\\d)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[2389]\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'966' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:81)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SA' => [
					'pattern' => '/^(?:92\\d{7}|(?:[15]|8\\d)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:1(?:1\\d|2[24-8]|3[35-8]|4[3-68]|6[2-5]|7[235-7])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:579[01]\\d{5}|5(?:[013-689]\\d|7[0-36-8])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:925\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:920\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:811\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'677' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:7|8[4-9]|9(?:[1-8]|9[0-8]))/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'SB' => [
					'pattern' => '/^(?:(?:[1-6]|[7-9]\\d\\d)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[4-79]|[23]\\d|4[0-2]|5[03]|6[0-37])\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									5,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:48\\d{3}|(?:(?:7[1-9]|8[4-9])\\d|9(?:1[2-9]|2[013-9]|3[0-2]|[46]\\d|5[0-46-9]|7[0-689]|8[0-79]|9[0-8]))\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1[38]\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[12]\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'248' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[246]|9[57])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SC' => [
					'pattern' => '/^(?:8000\\d{3}|(?:[249]\\d|64)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4[2-46]\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[5-8]\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8000\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:971\\d{4}|(?:64|95)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'249' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[19])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SD' => [
					'pattern' => '/^(?:[19]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:5\\d|8[35-7])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[0-2]|9[0-3569])\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'46' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2,3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:20)/i',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9(?:00|39|44))/i',
					'format' => '$1-$2',
					'intlFormat' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[12][136]|3[356]|4[0246]|6[03]|90[1-9])/i',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{2,3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2,3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:1[2457]|2(?:[247-9]|5[0138])|3[0247-9]|4[1357-9]|5[0-35-9]|6(?:[125689]|4[02-57]|7[0-2])|9(?:[125-8]|3[02-5]|4[0-3]))/i',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2,3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9(?:00|39|44))/i',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2,3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:1[13689]|2[0136]|3[1356]|4[0246]|54|6[03]|90[1-9])/i',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:10|7)/i',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[13-5]|2(?:[247-9]|5[0138])|6(?:[124-689]|7[0-2])|9(?:[125-8]|3[02-5]|4[0-3]))/i',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[26])/i',
					'format' => '$1-$2 $3 $4 $5',
					'intlFormat' => '$1 $2 $3 $4 $5',
				],
			],
			'countries' => [
				'SE' => [
					'pattern' => '/^(?:(?:[26]\\d\\d|9)\\d{9}|[1-9]\\d{8}|[1-689]\\d{7}|[1-4689]\\d{6}|2\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:[12][136]|3[356]|4[0246]|6[03]|8\\d)\\d|90[1-9])\\d{4,6}|(?:1(?:2[0-35]|4[0-4]|5[0-25-9]|7[13-6]|[89]\\d)|2(?:2[0-7]|4[0136-8]|5[0138]|7[018]|8[01]|9[0-57])|3(?:0[0-4]|1\\d|2[0-25]|4[056]|7[0-2]|8[0-3]|9[023])|4(?:1[013-8]|3[0135]|5[14-79]|7[0-246-9]|8[0156]|9[0-689])|5(?:0[0-6]|[15][0-5]|2[0-68]|3[0-4]|4\\d|6[03-5]|7[013]|8[0-79]|9[01])|6(?:1[1-3]|2[0-4]|4[02-57]|5[0-37]|6[0-3]|7[0-2]|8[0247]|9[0-356])|9(?:1[0-68]|2\\d|3[02-5]|4[0-3]|5[0-4]|[68][01]|7[0135-8]))\\d{5,6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[02369]\\d{7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:74[02-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20\\d{4,7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:649\\d{6}|9(?:00|39|44)[1-8]\\d{3,6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:77[0-7]\\d{6})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:75[1-8]\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:10[1-8]\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [12],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:25[245]|67[3-68])\\d{9})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'65' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:1(?:[013-8]|9(?:0[1-9]|[1-9]))|77)/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[369]|8(?:0[1-4]|[1-9]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SG' => [
					'pattern' => '/^(?:(?:(?:1\\d|8)\\d\\d|7000)\\d{7}|[3689]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:662[0-24-9]\\d{4}|6(?:[1-578]\\d|6[013-57-9]|9[0-35-9])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:04[0-35-79]|95[0-2])\\d{4}|(?:8(?:0[1-3]|[1-8]\\d|9[0-4])|9[0-8]\\d)\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:18|8)00\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1900\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3[12]\\d|666)\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7000\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'290' => [
			'formats' => [],
			'countries' => [
				'SH' => [
					'pattern' => '/^(?:(?:[256]\\d|8)\\d{3})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									4,
									5,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[0-57-9]\\d|6[4-9])\\d\\d)$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[56]\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:262\\d\\d)$/i',
						],
					],
				],
				'TA' => [
					'pattern' => '/^(?:8\\d{3})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [4],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => 'SH',
		],
		'386' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,6}))$/i',
					'leadingDigits' => '/^(?:8[09]|9)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:59|8)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[37][01]|4[0139]|51|6)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[1-57])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'SI' => [
					'pattern' => '/^(?:[1-7]\\d{7}|8\\d{4,7}|90\\d{4,6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:[1-357][2-8]|4[24-8])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:65(?:1\\d|55|[67]0)\\d{4}|(?:[37][01]|4[0139]|51|6[489])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									6,
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{4,6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:89[1-3]\\d{2,5}|90\\d{4,6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:59\\d\\d|8(?:1(?:[67]\\d|8[0-489])|2(?:0\\d|2[0-37-9]|8[0-2489])|3[389]\\d))\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'421' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{2})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:21)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:[3-5][1-8]1[67])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9090)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1/$2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[689])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[3-5])/i',
					'format' => '$1/$2 $3 $4',
				],
			],
			'countries' => [
				'SK' => [
					'pattern' => '/^(?:[2-689]\\d{8}|[2-59]\\d{6}|[2-5]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:16|[2-9]\\d{3})|(?:(?:[3-5][1-8]\\d|819)\\d|601[1-5])\\d)\\d{4}|(?:2|[3-5][1-8])1[67]\\d{3}|[3-5][1-8]16\\d\\d)$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:909[1-9]\\d{5}|9(?:0[1-8]|1[0-24-9]|4[03-57-9]|5\\d)\\d{6})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9090\\d{3})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:00|[78]\\d)\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[5-9]\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6(?:02|5[0-4]|9[0-6])\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:96\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'232' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[236-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'SL' => [
					'pattern' => '/^(?:(?:[237-9]\\d|66)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:22[2-4][2-9]\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:25|3[0-5]|66|7[3-9]|8[08]|9[09])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'378' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[5-7])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'SM' => [
					'pattern' => '/^(?:(?:0549|[5-7]\\d)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:0549(?:8[0157-9]|9\\d)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[16]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[178]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:5[158]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'221' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[379])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'SN' => [
					'pattern' => '/^(?:(?:[378]\\d|93)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3(?:0(?:1[0-2]|80)|282|3(?:8[1-9]|9[3-9])|611)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:75(?:01|[38]3)\\d{5}|7(?:[06-8]\\d|21|5[4-7]|90)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:88[4689]\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:81[02468]\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3(?:392|9[01]\\d)\\d|93(?:3[13]0|929))\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'252' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8[125])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[134])/i',
					'format' => '$1',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[15]|2[0-79]|3[0-46-8]|4[0-7])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{7}))$/i',
					'leadingDigits' => '/^(?:24|[67])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[3478]|64|90)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5,7}))$/i',
					'leadingDigits' => '/^(?:1|28|6(?:0[5-7]|[1-35-9])|9[2-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'SO' => [
					'pattern' => '/^(?:[346-9]\\d{8}|[12679]\\d{7}|[1-5]\\d{6}|[1348]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1\\d|2[0-79]|3[0-46-8]|4[0-7]|5[57-9])\\d{5}|(?:[134]\\d|8[125])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:15|(?:3[59]|4[89]|79|8[08])\\d|6(?:0[5-7]|[1-9]\\d)|9(?:0\\d|[2-9]))\\d|2(?:4\\d|8))\\d{5}|(?:6\\d|7[1-9])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'597' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:56)/i',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-5])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[6-8])/i',
					'format' => '$1-$2',
				],
			],
			'countries' => [
				'SR' => [
					'pattern' => '/^(?:(?:[2-5]|68|[78]\\d)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									6,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[1-3]|3[0-7]|(?:4|68)\\d|5[2-58])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7[124-7]|8[124-9])\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:56\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'211' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[19])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SS' => [
					'pattern' => '/^(?:[19]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1[89]\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:12|9[1257-9])\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'239' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[29])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'ST' => [
					'pattern' => '/^(?:(?:22|9\\d)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:22\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[5-9]\\d{3}|9(?:0[1-9]|[89]\\d)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'503' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[267])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SV' => [
					'pattern' => '/^(?:[267]\\d{7}|[89]00\\d{4}(?:\\d{4})?)$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:[1-6]\\d{3}|[79]90[034]|890[0245])\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:66(?:[02-9]\\d\\d|1(?:[02-9]\\d|16))\\d{3}|(?:6[0-57-9]|7\\d)\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{4}(?:\\d{4})?)$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{4}(?:\\d{4})?)$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'963' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[1-5])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'SY' => [
					'pattern' => '/^(?:[1-39]\\d{8}|[1-5]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [
									'6',
									'7',
								],
							],
							'pattern' => '/^(?:21\\d{6,7}|(?:1(?:[14]\\d|[2356])|2[235]|3(?:[13]\\d|4)|4[134]|5[1-3])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:22|[3-689]\\d)\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'268' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[0237])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{5})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'SZ' => [
					'pattern' => '/^(?:0800\\d{4}|(?:[237]\\d|900)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[23][2-5]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[6-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0800\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'235' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[2679])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'TD' => [
					'pattern' => '/^(?:(?:22|[69]\\d|77)\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:22(?:[37-9]0|5[0-5]|6[89])\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6[023568]|77|9\\d)\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'228' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[279])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'TG' => [
					'pattern' => '/^(?:[279]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:2[2-7]|3[23]|4[45]|55|6[67]|77)\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7[09]|9[0-36-9])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'66' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[13-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'TH' => [
					'pattern' => '/^(?:(?:001800|[2-57]|[689]\\d)\\d{7}|1\\d{7,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1[0689]|2\\d|3[2-9]|4[2-5]|5[2-6]|7[3-7])\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:671[0-3]\\d{5}|(?:14|6[1-6]|[89]\\d)\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:001800\\d|1800)\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1900\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[08]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'992' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{6})(\\d)(\\d{2}))$/i',
					'leadingDigits' => '/^(?:3317)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[34]7|91[78])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d)(\\d{4}))$/i',
					'leadingDigits' => '/^(?:3[1-5])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[0-57-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'TJ' => [
					'pattern' => '/^(?:(?:00|[1-57-9]\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [
									'3',
									5,
									6,
									7,
								],
							],
							'pattern' => '/^(?:(?:3(?:1[3-5]|2[245]|3[12]|4[24-7]|5[25]|72)|4(?:46|74|87))\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:41[18]\\d{6}|(?:[034]0|1[01]|2[02]|5[05]|7[07]|8[08]|9\\d)\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'690' => [
			'formats' => [],
			'countries' => [
				'TK' => [
					'pattern' => '/^(?:[2-47]\\d{3,6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									4,
									5,
									6,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[2-4]|[34]\\d)\\d{2,5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									4,
									5,
									6,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[2-4]\\d{2,5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'670' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[2-489]|70)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'TL' => [
					'pattern' => '/^(?:7\\d{7}|(?:[2-47]\\d|[89]0)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[1-5]|3[1-9]|4[1-4])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[2-8]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d{5})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:70\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'993' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:12)/i',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d)(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[1-5])/i',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:6)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'TM' => [
					'pattern' => '/^(?:[1-6]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:2\\d|3[1-9])|2(?:22|4[0-35-8])|3(?:22|4[03-9])|4(?:22|3[128]|4\\d|6[15])|5(?:22|5[7-9]|6[014-689]))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'216' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-57-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'TN' => [
					'pattern' => '/^(?:[2-57-9]\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:81200\\d{3}|(?:3[0-2]|7\\d)\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:3(?:001|[12]40)\\d{4}|(?:(?:[259]\\d|4[0-7])\\d|3(?:1[1-35]|6[0-4]|91))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8010\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:88\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8[12]10\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'676' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-4]|50|6[09]|7[0-24-69]|8[05])/i',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:0)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[5-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'TO' => [
					'pattern' => '/^(?:(?:0800|(?:[5-8]\\d\\d|999)\\d)\\d{3}|[2-8]\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2\\d|3[0-8]|4[0-4]|50|6[09]|7[0-24-69]|8[05])\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:55[4-6]|6(?:[09]\\d|3[02]|8[15-9])|(?:7\\d|8[46-9])\\d|999)\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:0800\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:55[0-37-9]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'90' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d)(\\d{3}))$/i',
					'leadingDigits' => '/^(?:444)/i',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:512|8[01589]|90)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:5(?:[0-59]|6161))/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[24][1-8]|3[1-9])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{6,7}))$/i',
					'leadingDigits' => '/^(?:80)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'TR' => [
					'pattern' => '/^(?:4\\d{6}|8\\d{11,12}|(?:[2-58]\\d\\d|900)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:[13][26]|[28][2468]|[45][268]|[67][246])|3(?:[13][28]|[24-6][2468]|[78][02468]|92)|4(?:[16][246]|[23578][2468]|4[26]))\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:56161\\d{5}|5(?:0[15-7]|1[06]|24|[34]\\d|5[1-59]|9[46])\\d{7})$/i',
						],
						'pager' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:512\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									10,
									12,
									13,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:00\\d{7}(?:\\d{2,3})?|11\\d{7}))$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:8[89]8|900)\\d{7})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:592(?:21[12]|461)\\d{4})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:850\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:444\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'688' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:90)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'TV' => [
					'pattern' => '/^(?:(?:2|7\\d\\d|90)\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[02-9]\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									6,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7[01]\\d|90)\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'886' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d)(\\d{4}))$/i',
					'leadingDigits' => '/^(?:202)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[258]0)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[23568]|4(?:0[2-48]|[1-47-9])|(?:400|7)[1-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[49])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'TW' => [
					'pattern' => '/^(?:[2-689]\\d{8}|7\\d{9,10}|[2-8]\\d{7}|2\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[2-8]\\d|370|55[01]|7[1-9])\\d{6}|4(?:(?:0(?:0[1-9]|[2-48]\\d)|1[023]\\d)\\d{4,5}|(?:[239]\\d\\d|4(?:0[56]|12|49))\\d{5})|6(?:[01]\\d{7}|4(?:0[56]|12|24|4[09])\\d{4,5})|8(?:(?:2(?:3\\d|4[0-269]|[578]0|66)|36[24-9]|90\\d\\d)\\d{4}|4(?:0[56]|12|24|4[09])\\d{4,5})|(?:2(?:2(?:0\\d\\d|4(?:0[68]|[249]0|3[0-467]|5[0-25-9]|6[0235689]))|(?:3(?:[09]\\d|1[0-4])|(?:4\\d|5[0-49]|6[0-29]|7[0-5])\\d)\\d)|(?:(?:3[2-9]|5[2-8]|6[0-35-79]|8[7-9])\\d\\d|4(?:2(?:[089]\\d|7[1-9])|(?:3[0-4]|[78]\\d|9[01])\\d))\\d)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:40001[0-2]|9[0-8]\\d{4})\\d{3})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-79]\\d{6}|800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									7,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:20(?:[013-9]\\d\\d|2)\\d{4})$/i',
						],
						'personalNumber' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:99\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [
									10,
									11,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7010(?:[0-2679]\\d|3[0-7]|8[0-5])\\d{5}|70\\d{8})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:50[0-46-9]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'255' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[24])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[67])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'TZ' => [
					'pattern' => '/^(?:(?:[26-8]\\d|41|90)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2[2-8]\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:77[2-9]\\d{6}|(?:6[1-9]|7[1-689])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[08]\\d{6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90\\d{7})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:8(?:40|6[01])\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:41\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'380' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:6[12][29]|(?:35|4[1378]|5[12457]|6[49])2|(?:56|65)[24]|(?:3[1-46-8]|46)2[013-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:4[45][0-5]|5(?:0|6(?:3[14-7]|7))|6(?:[12][018]|[36-8])|7|89|9[1-9]|(?:48|57)[0137-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[3-6])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'UA' => [
					'pattern' => '/^(?:[89]\\d{9}|[3-9]\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [
									5,
									6,
									7,
								],
							],
							'pattern' => '/^(?:(?:3[1-8]|4[13-8]|5[1-7]|6[12459])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:50|6[36-8]|7[1-3]|9[1-9])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800[1-8]\\d{5,6})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900[239]\\d{5,6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:89[1-579]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'256' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:2024)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:[27-9]|4(?:6[45]|[7-9]))/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[34])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'UG' => [
					'pattern' => '/^(?:800\\d{6}|(?:[29]0|[347]\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [
									5,
									6,
									7,
								],
							],
							'pattern' => '/^(?:20(?:(?:(?:24|81)0|30[67])\\d|6(?:00[0-2]|30[0-4]))\\d{3}|(?:20(?:[0147]\\d|2[5-9]|32|5[0-4]|6[15-9])|[34]\\d{3})\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:726[01]\\d{5}|7(?:[0157-9]\\d|20|36|[46][0-4])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800[1-3]\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[1-3]\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'598' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:405|8|90)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:9)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[124])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:4)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'UY' => [
					'pattern' => '/^(?:4\\d{9}|[1249]\\d{7}|(?:[49]\\d|80)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:1(?:770|987)|(?:2\\d|4[2-7])\\d\\d)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9[1-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:4\\d{5}|80[05])\\d{4}|405\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[0-8]\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'998' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[35-9])/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'UZ' => [
					'pattern' => '/^(?:(?:33|55|[679]\\d|88)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:6(?:1(?:22|3[124]|4[1-4]|5[1-3578]|64)|2(?:22|3[0-57-9]|41)|5(?:22|3[3-7]|5[024-8])|6\\d\\d|7(?:[23]\\d|7[69])|9(?:22|4[1-8]|6[135]))|7(?:0(?:5[4-9]|6[0146]|7[124-6]|9[135-8])|(?:1[12]|8\\d)\\d|2(?:22|3[13-57-9]|4[1-3579]|5[14])|3(?:2\\d|3[1578]|4[1-35-7]|5[1-57]|61)|4(?:2\\d|3[1-579]|7[1-79])|5(?:22|5[1-9]|6[1457])|6(?:22|3[12457]|4[13-8])|9(?:22|5[1-9])))\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:(?:33|88|9[0-57-9])\\d{3}|55(?:50[013]|90\\d)|6(?:1(?:2(?:2[01]|98)|35[0-4]|50\\d|61[23]|7(?:[01][017]|4\\d|55|9[5-9]))|2(?:(?:11|7\\d)\\d|2(?:[12]1|9[01379])|5(?:[126]\\d|3[0-4]))|5(?:19[01]|2(?:27|9[26])|(?:30|59|7\\d)\\d)|6(?:2(?:1[5-9]|2[0367]|38|41|52|60)|(?:3[79]|9[0-3])\\d|4(?:56|83)|7(?:[07]\\d|1[017]|3[07]|4[047]|5[057]|67|8[0178]|9[79]))|7(?:2(?:24|3[237]|4[5-9]|7[15-8])|5(?:7[12]|8[0589])|7(?:0\\d|[39][07])|9(?:0\\d|7[079]))|9(?:2(?:1[1267]|3[01]|5\\d|7[0-4])|(?:5[67]|7\\d)\\d|6(?:2[0-26]|8\\d)))|7(?:[07]\\d{3}|1(?:13[01]|6(?:0[47]|1[67]|66)|71[3-69]|98\\d)|2(?:2(?:2[79]|95)|3(?:2[5-9]|6[0-6])|57\\d|7(?:0\\d|1[17]|2[27]|3[37]|44|5[057]|66|88))|3(?:2(?:1[0-6]|21|3[469]|7[159])|(?:33|9[4-6])\\d|5(?:0[0-4]|5[579]|9\\d)|7(?:[0-3579]\\d|4[0467]|6[67]|8[078]))|4(?:2(?:29|5[0257]|6[0-7]|7[1-57])|5(?:1[0-4]|8\\d|9[5-9])|7(?:0\\d|1[024589]|2[0-27]|3[0137]|[46][07]|5[01]|7[5-9]|9[079])|9(?:7[015-9]|[89]\\d))|5(?:112|2(?:0\\d|2[29]|[49]4)|3[1568]\\d|52[6-9]|7(?:0[01578]|1[017]|[23]7|4[047]|[5-7]\\d|8[78]|9[079]))|6(?:2(?:2[1245]|4[2-4])|39\\d|41[179]|5(?:[349]\\d|5[0-2])|7(?:0[017]|[13]\\d|22|44|55|67|88))|9(?:22[128]|3(?:2[0-4]|7\\d)|57[02569]|7(?:2[05-9]|3[37]|4\\d|60|7[2579]|87|9[07]))))\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'58' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[24-689])/i',
					'format' => '$1-$2',
				],
			],
			'countries' => [
				'VE' => [
					'pattern' => '/^(?:[68]00\\d{7}|(?:[24]\\d|[59]0)\\d{8})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:(?:2(?:12|3[457-9]|[467]\\d|[58][1-9]|9[1-6])|[4-6]00)\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4(?:1[24-8]|2[46])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:90[01]\\d{7})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [7],
							],
							'pattern' => '/^(?:501\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'84' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[17]99)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:80)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4,5}))$/i',
					'leadingDigits' => '/^(?:69)/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4,6}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[69])/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[3578])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2[48])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'VN' => [
					'pattern' => '/^(?:[12]\\d{9}|[135-9]\\d{8}|[16]\\d{7}|[16-8]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:2(?:0[3-9]|1[0-689]|2[0-25-9]|3[2-9]|4[2-8]|5[124-9]|6[0-39]|7[0-7]|8[2-79]|9[0-4679])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:5(?:2[238]|59)|89[689]|99[013-9])\\d{6}|(?:3\\d|5[689]|7[06-9]|8[1-8]|9[0-8])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1800\\d{4,6}|12(?:0[13]|28)\\d{4})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [
									8,
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1900\\d{4,6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:672\\d{6})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[17]99|80\\d)\\d{4}|69\\d{5,6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'678' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[57-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'VU' => [
					'pattern' => '/^(?:[57-9]\\d{6}|(?:[238]\\d|48)\\d{3})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [5],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:38[0-8]|48[4-9])\\d\\d|(?:2[02-9]|3[4-7]|88)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[58]\\d|7[013-7])\\d{5})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:9(?:0[1-9]|1[01])\\d{4})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									5,
									7,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:3[03]|900\\d)\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'681' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:[478])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{2})(\\d{2})(\\d{2}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3 $4',
				],
			],
			'countries' => [
				'WF' => [
					'pattern' => '/^(?:(?:40|72)\\d{4}|8\\d{5}(?:\\d{3})?)$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:72\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:72|8[23])\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80[0-5]\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[48]0\\d{4})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'685' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[2-5]|6[1-9])/i',
					'format' => '$1',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,7}))$/i',
					'leadingDigits' => '/^(?:[68])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'WS' => [
					'pattern' => '/^(?:(?:[2-6]|8\\d{5})\\d{4}|[78]\\d{6}|[68]\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									5,
									6,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:6[1-9]\\d{3}|(?:[2-5]|60)\\d{4})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7[1-35-7]|8(?:[3-7]|9\\d{3}))\\d{5})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [6],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{3})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'383' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[89])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[2-4])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[23])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'XK' => [
					'pattern' => '/^(?:[23]\\d{7,8}|(?:4\\d\\d|[89]00)\\d{5})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2[89]|39)0\\d{6}|[23][89]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:4[3-9]\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{5})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:900\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'967' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:[1-6]|7[24-68])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'YE' => [
					'pattern' => '/^(?:(?:1|7\\d)\\d{7}|[1-7]\\d{6})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									7,
									8,
								],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:78[0-7]\\d{4}|17\\d{6}|(?:[12][2-68]|3[2358]|4[2-58]|5[2-6]|6[3-58]|7[24-6])\\d{5})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7[0137]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'27' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:8[1-4])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{2,3}))$/i',
					'leadingDigits' => '/^(?:8[1-4])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:860)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'ZA' => [
					'pattern' => '/^(?:[1-79]\\d{8}|8\\d{4,9})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:2(?:0330|4302)|52087)0\\d{3}|(?:1[0-8]|2[1-378]|3[1-69]|4\\d|5[1346-8])\\d{7})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:1(?:3492[0-25]|4495[0235]|549(?:20|5[01]))|4[34]492[01])\\d{3}|8[1-4]\\d{3,7}|(?:2[27]|47|54)4950\\d{3}|(?:1(?:049[2-4]|9[12]\\d\\d)|(?:6\\d|7[0-46-9])\\d{3}|8(?:5\\d{3}|7(?:08[67]|158|28[5-9]|310)))\\d{4}|(?:1[6-8]|28|3[2-69]|4[025689]|5[36-8])4920\\d{3}|(?:12|[2-5]1)492\\d{4})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80\\d{7})$/i',
						],
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:86[2-9]|9[0-2]\\d)\\d{6})$/i',
						],
						'sharedCost' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:860\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:87(?:08[0-589]|15[0-79]|28[0-4]|31[1-9])\\d{4}|87(?:[02][0-79]|1[0-46-9]|3[02-9]|[4-9]\\d)\\d{5})$/i',
						],
						'uan' => [
							'lengths' => [
								'national' => [
									9,
									10,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:861\\d{6,7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'260' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[28])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:[79])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'ZM' => [
					'pattern' => '/^(?:(?:63|80)0\\d{6}|(?:21|[79]\\d)\\d{7})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [6],
							],
							'pattern' => '/^(?:21[1-8]\\d{6})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:7[679]|9[5-8])\\d{7})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:800\\d{6})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:630\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'263' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3,5}))$/i',
					'leadingDigits' => '/^(?:2(?:0[45]|2[278]|[49]8)|3(?:[09]8|17)|6(?:[29]8|37|75)|[23][78]|(?:33|5[15]|6[68])[78])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{2,4}))$/i',
					'leadingDigits' => '/^(?:[49])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:80)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{7}))$/i',
					'leadingDigits' => '/^(?:2(?:02[014]|4|[56]20|[79]2)|392|5(?:42|525)|6(?:[16-8]21|52[013])|8[13-59])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:7)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:2(?:1[39]|2[0157]|[378]|[56][14])|3(?:123|29))/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:8)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,5}))$/i',
					'leadingDigits' => '/^(?:1|2(?:0[0-36-9]|12|29|[56])|3(?:1[0-689]|[24-6])|5(?:[0236-9]|1[2-4])|6(?:[013-59]|7[0-46-9])|(?:33|55|6[68])[0-69]|(?:29|3[09]|62)[0-79])/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3})(\\d{3,4}))$/i',
					'leadingDigits' => '/^(?:29[013-9]|39|54)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{3,5}))$/i',
					'leadingDigits' => '/^(?:258|5483)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'ZW' => [
					'pattern' => '/^(?:2(?:[0-57-9]\\d{6,8}|6[0-24-9]\\d{6,7})|[38]\\d{9}|[35-8]\\d{8}|[3-6]\\d{7}|[1-689]\\d{6}|[1-3569]\\d{5}|[1356]\\d{4})$/i',
					'patterns' => [
						'fixedLine' => [
							'lengths' => [
								'national' => [
									5,
									6,
									7,
									8,
									9,
									10,
								],
								'localOnly' => [
									'3',
									'4',
								],
							],
							'pattern' => '/^(?:(?:1(?:(?:3\\d|9)\\d|[4-8])|2(?:(?:(?:0(?:2[014]|5)|(?:2[0157]|31|84|9)\\d\\d|[56](?:[14]\\d\\d|20)|7(?:[089]|2[03]|[35]\\d\\d))\\d|4(?:2\\d\\d|8))\\d|1(?:2|[39]\\d{4}))|3(?:(?:123|(?:29\\d|92)\\d)\\d\\d|7(?:[19]|[56]\\d))|5(?:0|1[2-478]|26|[37]2|4(?:2\\d{3}|83)|5(?:25\\d\\d|[78])|[689]\\d)|6(?:(?:[16-8]21|28|52[013])\\d\\d|[39])|8(?:[1349]28|523)\\d\\d)\\d{3}|(?:4\\d\\d|9[2-9])\\d{4,5}|(?:(?:2(?:(?:(?:0|8[146])\\d|7[1-7])\\d|2(?:[278]\\d|92)|58(?:2\\d|3))|3(?:[26]|9\\d{3})|5(?:4\\d|5)\\d\\d)\\d|6(?:(?:(?:[0-246]|[78]\\d)\\d|37)\\d|5[2-8]))\\d\\d|(?:2(?:[569]\\d|8[2-57-9])|3(?:[013-59]\\d|8[37])|6[89]8)\\d{3})$/i',
						],
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:7(?:[178]\\d|3[1-9])\\d{6})$/i',
						],
						'tollFree' => [
							'lengths' => [
								'national' => [7],
								'localOnly' => [],
							],
							'pattern' => '/^(?:80(?:[01]\\d|20|8[0-8])\\d{3})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [10],
								'localOnly' => [],
							],
							'pattern' => '/^(?:86(?:1[12]|22|30|44|55|77|8[368])\\d{6})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'800' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:\\d)/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:(?:005|[1-9]\\d\\d)\\d{5})$/i',
					'patterns' => [
						'tollFree' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:005|[1-9]\\d\\d)\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'808' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1-9])/i',
					'format' => '$1 $2',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:[1-9]\\d{7})$/i',
					'patterns' => [
						'sharedCost' => [
							'lengths' => [
								'national' => [8],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[1-9]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'870' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:[35-7])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:7\\d{11}|[35-7]\\d{8})$/i',
					'patterns' => [
						'mobile' => [
							'lengths' => [
								'national' => [
									9,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:[356]|774[45])\\d{8}|7[6-8]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'878' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:10\\d{10})$/i',
					'patterns' => [
						'voip' => [
							'lengths' => [
								'national' => [12],
								'localOnly' => [],
							],
							'pattern' => '/^(?:10\\d{10})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'881' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{3})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[0-36-9])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:[0-36-9]\\d{8})$/i',
					'patterns' => [
						'mobile' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [],
							],
							'pattern' => '/^(?:[0-36-9]\\d{8})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'882' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{2})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:16|342)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{6}))$/i',
					'leadingDigits' => '/^(?:4)/i',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{2})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[19])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:3[23])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{3,4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:1)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:34[57])/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:34)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{2})(\\d{4,5})(\\d{5}))$/i',
					'leadingDigits' => '/^(?:[1-3])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:[13]\\d{6}(?:\\d{2,5})?|285\\d{9}|(?:[19]\\d|49)\\d{6})$/i',
					'patterns' => [
						'mobile' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:342\\d{4}|(?:337|49)\\d{6}|3(?:2|47|7\\d{3})\\d{7})$/i',
						],
						'voip' => [
							'lengths' => [
								'national' => [
									7,
									8,
									9,
									10,
									11,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:1(?:3(?:0[0347]|[13][0139]|2[035]|4[013568]|6[0459]|7[06]|8[15-8]|9[0689])\\d{4}|6\\d{5,10})|(?:(?:285\\d\\d|3(?:45|[69]\\d{3}))\\d|9[89])\\d{6})$/i',
						],
						'voicemail' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:348[57]\\d{7})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'883' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:510)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:2)/i',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{3})(\\d{3}))$/i',
					'leadingDigits' => '/^(?:510)/i',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(?:(\\d{4})(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:5)/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:210\\d{7}|51\\d{7}(?:\\d{3})?)$/i',
					'patterns' => [
						'voip' => [
							'lengths' => [
								'national' => [
									9,
									10,
									12,
								],
								'localOnly' => [],
							],
							'pattern' => '/^(?:(?:210|51[013]0\\d)\\d{7}|5100\\d{5})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'888' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d{3})(\\d{3})(\\d{5}))$/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:\\d{11})$/i',
					'patterns' => [
						'uan' => [
							'lengths' => [
								'national' => [11],
								'localOnly' => [],
							],
							'pattern' => '/^(?:\\d{11})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
		'979' => [
			'formats' => [
				[
					'pattern' => '/^(?:(\\d)(\\d{4})(\\d{4}))$/i',
					'leadingDigits' => '/^(?:[1359])/i',
					'format' => '$1 $2 $3',
				],
			],
			'countries' => [
				'001' => [
					'pattern' => '/^(?:[1359]\\d{8})$/i',
					'patterns' => [
						'premiumRate' => [
							'lengths' => [
								'national' => [9],
								'localOnly' => [8],
							],
							'pattern' => '/^(?:[1359]\\d{8})$/i',
						],
					],
				],
			],
			'main_country' => null,
		],
	];
}