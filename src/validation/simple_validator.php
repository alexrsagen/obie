<?php namespace Obie\Validation;

class SimpleValidator implements IValidator {
	const TYPE_CUSTOM       =  0; // Implementation of IValidator
	const TYPE_BOOLEAN      =  1; // "true" | "false" | "1" | "0"
	const TYPE_FQDN         =  2; // RFC 1034, RFC 1035, RFC 952, RFC 1123, RFC 2732, RFC 2181, RFC 1123
	const TYPE_HOSTNAME     =  3; // TYPE_FQDN + Must start with alphanumeric and contain only alphanumerics or hyphens
	const TYPE_EMAIL        =  4; // RFC 5322
	const TYPE_FLOAT        =  5; // IEEE 754
	const TYPE_INT          =  6; // Decimal integer
	const TYPE_IPV4         =  7; // RFC 791
	const TYPE_IPV6         =  8; // RFC 5952
	const TYPE_MAC          =  9; // EUI-48
	const TYPE_REGEX        = 10; // PCRE
	const TYPE_URL          = 11; // RFC 2396
	const TYPE_UUID         = 12; // 01234567-89ab-cdef-0123-456789abcdef
	const TYPE_NONEMPTY     = 13; // String which is not empty and not only whitespace
	const TYPE_BASE64       = 14; // Base-64 encoded string
	const TYPE_BASE64URL    = 15; // Base-64 encoded string
	const TYPE_BASE16       = 16; // Base-16 (hexadecimal) encoded string
	const TYPE_JWT          = 17; // RFC 7519

	const REGEX_RFC5322 = '/^(?:[a-z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*|"(?:[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x21\\x23-\\x5b\\x5d-\\x7f]|\\\\[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x21-\\x5a\\x53-\\x7f]|\\\\[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f])+)\\])$/';

	const REGEX_UUID = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/';

	const REGEX_BASE64 = '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/';
	const REGEX_BASE64URL_PART = '(?:[A-Za-z0-9\-_]{4})*(?:[A-Za-z0-9\-_]{2}(?:==|%3D%3D)?|[A-Za-z0-9\-_]{3}(?:=|%3D)?)?';
	const REGEX_BASE64URL = '/^' . self::REGEX_BASE64URL_PART . '$/';
	const REGEX_JWT = '/^' . self::REGEX_BASE64URL_PART . '(\.' . self::REGEX_BASE64URL_PART . ')*$/';
	const REGEX_BASE16 = '/^[a-fA-F0-9]*$/';

	protected int $type = self::TYPE_CUSTOM;
	protected ?IValidator $cv = null;

	public function __construct(int $type, ?IValidator $cv = null) {
		$this->type = $type;
		$this->cv = $cv;
	}

	public function validate($input) : bool {
		return static::isValid($input, $this->type, $this->cv);
	}

	public static function isValid($input, int $type, ?IValidator $cv = null) {
		switch ($type) {
			case self::TYPE_CUSTOM:
				if ($cv === null) return false;
				return $cv->validate($input);
			case self::TYPE_BOOLEAN:
				if (is_bool($input)) return true;
				return $input === 'true' || $input === 'false' || $input === '1' || $input === '0' || $input === 1 || $input === 0;
			case self::TYPE_FQDN:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_DOMAIN) !== false;
			case self::TYPE_HOSTNAME:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
			case self::TYPE_EMAIL:
				if (!is_string($input)) return false;
				return preg_match(self::REGEX_RFC5322, $input) === 1;
			case self::TYPE_FLOAT:
				if (is_float($input)) return true;
				return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
			case self::TYPE_INT:
				if (is_int($input)) return true;
				return filter_var($input, FILTER_VALIDATE_INT) !== false;
			case self::TYPE_IPV4:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
			case self::TYPE_IPV6:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
			case self::TYPE_MAC:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_MAC) !== false;
			case self::TYPE_REGEX:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_REGEXP) !== false;
			case self::TYPE_URL:
				if (!is_string($input)) return false;
				return filter_var($input, FILTER_VALIDATE_URL) !== false;
			case self::TYPE_UUID:
				if (!is_string($input)) return false;
				return preg_match(self::REGEX_UUID, $input) === 1;
			case self::TYPE_NONEMPTY:
				return is_string($input) && strlen(trim($input)) > 0;
			case self::TYPE_BASE64:
				if (!is_string($input)) return false;
				return preg_match(self::REGEX_BASE64, $input) === 1;
			case self::TYPE_BASE64URL:
				if (!is_string($input)) return false;
				return preg_match(self::REGEX_BASE64URL, $input) === 1;
			case self::TYPE_BASE16:
				if (!is_string($input)) return false;
				return preg_match(self::REGEX_BASE16, $input) === 1;
			case self::TYPE_JWT:
				if (!is_string($input)) return false;
				return preg_match(self::REGEX_JWT, $input) === 1;
			default:
				return false;
		}
	}
}
