<?php namespace ZeroX;
use ZeroX\Vars\VarTrait;
use ZeroX\Vars\VarCollection;
if (!defined('IN_ZEROX')) {
	return;
}

class View {
	use VarTrait;

	public static $views_dir = '';
	public static $default_vars = [];

	public static function getPath(string $name) {
		$base = static::$views_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim(str_replace('/./', '', preg_replace('/(\.)\.+|(\/)\/+/', '$1$2', '/' . trim(str_replace('\\', '/', $name), '/'))), '/'));
		$file = realpath($base . '.tpl.php');
		if ($file !== false && strncmp($file, static::$views_dir, strlen(static::$views_dir)) !== 0) {
			throw new \Exception('View path directory escape');
		}

		$file_index = realpath($base . DIRECTORY_SEPARATOR . 'index.tpl.php');
		if ($file_index !== false && strncmp($file_index, static::$views_dir, strlen(static::$views_dir)) !== 0) {
			throw new \Exception('View path directory escape');
		}

		if ($file !== false && file_exists($file) && is_file($file)) {
			return $file;
		} elseif ($file_index !== false && file_exists($file_index) && is_file($file_index)) {
			return $file_index;
		}

		throw new \Exception('Could not find view');
	}

	public static function render(string $name, array $vars = null) {
		$tpl = new self($name, $vars);
		return $tpl->getHTML();
	}

	private $name;
	private $html = '';
	private $blocks = [];
	private $parent = null;

	public function __construct(string $name, $vars = null, array $blocks = []) {
		$this->name = $name;
		$this->_init_vars($vars);
		$this->blocks = $blocks;
		foreach (static::$default_vars as $k => $v) {
			$k = explode('.', $k);
			if (!$this->isset(...$k)) {
				$this->set(...array_merge($k, [$v]));
			}
		}

		// Eval view code
		try {
			require static::getPath($name);
		} catch (\Exception $e) {
			throw new \Exception("Error loading view \"{$name}\": {$e->getMessage()}");
		}
	}

	public function extends(string $name = null) {
		$this->parent = $name;
	}

	public function begin() {
		ob_start();
	}

	public function end() {
		return $this->html = ob_get_clean();
	}

	public function endBlock(string $block_name) {
		return $this->blocks[$block_name] = ob_get_clean();
	}

	public function block(string $block_name) {
		if (array_key_exists($block_name, $this->blocks)) {
			return $this->blocks[$block_name];
		} else {
			// throw new \Exception("Template block \"$block_name\" not defined");
			return '';
		}
	}

	public function getHTML() {
		if ($this->parent !== null) {
			$parent = new self($this->parent, $this->vars, $this->blocks);
			return $parent->getHTML();
		}
		return $this->html;
	}

	public function include(string $name, array $vars = []) {
		if (!empty($vars)) {
			$existing_vars = $this->vars->get();
			$include_vars = new VarCollection(array_merge_recursive($existing_vars, $vars));
		} else {
			$include_vars = $this->vars;
		}
		$include = new self($name, $include_vars, $this->blocks);
		return $include->getHTML();
	}
}
