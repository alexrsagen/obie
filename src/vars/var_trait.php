<?php namespace Obie\Vars;

trait VarTrait {
	public ?VarCollection $vars = null;

	protected function _init_vars(array|VarCollection &$storage = [], bool $assoc = false): void {
		if ($this->vars === null) {
			if (is_array($storage)) {
				$this->vars = new VarCollection($storage, $assoc);
			} else {
				$this->vars = $storage;
			}
		}
	}

	public function get(...$v): mixed {
		$this->_init_vars();
		return $this->vars?->get(...$v);
	}

	public function getHTMLEscaped(...$v): ?string {
		$this->_init_vars();
		$res = $this->vars?->get(...$v);
		if ($res === null) return null;
		return htmlentities((string)$res);
	}

	public function getURLEscaped(...$v): ?string {
		$this->_init_vars();
		$res = $this->vars?->get(...$v);
		if ($res === null) return null;
		return urlencode((string)$res);
	}

	public function set(...$v): static {
		$this->_init_vars();
		$this->vars?->set(...$v);
		return $this;
	}

	public function unset(...$v): static {
		$this->_init_vars();
		$this->vars?->unset(...$v);
		return $this;
	}

	public function isset(...$v): bool {
		$this->_init_vars();
		return $this->vars?->isset(...$v);
	}
}
