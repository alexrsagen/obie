<?php namespace Obie;
use Obie\Vars\VarTrait;
use Obie\Vars\VarCollection;

class View {
	use VarTrait;

	public static string $views_dir   = '';
	public static array $default_vars = [];

	public static function getPath(string $name): string {
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

	/** @see self::getHTML() */
	public static function render(string $name, array $vars = []): string {
		$tpl = new self($name, $vars);
		return $tpl->getHTML();
	}

	protected string $name    = '';
	protected string $html    = '';
	protected array $blocks   = [];
	protected ?string $parent = null;

	public function __construct(string $name, array|VarCollection $vars = [], array &$blocks = []) {
		$this->name = $name;
		$this->_init_vars($vars);
		$this->blocks = &$blocks;
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

	/**
	 * Set the parent of the current view
	 *
	 * @param null|string $name The name of the view to set as parent
	 * @return void
	 */
	public function extends(?string $name = null): void {
		$this->parent = $name;
	}

	/**
	 * Begin capturing content to an output buffer
	 *
	 * Stop capturing content by calling View::end()
	 *
	 * @see https://www.php.net/manual/en/function.ob-start
	 * @return bool
	 */
	public function begin(): bool {
		return ob_start();
	}

	/**
	 * Capture contents output between calling View::begin() and now,
	 * delete the current output buffer.
	 *
	 * Store the captured contents internally in the current view.
	 *
	 * @see https://www.php.net/manual/en/function.ob-get-clean
	 * @return string|false
	 */
	public function end(): string|false {
		$html = ob_get_clean();
		if ($html !== false) {
			$this->html = $html;
		}
		return $html;
	}

	/**
	 * Capture contents output between calling View::begin() and now,
	 * delete the current output buffer.
	 *
	 * Store the captured content as a block in the current view.
	 *
	 * Blocks are accessible by both the view it was captured in and any
	 * parent views.
	 *
	 * @see https://www.php.net/manual/en/function.ob-get-clean
	 * @return string|false
	 */
	public function endBlock(string $block_name): string|false {
		$block = ob_get_clean();
		if ($block !== false) {
			$this->blocks[$block_name] = $block;
		}
		return $block;
	}

	/**
	 * Get the rendered content of a block, captured by View::endBlock()
	 *
	 * Blocks are accessible by both the view it was captured in and any
	 * parent views.
	 *
	 * @param string $block_name The name of the block to get the content of
	 * @return string
	 */
	public function block(string $block_name): string {
		if (array_key_exists($block_name, $this->blocks)) {
			return $this->blocks[$block_name];
		} else {
			// throw new \Exception("Template block \"$block_name\" not defined");
			return '';
		}
	}

	/**
	 * Get the rendered content, captured by View::end()
	 *
	 * @return string
	 */
	public function getHTML(): string {
		if ($this->parent !== null) {
			$parent = new self($this->parent, $this->vars, $this->blocks);
			return $parent->getHTML();
		}
		return $this->html;
	}

	/**
	 * Return the rendered content of another view, with the current context.
	 *
	 * Important: Should not be the same as the current view.
	 *
	 * @param string $name The name of the view to render
	 * @param array $vars An array of variables to extend the current vars with
	 * @return string The rendered view
	 */
	public function include(string $name, array $vars = []): string {
		if (!empty($vars)) {
			$new_vars = array_merge_recursive($this->vars->get(), $vars);
			$include_vars = new VarCollection($new_vars);
		} else {
			$include_vars = $this->vars;
		}
		$include = new self($name, $include_vars, $this->blocks);
		return $include->getHTML();
	}
}
