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
			return round((float)$b->getParameter('q') - (float)$a->getParameter('q'));
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

	public function getFirstMatch(Mime|string $input, bool $exact = false): ?Mime {
		if (is_string($input)) $input = Mime::decode($input);
		if ($input === null) return null;
		foreach ($this->types as $type) {
			if (
				(!$exact && ($type->type === '*' || $input->type === '*') || $type->type === $input->type) &&
				(!$exact && ($type->subtype === '*' || $input->subtype === '*') || $type->subtype === $input->subtype)
			) {
				return $type;
			}
		}
		return null;
	}

	public function contains(Mime|string $input): bool {
		return $this->getFirstMatch($input, exact: true) !== null;
	}

	public function matches(Mime|string $input): bool {
		return $this->getFirstMatch($input, exact: false) !== null;
	}
}