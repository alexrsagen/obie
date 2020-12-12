<?php namespace Obie\Http;

class AcceptHeader {
	public function __construct(
		public array $types = [],
	) {}

	public static function decode(string $input): static {
		$types = array_filter(array_map(function($v) {
			return Mime::decode(trim($v, "\n\r\t "));
		}, explode(',', $input)), function($v) {
			return $v !== null;
		});
		usort($types, function($a, $b) {
			return round((float)$a->getParameter('q') - (float)$b->getParameter('q'));
		});
		return new static($types);
	}

	public function encode(): string {
		return implode(',', array_filter(array_map(function($v) {
			if (is_string($v)) $v = Mime::decode($v);
			$weight = $v->getParameter('q');
			$v->parameters = [];
			$v->setParameter('q', $weight);
			return $v->encode();
		}, $this->types), function($v) {
			return $v instanceof Mime;
		}));
	}

	public function getFirstMatch(Mime|string $input, Mime|string|null $fallback = null, bool $exact = false): ?Mime {
		if (is_string($input)) $input = Mime::decode($input);
		if (is_string($fallback)) $fallback = Mime::decode($fallback);
		if ($input === null) return $fallback;
		foreach ($this->types as $type) {
			if (!$input->matches($type, exact: $exact)) continue;
			return $type;
		}
		return $fallback;
	}

	public function getPreferredType(?array $input = null, Mime|string|null $fallback = null, bool $exact = false): ?Mime {
		if (is_string($fallback)) $fallback = Mime::decode($fallback);
		if ($input !== null && count($input) === 0) return $fallback;
		if (count($this->types) === 0) return $fallback;
		if ($input === null) return $this->types[0];
		$input_mime = array_filter(array_map(function($v) {
			if (empty($v)) return null;
			if (is_string($v)) return Mime::decode($v);
			return $v;
		}, $input), function($v) {
			return $v !== null;
		});
		foreach ($this->types as $type) {
			foreach ($input_mime as $input_type) {
				if (!$input_type->matches($type, exact: $exact)) continue;
				return $type;
			}
		}
		return $fallback;
	}

	public function contains(Mime|string $input): bool {
		return $this->getFirstMatch($input, exact: true) !== null;
	}

	public function matches(Mime|string $input): bool {
		return $this->getFirstMatch($input, exact: false) !== null;
	}
}