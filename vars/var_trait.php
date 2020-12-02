<?php namespace Obie\Vars;

trait VarTrait {
	public $vars = null;

	protected function _init_vars(&$storage = null, bool $assoc = false) {
		if ($this->vars === null) {
			if (is_array($storage)) {
				$this->vars = new VarCollection($storage, $assoc);
			} elseif (is_a($storage, '\Obie\Vars\VarCollection')) {
				$this->vars = $storage;
			} else {
				$this->vars = new VarCollection();
			}
		}
	}

	public function get(...$v): mixed {
		return $this->vars->get(...$v);
	}

	public function getHTMLEscaped(...$v): string {
		$res = $this->vars->get(...$v);
		if ($res === null) return null;
		return htmlentities((string)$res);
	}

	public function getURLEscaped(...$v): string {
		$res = $this->vars->get(...$v);
		if ($res === null) return null;
		return urlencode((string)$res);
	}

	public function set(...$v): static {
		$this->vars->set(...$v);
		return $this;
	}

	public function unset(...$v): static {
		$this->vars->unset(...$v);
		return $this;
	}

	public function isset(...$v): bool {
		return $this->vars->isset(...$v);
	}
}
