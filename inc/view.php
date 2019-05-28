<?php namespace ZeroX;
use ZeroX\Vars\VarTrait;
if (!defined('IN_ZEROX')) {
	exit;
}

class View {
	use VarTrait;

	public static function getPath(string $name) {
		$safe_path = ZEROX_BASE_DIR . '/inc/app/views/' . str_replace('.', '', str_replace([
			'\\',
			'/./',
			'../',
			'.../'
		], '/', $name));
		$safe_path_file = $safe_path . '.tpl.php';
		$safe_path_folder_index = rtrim($safe_path, '/') . '/index.tpl.php';
		if (file_exists($safe_path_file) && is_file($safe_path_file)) {
			return $safe_path_file;
		} else if (file_exists($safe_path_folder_index) && is_file($safe_path_folder_index)) {
			return $safe_path_folder_index;
		}
		throw new \Exception('Could not find path or path is not a template');
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

		// Initialize default variables
		if (!array_key_exists('base_url', $this->vars)) {
			$this->vars['base_url'] = rtrim(Config::get('url'), '/');
		}
		if (!array_key_exists('site_name', $this->vars)) {
			$this->vars['site_name'] = Config::get('site_name');
		}

		// Eval view code
		try {
			require self::getPath($name);
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

	public function include(string $name) {
		$include = new self($name, $this->vars, $this->blocks);
		return $include->getHTML();
	}
}
