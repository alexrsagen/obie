<?php namespace Obie\Http;

class AcceptLanguageHeader {
	public function __construct(
		public array $languages = [],
	) {}

	public static function decodeArray(array $input): static {
		$languages = array_filter(array_map(function($v) {
			return Language::decode(trim($v, "\n\r\t "));
		}, $input), function($v) {
			return $v !== null;
		});
		usort($languages, function($a, $b) {
			return (float)($b->getParameter('q') ?? 1.0) <=> (float)($a->getParameter('q') ?? 1.0);
		});
		return new static($languages);
	}

	public static function decode(string $input): static {
		return static::decodeArray(explode(',', $input));
	}

	public function encode(): string {
		return implode(',', array_filter(array_map(function($v) {
			if (is_string($v)) $v = Language::decode($v);
			if ($v === null || !($v instanceof Language)) return null;
			$weight = $v->getParameter('q');
			$v->parameters = [];
			$v->setParameter('q', $weight);
			return $v->encode();
		}, $this->languages), function($v) {
			return !empty($v) && is_string($v) && $v !== '/';
		}));
	}

	public function getPreferredLanguage(?array $input = null, Language|string|null $fallback = null): ?Language {
		if (is_string($fallback)) $fallback = Language::decode($fallback);
		if ($input !== null && count($input) === 0) return $fallback;
		if (count($this->languages) === 0) return $fallback;
		if ($input === null) return $this->languages[0];
		$locales = array_map(function($language) {
			return $language->locale;
		}, $this->languages);
		$input_languages = array_filter(array_map(function($v) {
			if (empty($v) || !is_string($v) && !($v instanceof Language)) return null;
			if (is_string($v)) return Language::decode($v);
			return $v;
		}, $input), function($v) {
			return $v !== null;
		});
		foreach ($input_languages as $language) {
			$matched_locale = locale_lookup($locales, $language->locale, true, $fallback?->locale);
			if ($matched_locale !== "" && $matched_locale !== $fallback?->locale) {
				return $language;
			}
		}
		return $fallback;
	}
}