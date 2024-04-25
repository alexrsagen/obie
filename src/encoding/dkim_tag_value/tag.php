<?php namespace Obie\Encoding\DkimTagValue;
use Obie\Encoding\DkimTagValue;
use Obie\Log;
use Obie\Validation\SimpleValidator;

class Tag {
	const REGEX_PART_HYPHENATED_WORD = '[a-z](?:[a-z0-9\\-]*[a-z0-9])?';
	const REGEX_HYPHENATED_WORD = '/^' . self::REGEX_PART_HYPHENATED_WORD . '$/i';
	const REGEX_HYPHENATED_WORDS = '/^' . self::REGEX_PART_HYPHENATED_WORD . '(?:\\:' . self::REGEX_PART_HYPHENATED_WORD . ')*$/i';
	const REGEX_HYPHENATED_WORDS_OR_ASTERISK = '/^(?:\\*|' . self::REGEX_PART_HYPHENATED_WORD . ')(?:\\:(?:\\*|' . self::REGEX_PART_HYPHENATED_WORD . '))*$/i';

	function __construct(
		public string $name = '',
		public string $value = '',
	) {}

	public function isValid(string $version = DkimTagValue::VERSION_DKIM1): bool {
		// version tag, common to all implementations
		if ($this->name === 'v' && $this->value !== $version) {
			Log::warning('DkimTagValue/Tag: unexpected version value');
			return false;
		}

		// version-specific tags
		if ($version === DkimTagValue::VERSION_DKIM1) {
			// tags defined in RFC 6376 (DKIM)
			if (in_array($this->name, ['h', 'k']) && preg_match(self::REGEX_HYPHENATED_WORD, $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid hyphenated-word value for DKIM1 tag "h=" or "k="');
				return false;
			}
			if ($this->name === 's' && preg_match(self::REGEX_HYPHENATED_WORDS_OR_ASTERISK, $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid hyphenated-word value for DKIM1 tag "s="');
				return false;
			}
			if ($this->name === 't' && preg_match(self::REGEX_HYPHENATED_WORDS, $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid hyphenated-word value for DKIM1 tag "t="');
				return false;
			}
			if ($this->name === 'p' && !SimpleValidator::isValid($this->value, SimpleValidator::TYPE_BASE64)) {
				Log::warning('DkimTagValue/Tag: invalid public key value for DKIM1 tag "p="');
				return false;
			}
		} elseif ($version === DkimTagValue::VERSION_DMARC1) {
			// tags defined in RFC 7489 (DMARC)
			if (in_array($this->name, ['adkim', 'aspf']) && preg_match('/^[rs]$/', $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid value for DMARC1 tag "adkim=" or "aspf="');
				return false;
			}
			if ($this->name === 'fo' && preg_match('/^[01ds:]+$/', $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid value for DMARC1 tag "fo="');
				return false;
			}
			if (in_array($this->name, ['sp','p']) && preg_match('/^(none|quarantine|reject)$/', $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid policy value for DMARC1 tag "sp=" or "p="');
				return false;
			}
			if ($this->name === 'pct' && preg_match('/^(100|[1-9][0-9]|[0-9])$/', $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid 0-100 numeric value for DMARC1 tag "pct="');
				return false;
			}
			if ($this->name === 'rf' && preg_match(self::REGEX_HYPHENATED_WORD, $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid hyphenated-word value for DMARC1 tag "rf="');
				return false;
			}
			if ($this->name === 'ri' && preg_match('/^\d+$/', $this->value) !== 1) {
				Log::warning('DkimTagValue/Tag: invalid numeric value for DMARC1 tag "ri="');
				return false;
			}
		}

		return true;
	}
}