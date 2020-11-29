<?php namespace Obie;

class Tel {
	const FMT_NUM = 'num'; // Just the number, no prefix or calling code
	const FMT_LOC = 'loc'; // Local format without international prefix or calling code
	const FMT_NAT = 'nat'; // Local format with international prefix, space, calling code
	const FMT_RAW = 'raw'; // E.164 without international prefix
	const FMT_INT = 'int'; // E.164 with international prefix, calling code
	const FMT_EPP = 'epp'; // E.164 with international prefix, dot, calling code
	const FMT_TEL = 'tel'; // RFC3966

	protected $fmt = '';
	protected $int = '';
	protected $cc = '';
	protected $num = '';
	protected $ext = '';
	protected $params = [];

	public function getFormat(): string {
		return $this->fmt;
	}
	public function getInternationalPrefix(): string {
		return $this->int;
	}
	public function getCallingCode(): string {
		return $this->cc;
	}
	public function getCountry(): ?string {
		if (!array_key_exists($this->cc, self::METADATA)) return null;
		// Get NANP country from number
		if ($this->cc === '1') {
			$nanp_code = substr($this->num, 0, 3);
			if (array_key_exists($nanp_code, self::NANP_COUNTRIES_BY_CODE)) {
				return self::NANP_COUNTRIES_BY_CODE[$nanp_code];
			}
		}
		// Get country from country code
		return self::METADATA[$this->cc]['countries'][0];
	}
	public function getNumber(): string {
		return $this->num;
	}
	public function getExt(): string {
		return $this->ext;
	}
	public function getParams(): string {
		return $this->params;
	}

	public function setFormat(string $fmt): self {
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
	public function setCallingCode(string $cc, string $int = '+'): self {
		$this->cc = $cc;
		if (strlen($cc) === 0) {
			$this->int = '';
		} else {
			$this->int = $int;
		}
		return $this;
	}
	public function setNum(string $num): self {
		$this->num = static::normalize($num);
		return $this;
	}
	public function setExt(string $ext): self {
		$this->ext = static::normalize($ext);
		return $this;
	}
	public function setParam(string $key, string $value): self {
		if ($key === 'ext') {
			$this->ext = $value;
		} else {
			$this->params[$key] = $value;
		}
		return $this;
	}
	public function setParams(array $params): self {
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

	public static function parse(string $number, ?string $fallback_cc = null, bool $raw_guess_cc = false): self {
		$res = new static;
		if (strlen($number) === 0) return $res;
		$offset = 0;

		// detect RFC3966
		// https://tools.ietf.org/html/rfc3966#section-3
		if (strtolower(substr($number, 0, 4)) === 'tel:') {
			$res->fmt = self::FMT_TEL;
			$offset += 4;
		}

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

		// find calling code if international dialing prefix was detected,
		// or guessing calling code was enabled (in the case of FMT_RAW)
		if (strlen($res->int) !== 0 || $raw_guess_cc) {
			// build calling code list from largest to smallest
			// split into different lengths
			static $cc_list = null;
			if ($cc_list === null) {
				$cc_list = array_keys(self::METADATA);
				rsort($cc_list, SORT_NUMERIC);
				$cc_list = array_map('strval', $cc_list);
			}

			// find calling code
			$slice = '';
			foreach ($cc_list as $cc) {
				// update slice when needed, as cc gets shorter
				if (strlen($slice) !== strlen($cc)) {
					$slice = substr($number, $offset, strlen($cc));
				}
				if ($slice === $cc) {
					$res->cc = $cc;
					$offset += strlen($cc);
					break;
				}
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
					$res->fmt = strlen($res->int) !== 0 ? self::FMT_INT : self::FMT_RAW;
					break;
				}
			}
		} else {
			$res->fmt = self::FMT_NUM;
			if ($fallback_cc !== null && array_key_exists($fallback_cc, self::METADATA)) {
				$res->int = '+';
				$res->cc = $fallback_cc;
			}
		}

		// find number length by finding the first character which can not be
		// a part of the number
		$num_len = strlen($number) - $offset;
		for ($i = $offset; $i < strlen($number); $i++) {
			if (preg_match('/[^\d\x{FF10}-\x{FF19}\x{0660}-\x{0669}\x{06F0}-\x{06F9}a-zA-Z\s()\.-]/u', $number[$i]) === 1) {
				$num_len = $i - $offset;
				break;
			}
		}

		// set number, detect format
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
				if (preg_match('/[^\d\x{FF10}-\x{FF19}\x{0660}-\x{0669}\x{06F0}-\x{06F9}a-zA-Z\s()\.-]/u', $number[$i]) === 1) {
					$ext_len = $i - $offset;
					break;
				}
			}

			// set extension
			$res->ext = static::normalize(substr($number, $offset, $ext_len));
			$offset += $ext_len;
		}

		// parse params if RFC3966
		if ($res->fmt === self::FMT_TEL) {
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

		// add tel: prefix if format is RFC3966
		if ($fmt === self::FMT_TEL) {
			$res .= 'tel:';
		}

		// add international dialing prefix and calling code, if a calling code
		// is specified and local formatting is not specified
		if (strlen($this->cc) !== 0 && $fmt !== self::FMT_LOC && $fmt !== self::FMT_NUM) {
			if ($fmt !== self::FMT_RAW) {
				$res .= strlen($this->int) === 0 ? '+' : $this->int;
			}
			$res .= $this->cc;
			if ($fmt === self::FMT_NAT) {
				$res .= ' ';
			} elseif ($fmt === self::FMT_EPP) {
				$res .= '.';
			}
		}

		// add phone number
		if (array_key_exists($this->cc, self::METADATA) && ($fmt === self::FMT_LOC || $fmt === self::FMT_NAT)) {
			// local formatting
			// find matching format
			$format = null;
			foreach (self::METADATA[$this->cc]['formats'] as $v) {
				if (
					preg_match($v['pattern'], $this->num) === 1 &&
					(
						!array_key_exists('leadingDigits', $v) ||
						preg_match($v['leadingDigits'], $this->num) === 1
					)
				) {
					$format = $v;
					break;
				}
			}
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

		// add phone number extension with tilde if format is not RFC3966
		if (strlen($this->ext) !== 0 && $fmt !== self::FMT_TEL) {
			$res .= '~' . $this->ext;
		}

		// add phone number params if format is RFC3966
		if ($fmt === self::FMT_TEL) {
			// rebuild array to ensure ext and isdn-subaddress always appear
			// first, as per RFC3966 section 3
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
	// https://en.wikipedia.org/wiki/List_of_North_American_Numbering_Plan_area_codes
	const NANP_COUNTRIES_BY_CODE = [
		// United States
		'205' => 'US', '251' => 'US', '256' => 'US', '334' => 'US', '659' => 'US', '938' => 'US', '907' => 'US', '236' => 'US', '250' => 'US', '778' => 'US', '480' => 'US', '520' => 'US', '602' => 'US', '623' => 'US', '928' => 'US', '327' => 'US', '479' => 'US', '501' => 'US', '870' => 'US', '209' => 'US', '213' => 'US', '279' => 'US', '310' => 'US', '323' => 'US', '341' => 'US', '408' => 'US', '415' => 'US', '424' => 'US', '442' => 'US', '510' => 'US', '530' => 'US', '559' => 'US', '562' => 'US', '619' => 'US', '626' => 'US', '628' => 'US', '650' => 'US', '657' => 'US', '661' => 'US', '669' => 'US', '707' => 'US', '714' => 'US', '747' => 'US', '760' => 'US', '805' => 'US', '818' => 'US', '820' => 'US', '831' => 'US', '840' => 'US', '858' => 'US', '909' => 'US', '916' => 'US', '925' => 'US', '949' => 'US', '951' => 'US', '303' => 'US', '719' => 'US', '720' => 'US', '970' => 'US', '203' => 'US', '475' => 'US', '860' => 'US', '959' => 'US', '302' => 'US', '202' => 'US', '771' => 'US', '239' => 'US', '305' => 'US', '321' => 'US', '352' => 'US', '386' => 'US', '407' => 'US', '448' => 'US', '561' => 'US', '656' => 'US', '689' => 'US', '727' => 'US', '754' => 'US', '772' => 'US', '786' => 'US', '813' => 'US', '850' => 'US', '863' => 'US', '904' => 'US', '941' => 'US', '954' => 'US', '229' => 'US', '404' => 'US', '470' => 'US', '478' => 'US', '678' => 'US', '706' => 'US', '762' => 'US', '770' => 'US', '912' => 'US', '808' => 'US', '208' => 'US', '986' => 'US', '217' => 'US', '224' => 'US', '309' => 'US', '312' => 'US', '331' => 'US', '447' => 'US', '464' => 'US', '618' => 'US', '630' => 'US', '708' => 'US', '730' => 'US', '773' => 'US', '779' => 'US', '815' => 'US', '847' => 'US', '872' => 'US', '219' => 'US', '260' => 'US', '317' => 'US', '463' => 'US', '574' => 'US', '765' => 'US', '812' => 'US', '930' => 'US', '319' => 'US', '515' => 'US', '563' => 'US', '641' => 'US', '712' => 'US', '316' => 'US', '620' => 'US', '785' => 'US', '913' => 'US', '270' => 'US', '364' => 'US', '502' => 'US', '606' => 'US', '859' => 'US', '225' => 'US', '318' => 'US', '337' => 'US', '504' => 'US', '985' => 'US', '207' => 'US', '227' => 'US', '240' => 'US', '301' => 'US', '410' => 'US', '443' => 'US', '667' => 'US', '339' => 'US', '351' => 'US', '413' => 'US', '508' => 'US', '617' => 'US', '774' => 'US', '781' => 'US', '857' => 'US', '978' => 'US', '231' => 'US', '248' => 'US', '269' => 'US', '313' => 'US', '517' => 'US', '586' => 'US', '616' => 'US', '734' => 'US', '810' => 'US', '906' => 'US', '947' => 'US', '989' => 'US', '218' => 'US', '320' => 'US', '507' => 'US', '612' => 'US', '651' => 'US', '763' => 'US', '952' => 'US', '228' => 'US', '601' => 'US', '662' => 'US', '769' => 'US', '314' => 'US', '417' => 'US', '573' => 'US', '636' => 'US', '660' => 'US', '816' => 'US', '406' => 'US', '308' => 'US', '402' => 'US', '531' => 'US', '702' => 'US', '725' => 'US', '775' => 'US', '603' => 'US', '201' => 'US', '551' => 'US', '609' => 'US', '640' => 'US', '732' => 'US', '848' => 'US', '856' => 'US', '862' => 'US', '908' => 'US', '973' => 'US', '505' => 'US', '575' => 'US', '212' => 'US', '315' => 'US', '332' => 'US', '347' => 'US', '516' => 'US', '518' => 'US', '585' => 'US', '607' => 'US', '631' => 'US', '646' => 'US', '680' => 'US', '716' => 'US', '718' => 'US', '838' => 'US', '845' => 'US', '914' => 'US', '917' => 'US', '929' => 'US', '934' => 'US', '252' => 'US', '336' => 'US', '704' => 'US', '743' => 'US', '828' => 'US', '910' => 'US', '919' => 'US', '980' => 'US', '984' => 'US', '701' => 'US', '216' => 'US', '220' => 'US', '234' => 'US', '326' => 'US', '330' => 'US', '380' => 'US', '419' => 'US', '440' => 'US', '513' => 'US', '567' => 'US', '614' => 'US', '740' => 'US', '937' => 'US', '405' => 'US', '539' => 'US', '572' => 'US', '580' => 'US', '918' => 'US', '458' => 'US', '503' => 'US', '541' => 'US', '971' => 'US', '215' => 'US', '223' => 'US', '267' => 'US', '272' => 'US', '412' => 'US', '445' => 'US', '484' => 'US', '570' => 'US', '582' => 'US', '610' => 'US', '717' => 'US', '724' => 'US', '814' => 'US', '878' => 'US', '401' => 'US', '803' => 'US', '839' => 'US', '843' => 'US', '854' => 'US', '864' => 'US', '605' => 'US', '423' => 'US', '615' => 'US', '629' => 'US', '731' => 'US', '865' => 'US', '901' => 'US', '931' => 'US', '210' => 'US', '214' => 'US', '254' => 'US', '281' => 'US', '325' => 'US', '346' => 'US', '361' => 'US', '409' => 'US', '430' => 'US', '432' => 'US', '469' => 'US', '512' => 'US', '682' => 'US', '713' => 'US', '726' => 'US', '737' => 'US', '806' => 'US', '817' => 'US', '830' => 'US', '832' => 'US', '903' => 'US', '915' => 'US', '936' => 'US', '940' => 'US', '945' => 'US', '956' => 'US', '972' => 'US', '979' => 'US', '385' => 'US', '435' => 'US', '801' => 'US', '802' => 'US', '276' => 'US', '434' => 'US', '540' => 'US', '571' => 'US', '703' => 'US', '757' => 'US', '804' => 'US', '826' => 'US', '948' => 'US', '206' => 'US', '253' => 'US', '360' => 'US', '425' => 'US', '509' => 'US', '564' => 'US', '304' => 'US', '681' => 'US', '262' => 'US', '274' => 'US', '414' => 'US', '534' => 'US', '608' => 'US', '715' => 'US', '920' => 'US', '307' => 'US', '710' => 'US',

		// Canada
		'368' => 'CA', '403' => 'CA', '587' => 'CA', '780' => 'CA', '825' => 'CA', '867' => 'CA', '236' => 'CA', '250' => 'CA', '604' => 'CA', '672' => 'CA', '778' => 'CA', '867' => 'CA', '907' => 'CA', '204' => 'CA', '431' => 'CA', '584' => 'CA', '428' => 'CA', '506' => 'CA', '709' => 'CA', '879' => 'CA', '867' => 'CA', '782' => 'CA', '902' => 'CA', '867' => 'CA', '226' => 'CA', '249' => 'CA', '289' => 'CA', '343' => 'CA', '365' => 'CA', '382' => 'CA', '387' => 'CA', '416' => 'CA', '437' => 'CA', '519' => 'CA', '548' => 'CA', '613' => 'CA', '647' => 'CA', '683' => 'CA', '705' => 'CA', '742' => 'CA', '753' => 'CA', '807' => 'CA', '905' => 'CA', '782' => 'CA', '902' => 'CA', '263' => 'CA', '354' => 'CA', '367' => 'CA', '418' => 'CA', '438' => 'CA', '450' => 'CA', '468' => 'CA', '514' => 'CA', '579' => 'CA', '581' => 'CA', '819' => 'CA', '873' => 'CA', '306' => 'CA', '474' => 'CA', '639' => 'CA', '867' => 'CA', '600' => 'CA', '622' => 'CA', '633' => 'CA', '644' => 'CA', '655' => 'CA', '677' => 'CA', '688' => 'CA',

		// Caribbean and Atlantic islands
		'264' => 'AI',
		'268' => 'AG',
		'242' => 'BS',
		'246' => 'BB',
		'441' => 'BM',
		'284' => 'VG',
		'345' => 'KY',
		'767' => 'DM',
		'809' => 'DO',
		'829' => 'DO',
		'849' => 'DO',
		'473' => 'GD',
		'671' => 'GU',
		'658' => 'JM',
		'876' => 'JM',
		'664' => 'MS',
		'670' => 'MP',
		'787' => 'PR',
		'939' => 'PR',
		'869' => 'KN',
		'758' => 'LC',
		'784' => 'VC',
		'721' => 'SX',
		'868' => 'TT',
		'649' => 'TC',
		'340' => 'VI',
		'684' => 'AS',
		'808' => 'US', // 'UM'
	];

	// This data manually generated from:
	// https://github.com/google/libphonenumber/blob/0a45cfd96e71cad8edb0e162a70fcc8bd9728933/resources/PhoneNumberMetadata.xml
	const INT_PREFIXES = ['14110011', '14140011', '14340011', '14410011', '14470011', '14560011', '14660011', '14740011', '14770011', '14880011', '179000', '179100', '179200', '179300', '179400', '179500', '179600', '179700', '179800', '179900', '191100', '191200', '191400', '197700', '199000', '00345', '00365', '00391', '00414', '00444', '00700', '00727', '00755', '00761', '00762', '00766', '11000', '11100', '11200', '11300', '11400', '11500', '11600', '11700', '11800', '11900', '12000', '12100', '12200', '12300', '12400', '12500', '12600', '12700', '12800', '12900', '99511', '99532', '99533', '99541', '99555', '99559', '99577', '99588', '99590', '99599', '0011', '0012', '0013', '0014', '0015', '0016', '0017', '0018', '0019', '0021', '0022', '0023', '0025', '0030', '0031', '0041', '0043', '0050', '0055', '0056', '0059', '0065', '0073', '0099', '1001', '1010', '1020', '1022', '1066', '1088', '1099', '1100', '1110', '1120', '1130', '1140', '1150', '1160', '1190', '1200', '1220', '1230', '1240', '1250', '1401', '1402', '1403', '1510', '1530', '1540', '1550', '1580', '1690', '1700', '1710', '1760', '1770', '1800', '1810', '1880', '8~10', '0~0', '000', '001', '002', '003', '004', '005', '006', '007', '008', '009', '010', '011', '012', '013', '014', '015', '016', '017', '018', '019', '020', '021', '022', '023', '024', '025', '026', '027', '028', '029', '030', '031', '032', '033', '034', '035', '036', '037', '038', '039', '119', '130', '131', '132', '133', '134', '135', '136', '137', '138', '139', '140', '141', '142', '143', '144', '145', '146', '147', '148', '149', '150', '151', '152', '153', '154', '155', '156', '157', '158', '159', '160', '161', '162', '163', '164', '165', '166', '167', '168', '169', '170', '171', '172', '173', '174', '175', '176', '177', '178', '179', '180', '181', '182', '183', '184', '185', '186', '187', '188', '189', '190', '191', '192', '193', '194', '195', '196', '197', '198', '199', '810', '990', '991', '994', '996', '999', '00', '01', '02', '09', '12', '13', '14', '15', '16', '17', '18', '19', '20', '30', '33', '40', '50', '52', '60', '70', '99', '0', '+', "\u{FF0B}"];

	// This data automatically generated from:
	// https://github.com/google/libphonenumber/blob/0a45cfd96e71cad8edb0e162a70fcc8bd9728933/resources/PhoneNumberMetadata.xml
	const METADATA = [
		'247' => [
			'countries' => ['AC'],
			'formats' => [],
		],
		'376' => [
			'countries' => ['AD'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[135-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'971' => [
			'countries' => ['AE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2,9})$/',
					'leadingDigits' => '/^60|8/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[236]|[479][2-8]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d)(\\d{5})$/',
					'leadingDigits' => '/^[479]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'93' => [
			'countries' => ['AF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'1' => [
			'countries' => [
				'US',
				'AG',
				'AI',
				'AS',
				'BB',
				'BM',
				'BS',
				'CA',
				'DM',
				'DO',
				'GD',
				'GU',
				'JM',
				'KN',
				'KY',
				'LC',
				'MP',
				'MS',
				'PR',
				'SX',
				'TC',
				'TT',
				'VC',
				'VG',
				'VI',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '($1) $2-$3',
					'intlFormat' => '$1-$2-$3',
				],
			],
		],
		'355' => [
			'countries' => ['AL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^80|9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^4[2-6]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2358][2-5]|4/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[23578]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'374' => [
			'countries' => ['AM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[89]0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^2|3[12]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^1|47/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^[3-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'244' => [
			'countries' => ['AO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[29]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'54' => [
			'countries' => ['AR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})$/',
					'leadingDigits' => '/^0|1(?:0[0-35-7]|1[02-5]|2[015]|34|4[478])|911/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-8]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[1-8]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^2(?:[23]02|6(?:[25]|4(?:64|[78]))|9(?:[02356]|4(?:[0268]|5[2-6])|72|8[23]))|3(?:3[28]|4(?:[04679]|3(?:5(?:4[0-25689]|[56])|[78])|58|8[2379])|5(?:[2467]|3[237]|8(?:[23]|4(?:[45]|60)|5(?:4[0-39]|5|64)))|7[1-578]|8(?:[2469]|3[278]|54(?:4|5[13-7]|6[89])|86[3-6]))|2(?:2[24-9]|3[1-59]|47)|38(?:[58][78]|7[378])|3(?:454|85[56])[46]|3(?:4(?:36|5[56])|8(?:[38]5|76))[4-6]/',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[68]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[23]/',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^9(?:2(?:[23]02|6(?:[25]|4(?:64|[78]))|9(?:[02356]|4(?:[0268]|5[2-6])|72|8[23]))|3(?:3[28]|4(?:[04679]|3(?:5(?:4[0-25689]|[56])|[78])|5(?:4[46]|8)|8[2379])|5(?:[2467]|3[237]|8(?:[23]|4(?:[45]|60)|5(?:4[0-39]|5|64)))|7[1-578]|8(?:[2469]|3[278]|5(?:4(?:4|5[13-7]|6[89])|[56][46]|[78])|7[378]|8(?:6[3-6]|[78]))))|92(?:2[24-9]|3[1-59]|47)|93(?:4(?:36|5[56])|8(?:[38]5|76))[4-6]/',
					'format' => '$2 15-$3-$4',
					'intlFormat' => '$1 $2 $3-$4',
				],
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^91/',
					'format' => '$2 15-$3-$4',
					'intlFormat' => '$1 $2 $3-$4',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$2 15-$3-$4',
					'intlFormat' => '$1 $2 $3-$4',
				],
			],
		],
		'43' => [
			'countries' => ['AT'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3,12})$/',
					'leadingDigits' => '/^1(?:11|[2-9])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^517/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,5})$/',
					'leadingDigits' => '/^5[079]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,10})$/',
					'leadingDigits' => '/^(?:31|4)6|51|6(?:5[0-3579]|[6-9])|7(?:20|32|8)|[89]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3,9})$/',
					'leadingDigits' => '/^[2-467]|5[2-6]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4,7})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'61' => [
			'countries' => [
				'AU',
				'CC',
				'CX',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})$/',
					'leadingDigits' => '/^16/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^13/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^19/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1802/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3,4})$/',
					'leadingDigits' => '/^19/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2,4})$/',
					'leadingDigits' => '/^16/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^14|4/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2378]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1(?:30|[89])/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'297' => [
			'countries' => ['AW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[25-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'358' => [
			'countries' => [
				'FI',
				'AX',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{5})$/',
					'leadingDigits' => '/^75[12]/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d)(\\d{4,9})$/',
					'leadingDigits' => '/^[2568][1-8]|3(?:0[1-9]|[1-9])|9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^11/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,7})$/',
					'leadingDigits' => '/^[12]00|[368]|70[07-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4,8})$/',
					'leadingDigits' => '/^[1245]|7[135]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6,10})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2',
				],
			],
		],
		'994' => [
			'countries' => ['AZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^90/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^1[28]|2|365(?:[0-46-9]|5[0-35-9])|46/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[13-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'387' => [
			'countries' => ['BA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^6[1-3]|[7-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[3-5]|6[56]/',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'880' => [
			'countries' => ['BD'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{4,6})$/',
					'leadingDigits' => '/^31[5-8]|[459]1/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,7})$/',
					'leadingDigits' => '/^3(?:[67]|8[013-9])|4(?:6[168]|7|[89][18])|5(?:6[128]|9)|6(?:28|4[14]|5)|7[2-589]|8(?:0[014-9]|[12])|9[358]|(?:3[2-5]|4[235]|5[2-578]|6[0389]|76|8[3-7]|9[24])1|(?:44|66)[01346-9]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3,6})$/',
					'leadingDigits' => '/^[13-9]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d)(\\d{7,8})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1-$2',
				],
			],
		],
		'32' => [
			'countries' => ['BE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^(?:80|9)0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[239]|4[23]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[15-8]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^4/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'226' => [
			'countries' => ['BF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[025-7]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'359' => [
			'countries' => ['BG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d)(\\d)(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^43[1-6]|70[1-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2,3})$/',
					'leadingDigits' => '/^[356]|4[124-7]|7[1-9]|8[1-6]|9[1-7]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^(?:70|8)0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^43[1-7]|7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[48]|9[08]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'973' => [
			'countries' => ['BH'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[13679]|8[047]/',
					'format' => '$1 $2',
				],
			],
		],
		'257' => [
			'countries' => ['BI'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2367]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'229' => [
			'countries' => ['BJ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[25689]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'590' => [
			'countries' => [
				'GP',
				'BL',
				'MF',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[569]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'673' => [
			'countries' => ['BN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-578]/',
					'format' => '$1 $2',
				],
			],
		],
		'591' => [
			'countries' => ['BO'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{7})$/',
					'leadingDigits' => '/^[23]|4[46]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{8})$/',
					'leadingDigits' => '/^[67]/',
					'format' => '$1',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'599' => [
			'countries' => [
				'CW',
				'BQ',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[3467]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^9[4-8]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'55' => [
			'countries' => ['BR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3,6})$/',
					'leadingDigits' => '/^1(?:1[25-8]|2[357-9]|3[02-68]|4[12568]|5|6[0-8]|8[015]|9[0-47-9])|321|610/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^4(?:02|37)0|[34]00/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2357]|4(?:[0-24-9]|3(?:[0-689]|7[1-9]))/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2,3})(\\d{4})$/',
					'leadingDigits' => '/^(?:[358]|90)0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^(?:[14689][1-9]|2[12478]|3[1-578]|5[13-5]|7[13-579])[2-57]/',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^[16][1-9]|[2-57-9]/',
					'format' => '$1 $2-$3',
				],
			],
		],
		'975' => [
			'countries' => ['BT'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-68]|7[246]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^1[67]|7/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'267' => [
			'countries' => ['BW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^90/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-6]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'375' => [
			'countries' => ['BY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^800/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2,4})$/',
					'leadingDigits' => '/^800/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^1(?:5[169]|6(?:3[1-3]|4|5[125])|7(?:1[3-9]|7[0-24-6]|9[2-7]))|2(?:1[35]|2[34]|3[3-5])/',
					'format' => '$1 $2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^1(?:[56]|7[467])|2[1-3]/',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[1-4]/',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'501' => [
			'countries' => ['BZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-8]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1-$2-$3-$4',
				],
			],
		],
		'243' => [
			'countries' => ['CD'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^88/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^[1-6]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'236' => [
			'countries' => ['CF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[278]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'242' => [
			'countries' => ['CG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^801/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[02]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'41' => [
			'countries' => ['CH'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^8[047]|90/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2-79]|81/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
		],
		'225' => [
			'countries' => ['CI'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[02-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'682' => [
			'countries' => ['CK'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[2-578]/',
					'format' => '$1 $2',
				],
			],
		],
		'56' => [
			'countries' => ['CL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})$/',
					'leadingDigits' => '/^1(?:[03-589]|21)|[29]0|78/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^2196/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^44/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^2[1-3]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^9[2-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^3[2-5]|[47]|5[1-3578]|6[13-57]|8(?:0[1-9]|[1-9])/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^60|8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^60/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'237' => [
			'countries' => ['CM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^88/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[26]/',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
		],
		'86' => [
			'countries' => ['CN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{5,6})$/',
					'leadingDigits' => '/^96/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5,6})$/',
					'leadingDigits' => '/^(?:10|2[0-57-9])(?:100|9[56])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1[1-9]|26|[3-9]|(?:10|2[0-57-9])(?:[02-8]|1(?:0[1-9]|[1-9])|9[0-47-9])/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^16[08]/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5,6})$/',
					'leadingDigits' => '/^85[23](?:100|95)|(?:3(?:[157]\\d|35|49|9[1-68])|4(?:[17]\\d|2[179]|[35][1-9]|6[47-9]|8[23])|5(?:[1357]\\d|2[37]|4[36]|6[1-46]|80|9[1-9])|6(?:3[1-5]|6[0238]|9[12])|7(?:01|[1579]\\d|2[248]|3[014-9]|4[3-6]|6[023689])|8(?:1[236-8]|2[5-7]|[37]\\d|5[14-9]|8[36-8]|9[1-8])|9(?:0[1-3689]|1[1-79]|[379]\\d|4[13]|5[1-5]))(?:100|9[56])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^26|3(?:[0268]|3[0-46-9]|4[0-8]|9[079])|4(?:[049]|2[02-68]|[35]0|6[0-356]|8[014-9])|5(?:0|2[0-24-689]|4[0-2457-9]|6[057-9]|90)|6(?:[0-24578]|3[06-9]|6[14-79]|9[03-9])|7(?:0[02-9]|2[0135-79]|3[23]|4[0-27-9]|6[1457]|8)|8(?:[046]|1[01459]|2[0-489]|5(?:0|[23](?:[02-8]|1[1-9]|9[0-46-9]))|8[0-2459]|9[09])|9(?:0[0457]|1[08]|[268]|4[024-9]|5[06-9])|(?:1|58|85[23]10)[1-9]|(?:10|2[0-57-9])(?:[0-8]|9[0-47-9])|(?:3(?:[157]\\d|35|49|9[1-68])|4(?:[17]\\d|2[179]|[35][1-9]|6[47-9]|8[23])|5(?:[1357]\\d|2[37]|4[36]|6[1-46]|80|9[1-9])|6(?:3[1-5]|6[0238]|9[12])|7(?:01|[1579]\\d|2[248]|3[014-9]|4[3-6]|6[023689])|8(?:1[236-8]|2[5-7]|[37]\\d|5[14-9]|8[36-8]|9[1-8])|9(?:0[1-3689]|1[1-79]|[379]\\d|4[13]|5[1-5]))(?:[02-8]|1(?:0[1-9]|[1-9])|9[0-47-9])/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^(?:4|80)0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^10[0-79]|2(?:[02-57-9]|1[1-79])|(?:10|21)8(?:0[1-9]|[1-9])/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^3(?:[3-59]|7[02-68])|4(?:[26-8]|3[3-9]|5[2-9])|5(?:3[03-9]|[468]|7[028]|9[2-46-9])|6|7(?:[0-247]|3[04-9]|5[0-4689]|6[2368])|8(?:[1-358]|9[1-7])|9(?:[013479]|5[1-5])|(?:[34]1|55|79|87)[02-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7,8})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^80/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[3-578]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^1[3-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[12]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'57' => [
			'countries' => ['CO'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{7})$/',
					'leadingDigits' => '/^[146][2-9]|[2578]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1-$2-$3',
					'intlFormat' => '$1 $2 $3',
				],
			],
		],
		'506' => [
			'countries' => ['CR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]|8[3-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1-$2-$3',
				],
			],
		],
		'53' => [
			'countries' => ['CU'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{4,6})$/',
					'leadingDigits' => '/^2[1-4]|[34]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{6,7})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{7})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2',
				],
			],
		],
		'238' => [
			'countries' => ['CV'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2-589]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'357' => [
			'countries' => ['CY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^[257-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'420' => [
			'countries' => ['CZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-8]|9[015-7]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'49' => [
			'countries' => ['DE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3,13})$/',
					'leadingDigits' => '/^3[02]|40|[68]9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,12})$/',
					'leadingDigits' => '/^2(?:0[1-389]|12[0-8])|3(?:[35-9][15]|4[015])|906|2(?:[13][14]|2[18])|(?:2[4-9]|4[2-9]|[579][1-9]|[68][1-8])1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2,11})$/',
					'leadingDigits' => '/^[24-6]|3(?:3(?:0[1-467]|2[127-9]|3[124578]|7[1257-9]|8[1256]|9[145])|4(?:2[135]|4[13578]|9[1346])|5(?:0[14]|2[1-3589]|6[1-4]|7[13468]|8[13568])|6(?:2[1-489]|3[124-6]|6[13]|7[12579]|8[1-356]|9[135])|7(?:2[1-7]|4[145]|6[1-5]|7[1-4])|8(?:21|3[1468]|6|7[1467]|8[136])|9(?:0[12479]|2[1358]|4[134679]|6[1-9]|7[136]|8[147]|9[1468]))|70[2-8]|8(?:0[2-9]|[1-8])|90[7-9]|[79][1-9]|3[68]4[1347]|3(?:47|60)[1356]|3(?:3[46]|46|5[49])[1246]|3[4579]3[1357]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^138/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{2,10})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5,11})$/',
					'leadingDigits' => '/^181/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d)(\\d{4,10})$/',
					'leadingDigits' => '/^1(?:3|80)|9/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7,8})$/',
					'leadingDigits' => '/^1[67]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7,12})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{6})$/',
					'leadingDigits' => '/^18500/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{7})$/',
					'leadingDigits' => '/^18[68]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{6})$/',
					'leadingDigits' => '/^15[0568]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{7})$/',
					'leadingDigits' => '/^15[1279]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{8})$/',
					'leadingDigits' => '/^18/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{7,8})$/',
					'leadingDigits' => '/^1(?:6[023]|7)/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^15[279]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{8})$/',
					'leadingDigits' => '/^15/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'253' => [
			'countries' => ['DJ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[27]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'45' => [
			'countries' => ['DK'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'213' => [
			'countries' => ['DZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[1-4]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[5-8]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'593' => [
			'countries' => ['EC'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2-$3',
					'intlFormat' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'372' => [
			'countries' => ['EE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[369]|4[3-8]|5(?:[02]|1(?:[0-8]|95)|5[0-478]|6(?:4[0-4]|5[1-589]))|7[1-9]|88/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3,4})$/',
					'leadingDigits' => '/^[45]|8(?:00[1-9]|[1-49])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'20' => [
			'countries' => ['EG'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{7,8})$/',
					'leadingDigits' => '/^[23]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6,7})$/',
					'leadingDigits' => '/^1[35]|[4-6]|8[2468]|9[235-7]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[189]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'212' => [
			'countries' => [
				'MA',
				'EH',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^5(?:29|38)[89]0/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^5[45]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^5(?:2(?:[2-49]|8[235-9])|3[5-9]|9)|892/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6})$/',
					'leadingDigits' => '/^[5-7]/',
					'format' => '$1-$2',
				],
			],
		],
		'291' => [
			'countries' => ['ER'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[178]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'34' => [
			'countries' => ['ES'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})$/',
					'leadingDigits' => '/^905/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^[79]9/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[89]00/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[5-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'251' => [
			'countries' => ['ET'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[1-59]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'679' => [
			'countries' => ['FJ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[235-9]|45/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'500' => [
			'countries' => ['FK'],
			'formats' => [],
		],
		'691' => [
			'countries' => ['FM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[389]/',
					'format' => '$1 $2',
				],
			],
		],
		'298' => [
			'countries' => ['FO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1',
				],
			],
		],
		'33' => [
			'countries' => ['FR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})$/',
					'leadingDigits' => '/^10/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[1-79]/',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
		],
		'241' => [
			'countries' => ['GA'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^11|[67]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'44' => [
			'countries' => [
				'GB',
				'GG',
				'IM',
				'JE',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8001111/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^845464/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6})$/',
					'leadingDigits' => '/^800/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{4,5})$/',
					'leadingDigits' => '/^1(?:3873|5(?:242|39[4-6])|(?:697|768)[347]|9467)/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{5,6})$/',
					'leadingDigits' => '/^1(?:[2-69][02-9]|[78])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[25]|7(?:0|6(?:[03-9]|2[356]))/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{6})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[1389]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'995' => [
			'countries' => ['GE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^70/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^32/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[57]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[348]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'594' => [
			'countries' => ['GF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[569]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'233' => [
			'countries' => ['GH'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[237]|8[0-2]/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[235]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'350' => [
			'countries' => ['GI'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2',
				],
			],
		],
		'299' => [
			'countries' => ['GL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^19|[2-689]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'220' => [
			'countries' => ['GM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'224' => [
			'countries' => ['GN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[67]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'240' => [
			'countries' => ['GQ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[235]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2',
				],
			],
		],
		'30' => [
			'countries' => ['GR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^21|7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{6})$/',
					'leadingDigits' => '/^2(?:2|3[2-57-9]|4[2-469]|5[2-59]|6[2-9]|7[2-69]|8[2-49])|5/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2689]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'502' => [
			'countries' => ['GT'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'245' => [
			'countries' => ['GW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^40/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[49]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'592' => [
			'countries' => ['GY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-46-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'852' => [
			'countries' => ['HK'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2,5})$/',
					'leadingDigits' => '/^9003/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]|8[1-4]|9(?:0[1-9]|[1-8])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'504' => [
			'countries' => ['HN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[237-9]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
			],
		],
		'385' => [
			'countries' => ['HR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2,3})$/',
					'leadingDigits' => '/^6[01]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2,3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[67]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[2-5]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'509' => [
			'countries' => ['HT'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[2-489]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'36' => [
			'countries' => ['HU'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[27][2-9]|3[2-7]|4[24-9]|5[2-79]|6|8[2-57-9]|9[2-69]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'62' => [
			'countries' => ['ID'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^15/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5,9})$/',
					'leadingDigits' => '/^2[124]|[36]1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5,7})$/',
					'leadingDigits' => '/^800/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5,8})$/',
					'leadingDigits' => '/^[2-79]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,4})(\\d{3})$/',
					'leadingDigits' => '/^8[1-35-9]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6,8})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^804/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^80/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4,5})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^001/',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
			],
		],
		'353' => [
			'countries' => ['IE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^2[24-9]|47|58|6[237-9]|9[35-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[45]0/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[2569]|4[1-69]|7[14]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^70/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^81/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[78]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^4/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'972' => [
			'countries' => ['IL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^125/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^121/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-489]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[57]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^12/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{6})$/',
					'leadingDigits' => '/^159/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1[7-9]/',
					'format' => '$1-$2-$3-$4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{1,2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^15/',
					'format' => '$1-$2 $3-$4',
				],
			],
		],
		'91' => [
			'countries' => ['IN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{7})$/',
					'leadingDigits' => '/^575/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{8})$/',
					'leadingDigits' => '/^5(?:0|2(?:21|3)|3(?:0|3[23])|616|717|8888)/',
					'format' => '$1',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4,5})$/',
					'leadingDigits' => '/^1800/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^140/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^11|2[02]|33|4[04]|79(?:[124-6]|3(?:[02-9]|1[0-24-9])|7(?:1|9[1-6]))|80(?:[2-4]|6[0-589])/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1(?:2[0-24]|3[0-25]|4[145]|[59][14]|6[1-9]|7[1257]|8[1-57-9])|2(?:1[257]|3[013]|4[01]|5[0137]|6[058]|78|8[1568]|9[14])|3(?:26|4[1-3]|5[34]|6[01489]|7[02-46]|8[159])|4(?:1[36]|2[1-47]|3[15]|5[12]|6[0-26-9]|7[0-24-9]|8[013-57]|9[014-7])|5(?:1[025]|22|[36][25]|4[28]|[578]1|9[15])|6(?:12(?:[2-6]|7[0-8])|74[2-7])|7(?:(?:2[14]|5[15])[2-6]|3171|61[346]|88(?:[2-7]|82))|8(?:70[2-6]|84(?:[2356]|7[19])|91(?:[3-6]|7[19]))|73[134][2-6]|(?:74[47]|8(?:16|2[014]|3[126]|6[136]|7[78]|83))(?:[2-6]|7[19])|(?:1(?:29|60|8[06])|261|552|6(?:[2-4]1|5[17]|6[13]|7(?:1|4[0189])|80)|7(?:12|88[01]))[2-7]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1(?:[2-479]|5(?:[0236-9]|5[013-9]))|[2-5]|6(?:2(?:84|95)|355|83)|73179|807(?:1|9[1-3])|(?:1552|6(?:1[1358]|2[2457]|3[2-4]|4[235-7]|5[2-689]|6[24578]|7[235689]|8[124-6])\\d|7(?:1(?:[013-8]\\d|9[6-9])|28[6-8]|3(?:2[0-49]|9[2-57])|4(?:1[2-4]|[29][0-7]|3[0-8]|[56]\\d|8[0-24-7])|5(?:2[1-3]|9[0-6])|6(?:0[5689]|2[5-9]|3[02-8]|4\\d|5[0-367])|70[13-7]))[2-7]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{5})$/',
					'leadingDigits' => '/^[6-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2,4})(\\d{4})$/',
					'leadingDigits' => '/^1(?:6|8[06]0)/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^18/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'246' => [
			'countries' => ['IO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2',
				],
			],
		],
		'964' => [
			'countries' => ['IQ'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[2-6]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'98' => [
			'countries' => ['IR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4,5})$/',
					'leadingDigits' => '/^96/',
					'format' => '$1',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4,5})$/',
					'leadingDigits' => '/^(?:1[137]|2[13-68]|3[1458]|4[145]|5[1468]|6[16]|7[1467]|8[13467])[12689]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[1-8]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'354' => [
			'countries' => ['IS'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[4-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'39' => [
			'countries' => [
				'IT',
				'VA',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{4,5})$/',
					'leadingDigits' => '/^1(?:0|9(?:2[2-9]|[46]))/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^1(?:1|92)/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4,6})$/',
					'leadingDigits' => '/^0[26]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,6})$/',
					'leadingDigits' => '/^0[13-57-9][0159]|8(?:03|4[17]|9(?:2|[45][0-4]))/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2,6})$/',
					'leadingDigits' => '/^0(?:[13-579][2-46-8]|8[236-8])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^894/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^0[26]|5/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^1[4679]|[38]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^0[13-57-9][0159]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^0[26]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4,5})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'962' => [
			'countries' => ['JO'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2356]|87/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5,6})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^70/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'81' => [
			'countries' => ['JP'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^00777[01]/',
					'format' => '$1-$2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^(?:12|57|99)0/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d)(\\d{4})$/',
					'leadingDigits' => '/^1(?:267|3(?:7[247]|9[278])|466|5(?:47|58|64)|6(?:3[245]|48|5[4-68]))|499[2468]|5(?:769|979[2-69])|7468|8(?:3(?:8[78]|96[2457-9])|477|51[24]|636[457-9])|9(?:496|802|9(?:1[23]|69))|1(?:45|58)[67]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^60/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[36]|4(?:2(?:0|9[02-69])|7(?:0[019]|1))/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1(?:1|5(?:4[018]|5[017])|77|88|9[69])|2(?:2[127]|3[0-269]|4[59]|5(?:[0468][01]|[1-3]|5[0-69]|7[015-9]|9(?:17|99))|6(?:2|4[016-9])|7(?:[1-35]|8[0189])|8(?:[16]|3[0134]|9[0-5])|9(?:[028]|17|3[015-9]))|4(?:2(?:[13-79]|2[01]|8[014-6])|3[0-57]|[45]|6[248]|7[2-47]|9[29])|5(?:2|3[045]|4[0-369]|5[29]|8[02389]|9[0-3])|7(?:2[02-46-9]|34|[58]|6[0249]|7[57]|9(?:[23]|4[0-59]|5[01569]|6[0167]))|8(?:2(?:[1258]|4[0-39]|9(?:[019]|4[1-3]|6(?:[0-47-9]|5[01346-9])))|3(?:[29]|7(?:[017-9]|6[6-8]))|49|6(?:[0-24]|36[23]|5(?:[0-389]|5[23])|6(?:[01]|9[178])|72|9[0145])|7[0-468]|8[68])|9(?:4[15]|5[138]|6[1-3]|7[156]|8[189]|9(?:[1289]|3(?:31|4[357])|4[0178]))|(?:223|8699)[014-9]|(?:48|829(?:2|66)|9[23])[1-9]|(?:47[59]|59[89]|8(?:68|9))[019]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[14]|[29][2-9]|5[3-9]|7[2-4679]|8(?:[246-9]|3(?:[3-6][2-9]|7|8[2-5])|5[2-9])/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{3,4})$/',
					'leadingDigits' => '/^007/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^008/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^800/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2579]|80/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})(\\d{4,5})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{5})(\\d{5,6})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{6})(\\d{6,7})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
			],
		],
		'254' => [
			'countries' => ['KE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{5,7})$/',
					'leadingDigits' => '/^[24-6]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6})$/',
					'leadingDigits' => '/^[17]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'996' => [
			'countries' => ['KG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^3(?:1[346]|[24-79])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[235-79]|88/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d)(\\d{2,3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'855' => [
			'countries' => ['KH'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'686' => [
			'countries' => ['KI'],
			'formats' => [],
		],
		'269' => [
			'countries' => ['KM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[3478]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'850' => [
			'countries' => ['KP'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'82' => [
			'countries' => ['KR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{5})$/',
					'leadingDigits' => '/^1[016-9]114/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})$/',
					'leadingDigits' => '/^(?:3[1-3]|[46][1-4]|5[1-5])1/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^60|8/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^[1346]|5[1-5]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[57]/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^0030/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3 $4',
					'intlFormat' => 'NA',
				],
			],
		],
		'965' => [
			'countries' => ['KW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{3,4})$/',
					'leadingDigits' => '/^[169]|2(?:[235]|4[1-35-9])|52/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[25]/',
					'format' => '$1 $2',
				],
			],
		],
		'7' => [
			'countries' => [
				'RU',
				'KZ',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[0-79]/',
					'format' => '$1-$2-$3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^7(?:1(?:[0-6]2|7|8[27])|2(?:13[03-69]|62[013-9]))|72[1-57-9]2/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{5})(\\d)(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^7(?:1(?:0(?:[356]|4[023])|[18]|2(?:3[013-9]|5)|3[45]|43[013-79]|5(?:3[1-8]|4[1-7]|5)|6(?:3[0-35-9]|[4-6]))|2(?:1(?:3[178]|[45])|[24-689]|3[35]|7[457]))|7(?:14|23)4[0-8]|71(?:33|45)[1-79]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[3489]/',
					'format' => '$1 $2-$3-$4',
				],
			],
		],
		'856' => [
			'countries' => ['LA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^2[13]|3[14]|[4-8]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^30[013-9]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[23]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'961' => [
			'countries' => ['LB'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[13-69]|7(?:[2-57]|62|8[0-7]|9[04-9])|8[02-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[7-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'423' => [
			'countries' => ['LI'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[237-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^69/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'94' => [
			'countries' => ['LK'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[1-689]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'231' => [
			'countries' => ['LR'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[4-6]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[3578]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'266' => [
			'countries' => ['LS'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2568]/',
					'format' => '$1 $2',
				],
			],
		],
		'370' => [
			'countries' => ['LT'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^52[0-7]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[7-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^37|4(?:[15]|6[1-8])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[3-6]/',
					'format' => '$1 $2',
				],
			],
		],
		'352' => [
			'countries' => ['LU'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^2(?:0[2-689]|[2-9])|[3-57]|8(?:0[2-9]|[13-9])|9(?:0[89]|[2-579])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^2(?:0[2-689]|[2-9])|[3-57]|8(?:0[2-9]|[13-9])|9(?:0[89]|[2-579])/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^20[2-689]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{1,2})$/',
					'leadingDigits' => '/^2(?:[0367]|4[3-8])/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^80[01]|90[015]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^20/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{1,2})$/',
					'leadingDigits' => '/^2(?:[0367]|4[3-8])/',
					'format' => '$1 $2 $3 $4 $5',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{1,5})$/',
					'leadingDigits' => '/^[3-57]|8[13-9]|9(?:0[89]|[2-579])|(?:2|80)[2-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'371' => [
			'countries' => ['LV'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[269]|8[01]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'218' => [
			'countries' => ['LY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1-$2',
				],
			],
		],
		'377' => [
			'countries' => ['MC'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^4/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[39]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3 $4 $5',
				],
			],
		],
		'373' => [
			'countries' => ['MD'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^22|3/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[25-7]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'382' => [
			'countries' => ['ME'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'261' => [
			'countries' => ['MG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^[23]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'692' => [
			'countries' => ['MH'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-6]/',
					'format' => '$1-$2',
				],
			],
		],
		'389' => [
			'countries' => ['MK'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[347]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d)(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[58]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'223' => [
			'countries' => ['ML'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})$/',
					'leadingDigits' => '/^67(?:0[09]|[59]9|77|8[89])|74(?:0[02]|44|55)/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[24-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'95' => [
			'countries' => ['MM'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^16|2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[45]|6(?:0[23]|[1-689]|7[235-7])|7(?:[0-4]|5[2-7])|8[1-6]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[12]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[4-7]|8[1-35]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4,6})$/',
					'leadingDigits' => '/^9(?:2[0-4]|[35-9]|4[137-9])/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^92/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'976' => [
			'countries' => ['MN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[12]1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[57-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5,6})$/',
					'leadingDigits' => '/^[12]2[1-3]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{5,6})$/',
					'leadingDigits' => '/^[12](?:27|3[2-8]|4[2-68]|5[1-4689])[0-3]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{4,5})$/',
					'leadingDigits' => '/^[12]/',
					'format' => '$1 $2',
				],
			],
		],
		'853' => [
			'countries' => ['MO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[268]/',
					'format' => '$1 $2',
				],
			],
		],
		'596' => [
			'countries' => ['MQ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[569]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'222' => [
			'countries' => ['MR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2-48]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'356' => [
			'countries' => ['MT'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2357-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'230' => [
			'countries' => ['MU'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-46]|8[013]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2',
				],
			],
		],
		'960' => [
			'countries' => ['MV'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[3467]|9[13-9]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'265' => [
			'countries' => ['MW'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1[2-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[137-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'52' => [
			'countries' => ['MX'],
			'formats' => [
				[
					'pattern' => '/^(\\d{5})$/',
					'leadingDigits' => '/^53/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^33|5[56]|81/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^1(?:33|5[56]|81)/',
					'format' => '$2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$2 $3 $4',
				],
			],
		],
		'60' => [
			'countries' => ['MY'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[4-79]/',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^1(?:[02469]|[378][1-9])|8/',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^1[36-8]/',
					'format' => '$1-$2-$3-$4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^15/',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1-$2 $3',
				],
			],
		],
		'258' => [
			'countries' => ['MZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^2|8[2-79]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'264' => [
			'countries' => ['NA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^88/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^87/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'687' => [
			'countries' => ['NC'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})$/',
					'leadingDigits' => '/^5[6-8]/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2-57-9]/',
					'format' => '$1.$2.$3',
				],
			],
		],
		'227' => [
			'countries' => ['NE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^08/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[089]|2[013]|7[04]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'672' => [
			'countries' => ['NF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^1[0-3]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{5})$/',
					'leadingDigits' => '/^[13]/',
					'format' => '$1 $2',
				],
			],
		],
		'234' => [
			'countries' => ['NG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^78/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[12]|9(?:0[3-9]|[1-9])/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2,3})$/',
					'leadingDigits' => '/^[3-7]|8[2-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[7-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4,5})$/',
					'leadingDigits' => '/^[78]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})(\\d{5,6})$/',
					'leadingDigits' => '/^[78]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'505' => [
			'countries' => ['NI'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[125-8]/',
					'format' => '$1 $2',
				],
			],
		],
		'31' => [
			'countries' => ['NL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})$/',
					'leadingDigits' => '/^1[238]|[34]/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})$/',
					'leadingDigits' => '/^14/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4,7})$/',
					'leadingDigits' => '/^[89]0/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^66/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{8})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1[16-8]|2[259]|3[124]|4[17-9]|5[124679]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[1-57-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'47' => [
			'countries' => [
				'NO',
				'SJ',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[489]|5[89]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[235-7]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'977' => [
			'countries' => ['NP'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{7})$/',
					'leadingDigits' => '/^1[2-6]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^[1-8]|9(?:[1-579]|6[2-6])/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1-$2',
				],
			],
		],
		'674' => [
			'countries' => ['NR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[4-68]/',
					'format' => '$1 $2',
				],
			],
		],
		'683' => [
			'countries' => ['NU'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2',
				],
			],
		],
		'64' => [
			'countries' => ['NZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3,8})$/',
					'leadingDigits' => '/^83/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2,3})$/',
					'leadingDigits' => '/^50[0367]|[89]0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^24|[346]|7[2-57-9]|9[2-9]/',
					'format' => '$1-$2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^2(?:10|74)|[59]|80/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^1|2[028]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,5})$/',
					'leadingDigits' => '/^2(?:[169]|7[0-35-9])|7|86/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'968' => [
			'countries' => ['OM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4,6})$/',
					'leadingDigits' => '/^[58]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[179]/',
					'format' => '$1 $2',
				],
			],
		],
		'507' => [
			'countries' => ['PA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[1-57-9]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[68]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'51' => [
			'countries' => ['PE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^80/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{7})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^[4-8]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'689' => [
			'countries' => ['PF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^44/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[48]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'675' => [
			'countries' => ['PG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^18|[2-69]|85/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[78]/',
					'format' => '$1 $2',
				],
			],
		],
		'63' => [
			'countries' => ['PH'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{5})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4,6})$/',
					'leadingDigits' => '/^3(?:230|397|461)|4(?:2(?:35|[46]4|51)|396|4(?:22|63)|59[347]|76[15])|5(?:221|446)|642[23]|8(?:622|8(?:[24]2|5[13]))/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^3469|4(?:279|9(?:30|56))|8834/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[3-7]|8[2-8]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{1,2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'92' => [
			'countries' => ['PK'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^[89]0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6,7})$/',
					'leadingDigits' => '/^9(?:2[3-8]|98)|(?:2(?:3[2358]|4[2-4]|9[2-8])|45[3479]|54[2-467]|60[468]|72[236]|8(?:2[2-689]|3[23578]|4[3478]|5[2356])|9(?:22|3[27-9]|4[2-6]|6[3569]|9[25-7]))[2-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7,8})$/',
					'leadingDigits' => '/^(?:2[125]|4[0-246-9]|5[1-35-7]|6[1-8]|7[14]|8[16]|91)[2-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{5})$/',
					'leadingDigits' => '/^58/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^2[125]|4[0-246-9]|5[1-35-7]|6[1-8]|7[14]|8[16]|91/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[24-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'48' => [
			'countries' => ['PL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{5})$/',
					'leadingDigits' => '/^19/',
					'format' => '$1',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^11|64/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^(?:1[2-8]|2[2-69]|3[2-4]|4[1-468]|5[24-689]|6[1-3578]|7[14-7]|8[1-79]|9[145])19/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2,3})$/',
					'leadingDigits' => '/^64/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^39|45|5[0137]|6[0469]|7[02389]|8(?:0[14]|8)/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^1[2-8]|[2-7]|8[1-79]|9[145]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'508' => [
			'countries' => ['PM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[45]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'970' => [
			'countries' => ['PS'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2489]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'351' => [
			'countries' => ['PT'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^2[12]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[236-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'680' => [
			'countries' => ['PW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'595' => [
			'countries' => ['PY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3,6})$/',
					'leadingDigits' => '/^[2-9]0/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^[26]1|3[289]|4[1246-8]|7[1-3]|8[1-36]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4,5})$/',
					'leadingDigits' => '/^2[279]|3[13-5]|4[359]|5|6(?:[34]|7[1-46-8])|7[46-8]|85/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^2[14-68]|3[26-9]|4[1246-8]|6(?:1|75)|7[1-35]|8[1-36]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^87/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6})$/',
					'leadingDigits' => '/^9(?:[5-79]|8[1-6])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-8]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'974' => [
			'countries' => ['QA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^2[126]|8/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[2-7]/',
					'format' => '$1 $2',
				],
			],
		],
		'262' => [
			'countries' => [
				'RE',
				'YT',
			],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2689]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'40' => [
			'countries' => ['RO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^2[3-6]\\d9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^219|31/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[23]1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[237-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'381' => [
			'countries' => ['RS'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3,9})$/',
					'leadingDigits' => '/^(?:2[389]|39)0|[7-9]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5,10})$/',
					'leadingDigits' => '/^[1-36]/',
					'format' => '$1 $2',
				],
			],
		],
		'250' => [
			'countries' => ['RW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[7-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'966' => [
			'countries' => ['SA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^81/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'677' => [
			'countries' => ['SB'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^7|8[4-9]|9(?:[1-8]|9[0-8])/',
					'format' => '$1 $2',
				],
			],
		],
		'248' => [
			'countries' => ['SC'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[246]|9[57]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'249' => [
			'countries' => ['SD'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[19]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'46' => [
			'countries' => ['SE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2,3})(\\d{2})$/',
					'leadingDigits' => '/^20/',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^9(?:00|39|44)/',
					'format' => '$1-$2',
					'intlFormat' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^[12][136]|3[356]|4[0246]|6[03]|90[1-9]/',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{2,3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2,3})(\\d{2})$/',
					'leadingDigits' => '/^1[2457]|2(?:[247-9]|5[0138])|3[0247-9]|4[1357-9]|5[0-35-9]|6(?:[125689]|4[02-57]|7[0-2])|9(?:[125-8]|3[02-5]|4[0-3])/',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2,3})(\\d{3})$/',
					'leadingDigits' => '/^9(?:00|39|44)/',
					'format' => '$1-$2 $3',
					'intlFormat' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2,3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^1[13689]|2[0136]|3[1356]|4[0246]|54|6[03]|90[1-9]/',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^10|7/',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[13-5]|2(?:[247-9]|5[0138])|6(?:[124-689]|7[0-2])|9(?:[125-8]|3[02-5]|4[0-3])/',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1-$2 $3 $4',
					'intlFormat' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[26]/',
					'format' => '$1-$2 $3 $4 $5',
					'intlFormat' => '$1 $2 $3 $4 $5',
				],
			],
		],
		'65' => [
			'countries' => ['SG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4,5})$/',
					'leadingDigits' => '/^1(?:[013-8]|9(?:0[1-9]|[1-9]))|77/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[369]|8(?:0[1-3]|[1-9])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'290' => [
			'countries' => [
				'SH',
				'TA',
			],
			'formats' => [],
		],
		'386' => [
			'countries' => ['SI'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3,6})$/',
					'leadingDigits' => '/^8[09]|9/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^59|8/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[37][01]|4[0139]|51|6/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[1-57]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'421' => [
			'countries' => ['SK'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{2})(\\d{3,4})$/',
					'leadingDigits' => '/^21/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2,3})$/',
					'leadingDigits' => '/^[3-5][1-8]1[67]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^9090/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3})(\\d{2})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1/$2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[689]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[3-5]/',
					'format' => '$1/$2 $3 $4',
				],
			],
		],
		'232' => [
			'countries' => ['SL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^[236-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'378' => [
			'countries' => ['SM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[5-7]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{6})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2',
				],
			],
		],
		'221' => [
			'countries' => ['SN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[379]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'252' => [
			'countries' => ['SO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^8[125]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{6})$/',
					'leadingDigits' => '/^[134]/',
					'format' => '$1',
				],
				[
					'pattern' => '/^(\\d)(\\d{6})$/',
					'leadingDigits' => '/^[15]|2[0-79]|3[0-46-8]|4[0-7]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{7})$/',
					'leadingDigits' => '/^24|[67]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[3478]|64|90/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5,7})$/',
					'leadingDigits' => '/^1|28|6[1-35-9]|9[2-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'597' => [
			'countries' => ['SR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^56/',
					'format' => '$1-$2-$3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-5]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[6-8]/',
					'format' => '$1-$2',
				],
			],
		],
		'211' => [
			'countries' => ['SS'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[19]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'239' => [
			'countries' => ['ST'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[29]/',
					'format' => '$1 $2',
				],
			],
		],
		'503' => [
			'countries' => ['SV'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[267]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'963' => [
			'countries' => ['SY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[1-5]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'268' => [
			'countries' => ['SZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[0237]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{5})(\\d{4})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2',
				],
			],
		],
		'235' => [
			'countries' => ['TD'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[2679]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'228' => [
			'countries' => ['TG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[279]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'66' => [
			'countries' => ['TH'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[13-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'992' => [
			'countries' => ['TJ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{6})(\\d)(\\d{2})$/',
					'leadingDigits' => '/^3317/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[34]7|91[78]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d)(\\d{4})$/',
					'leadingDigits' => '/^3/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[02457-9]|11/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'690' => [
			'countries' => ['TK'],
			'formats' => [],
		],
		'670' => [
			'countries' => ['TL'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[2-489]|70/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2',
				],
			],
		],
		'993' => [
			'countries' => ['TM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^12/',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d)(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[1-5]/',
					'format' => '$1 $2-$3-$4',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{6})$/',
					'leadingDigits' => '/^6/',
					'format' => '$1 $2',
				],
			],
		],
		'216' => [
			'countries' => ['TN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-57-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'676' => [
			'countries' => ['TO'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^[2-4]|50|6[09]|7[0-24-69]|8[05]/',
					'format' => '$1-$2',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^0/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[5-8]/',
					'format' => '$1 $2',
				],
			],
		],
		'90' => [
			'countries' => ['TR'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d)(\\d{3})$/',
					'leadingDigits' => '/^444/',
					'format' => '$1 $2 $3',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^512|8[0589]|90/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^5(?:[0-59]|6161)/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[24][1-8]|3[1-9]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{6,7})$/',
					'leadingDigits' => '/^80/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'688' => [
			'countries' => ['TV'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^90/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2',
				],
			],
		],
		'886' => [
			'countries' => ['TW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d)(\\d{4})$/',
					'leadingDigits' => '/^202/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[258]0/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d)(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^[23568]|4(?:0[2-48]|[1-47-9])|(?:400|7)[1-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[49]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4,5})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'255' => [
			'countries' => ['TZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[24]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[67]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'380' => [
			'countries' => ['UA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^6[12][29]|(?:35|4[1378]|5[12457]|6[49])2|(?:56|65)[24]|(?:3[1-46-8]|46)2[013-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^4[45][0-5]|5(?:0|6(?:3[14-7]|7))|6(?:[12][018]|[36-8])|7|89|9[1-9]|(?:48|57)[0137-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^[3-6]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'256' => [
			'countries' => ['UG'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{5})$/',
					'leadingDigits' => '/^2024/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{6})$/',
					'leadingDigits' => '/^[27-9]|4(?:6[45]|[7-9])/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^[34]/',
					'format' => '$1 $2',
				],
			],
		],
		'598' => [
			'countries' => ['UY'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8|90/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^9/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[24]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^4/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'998' => [
			'countries' => ['UZ'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[35-9]/',
					'format' => '$1 $2 $3 $4',
				],
			],
		],
		'58' => [
			'countries' => ['VE'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{7})$/',
					'leadingDigits' => '/^[24-689]/',
					'format' => '$1-$2',
				],
			],
		],
		'84' => [
			'countries' => ['VN'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[17]99/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^80/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4,5})$/',
					'leadingDigits' => '/^69/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4,6})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[69]/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[3578]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^2[48]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^2/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'678' => [
			'countries' => ['VU'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[579]/',
					'format' => '$1 $2',
				],
			],
		],
		'681' => [
			'countries' => ['WF'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{2})$/',
					'leadingDigits' => '/^[4-8]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'685' => [
			'countries' => ['WS'],
			'formats' => [
				[
					'pattern' => '/^(\\d{5})$/',
					'leadingDigits' => '/^[2-5]|6[1-9]/',
					'format' => '$1',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3,7})$/',
					'leadingDigits' => '/^[68]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2',
				],
			],
		],
		'383' => [
			'countries' => ['XK'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[89]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[2-4]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[23]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'967' => [
			'countries' => ['YE'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^[1-6]|7[24-68]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'27' => [
			'countries' => ['ZA'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})$/',
					'leadingDigits' => '/^8[1-4]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{2,3})$/',
					'leadingDigits' => '/^8[1-4]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^860/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'260' => [
			'countries' => ['ZM'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2',
					'intlFormat' => 'NA',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[28]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^[79]/',
					'format' => '$1 $2',
				],
			],
		],
		'263' => [
			'countries' => ['ZW'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3,5})$/',
					'leadingDigits' => '/^2(?:0[45]|2[278]|[49]8)|3(?:[09]8|17)|6(?:[29]8|37|75)|[23][78]|(?:33|5[15]|6[68])[78]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{2,4})$/',
					'leadingDigits' => '/^[49]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^80/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{7})$/',
					'leadingDigits' => '/^2(?:02[014]|4|[56]20|[79]2)|392|5(?:42|525)|6(?:[16-8]21|52[013])|8[13-59]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{4})$/',
					'leadingDigits' => '/^7/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^2(?:1[39]|2[0157]|[378]|[56][14])|3(?:123|29)/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{6})$/',
					'leadingDigits' => '/^8/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,5})$/',
					'leadingDigits' => '/^1|2(?:0[0-36-9]|12|29|[56])|3(?:1[0-689]|[24-6])|5(?:[0236-9]|1[2-4])|6(?:[013-59]|7[0-46-9])|(?:33|55|6[68])[0-69]|(?:29|3[09]|62)[0-79]/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3})(\\d{3,4})$/',
					'leadingDigits' => '/^29[013-9]|39|54/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{3,5})$/',
					'leadingDigits' => '/^258|5483/',
					'format' => '$1 $2',
				],
			],
		],
		'800' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'808' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[1-9]/',
					'format' => '$1 $2',
				],
			],
		],
		'870' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^[35-7]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'878' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{5})(\\d{5})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'881' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{3})(\\d{5})$/',
					'leadingDigits' => '/^[0-36-9]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'882' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{2})(\\d{5})$/',
					'leadingDigits' => '/^16|342/',
					'format' => '$1 $2',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{2})(\\d{4})$/',
					'leadingDigits' => '/^[19]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{3})$/',
					'leadingDigits' => '/^3[23]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{3,4})(\\d{4})$/',
					'leadingDigits' => '/^1/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^34[57]/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^34/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{2})(\\d{4,5})(\\d{5})$/',
					'leadingDigits' => '/^[1-3]/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'883' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^510/',
					'format' => '$1 $2 $3',
				],
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{3})(\\d{3})$/',
					'leadingDigits' => '/^510/',
					'format' => '$1 $2 $3 $4',
				],
				[
					'pattern' => '/^(\\d{4})(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^5/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'888' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d{3})(\\d{3})(\\d{5})$/',
					'format' => '$1 $2 $3',
				],
			],
		],
		'979' => [
			'countries' => ['001'],
			'formats' => [
				[
					'pattern' => '/^(\\d)(\\d{4})(\\d{4})$/',
					'leadingDigits' => '/^[1359]/',
					'format' => '$1 $2 $3',
				],
			],
		],
	];
}