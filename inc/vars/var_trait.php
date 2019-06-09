<?php namespace ZeroX\Vars;
if (!defined('IN_ZEROX')) {
	return;
}

trait VarTrait {
	public $vars = null;

	private function _init_vars(&$storage = null, bool $assoc = false) {
		if ($this->vars === null) {
			if (is_array($storage)) {
				$this->vars = new VarCollection($storage, $assoc);
			} elseif (is_a($storage, '\ZeroX\Vars\VarCollection')) {
				$this->vars = $storage;
			} else {
				$this->vars = new VarCollection();
			}
		}
	}

	public function get(...$v) {
		return $this->vars->get(...$v);
	}

	public function getHTMLEscaped(...$v) {
		$res = $this->vars->get(...$v);
		if ($res === null) return null;
		return htmlentities((string)$res);
	}

	public function getURLEscaped(...$v) {
		$res = $this->vars->get(...$v);
		if ($res === null) return null;
		return urlencode((string)$res);
	}

	public function set(...$v) {
		$this->vars->set(...$v);
		return $this;
	}

	public function unset(...$v) {
		$this->vars->unset(...$v);
		return $this;
	}

	public function isset(...$v) {
		return $this->vars->isset(...$v);
	}
}
