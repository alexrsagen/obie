<?php namespace Obie;
use Obie\Encoding\Jsonc;

class I18n {
	protected string $locale = '';
	protected array $locales = [];
	protected array $translations_cache = []; // locale => translations

	function __construct(
		protected string $translations_dir,
		protected string $default_locale = 'en',
	) {
		$this->default_locale = locale_canonicalize($default_locale);
		$this->locale = $this->default_locale;
		$this->translations_dir = rtrim($translations_dir);

		$translations_glob = glob($this->translations_dir . DIRECTORY_SEPARATOR . '*.jsonc');
		if ($translations_glob !== false) {
			$this->locales = array_filter(array_map(function($filepath) {
				return locale_canonicalize(basename($filepath, '.jsonc'));
			}, $translations_glob), function($locale) { return !empty($locale); });
		}
	}

	public static function new(?string $translations_dir = null, ?string $default_locale = null): static {
		if (defined('OBIE_TRANSLATIONS_DIR')) {
			$translations_dir ??= OBIE_TRANSLATIONS_DIR;
		}
		$default_locale ??= App::$app::getConfig()?->get('lang') ?? 'en';
		return new static($translations_dir, $default_locale);
	}

	protected function loadLanguage(string $locale): static {
		if (!in_array($locale, $this->locales, true) || array_key_exists($locale, $this->translations_cache)) return $this;

		$locale_path = $this->translations_dir . DIRECTORY_SEPARATOR . $locale . '.jsonc';
		if ($locale_path !== false && strncmp($locale_path, $this->translations_dir, strlen($this->translations_dir)) !== 0) {
			throw new \Exception('Language path directory escape');
		}

		$translations = file_get_contents($locale_path);
		if ($translations) $translations = Jsonc::decode($translations);
		$this->translations_cache[$locale] = $translations;

		return $this;
	}

	/**
	 * Set the active language for this instance
	 *
	 * @param string $locale RFC 5646 language tag (example: "no")
	 * @throws \Exception
	 * @return static
	 */
	public function setLanguage(string $locale): static {
		$locale = locale_lookup($this->locales, $locale, true, $this->default_locale) ?? $this->locale;
		if (array_key_exists($locale, $this->translations_cache) && is_array($this->translations_cache[$locale])) {
			$this->locale = $locale;
		}
		return $this;
	}

	public function getLanguage(): string {
		return $this->locale;
	}

	public function getLanguages(): array {
		return $this->locales;
	}

	public function getTranslations(?string $locale = null): ?array {
		$locale = $locale ? (locale_lookup($this->locales, $locale, true, $this->default_locale) ?? $this->locale) : $this->locale;
		$this->loadLanguage($locale);
		if (
			array_key_exists($locale, $this->translations_cache) &&
			is_array($this->translations_cache[$locale])
		) {
			return $this->translations_cache[$locale];
		}
		return null;
	}

	public function getTranslation(string $str): string {
		$translations = $this->getTranslations();
		if (is_array($translations) && array_key_exists($str, $translations)) {
			return $translations[$str];
		}
		return $str;
	}

	public function translate(string $str, ...$args): string {
		$str = $this->getTranslation($str);
		if (count($args) === 1 && is_array($args[0])) $args = $args[0];

		// get positional arguments from $args
		$posarg_keys = array_filter(array_keys($args), 'is_int');
		$posargs = array_combine($posarg_keys, array_map(function($key) use($args) {
			return $args[$key];
		}, $posarg_keys));

		// get key-value arguments from $args
		$arg_keys = array_filter(array_keys($args), function($key) use($posarg_keys) {
			return !in_array($key, $posarg_keys, true);
		});
		$args = array_combine($arg_keys, array_map(function($key) use($args) {
			return $args[$key];
		}, $arg_keys));

		// replace positional arguments using sprintf
		if (str_contains($str, '%') && count($posargs) > 0) {
			$str = sprintf($str, ...$posargs);
		}

		// replace key-value arguments
		if (count($args) > 0) {
			foreach ($args as $key => $value) {
				$key = str_replace(['\\', '{{', '}}'], ['\\\\', '\\{{', '\\}}'], $key);
				$str = preg_replace('/(^|[^\\\\])\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/', '${1}' . str_replace('$', '$$', $value), $str);
			}
		}

		// remove remaining key-value arguments
		$str = preg_replace('/(^|[^\\\\])\{\{[^}]+\}\}/', '$1', $str);

		// unescape
		$str = preg_replace('/(^|[^\\\\])\\\{\{/', '$1{', $str);
		$str = preg_replace('/(^|[^\\\\])\\\}\}/', '$1}', $str);
		$str = str_replace('\\\\', '\\', $str);

		return $str;
	}
}