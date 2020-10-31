<?php namespace ZeroX;
use \ZeroX\Vars\VarTrait;

class Route {
	use VarTrait;

	const OK                = 1;
	const OK_EARLY_RESPONSE = 2;
	const OK_NO_RESPONSE    = 3;
	const EINVALID_METHOD   = -1;
	const EINVALID_PATH     = -2;

	protected $patterns = [];
	protected $methods = [];
	protected $handlers = [];
	protected $handler_args = [];
	protected $route = '';
	protected $content_type = Router::CONTENT_TYPE_HTML;
	protected $charset = Router::CHARSET_UTF8;
	protected $minify = true;

	public function __construct(array $methods, string $route, \Closure ...$handlers) {
		$this->_init_vars();
		$this->methods = $methods;
		$this->route = $route;
		$this->handlers = $handlers;
	}

	public function isRouteCatchall() {
		return $this->route === '*';
	}

	public function isMethodCatchall() {
		foreach ($this->methods as $method) {
			if ($method === Router::METHOD_USE || $method === Router::METHOD_ANY) {
				return true;
			}
		}
		return false;
	}

	public function where(string $part_name, string $pattern) {
		$this->patterns[$part_name] = $pattern;
	}

	public function execute(string $method = null, string $path = null) {
		// Default values
		if ($method === null) {
			$method = Router::getMethod();
		} else {
			$method = strtoupper($method);
		}
		if ($path === null) {
			$path = Router::getPath();
		}

		if (!$this->isRouteCatchall()) {
			// Process custom route format into plain regex
			$regex = '';
			if ($this->route === '/') {
				$regex = '/^\/$/';
			} else {
				$regex = '/^';
				$offset = 0;
				while (($offset = strpos($this->route, '/', $offset)) !== false) {
					$offset++;
					$part_end = strpos($this->route, '/', $offset);
					if ($part_end === false) $part_end = strlen($this->route);

					$regex .= '\/';
					if ($this->route[$offset] === ':') {
						$part_name = substr($this->route, $offset + 1, $part_end - $offset - 1);
						if (array_key_exists($part_name, $this->patterns)) {
							$regex .= '(' . $this->patterns[$part_name] . ')';
						} else {
							$regex .= '([^[\][\/\\$&+,:;=?@ "\'{}|^~`]+)';
						}
					} else {
						$regex .= substr($this->route, $offset, $part_end - $offset);
					}
				}
				if (Router::$strict) {
					$regex .= '$';
				}
				$regex .= '/';
			}

			// Evaluate path
			$is_matching = preg_match($regex, $path, $matches) === 1;
			if (!$is_matching) {
				return self::EINVALID_PATH;
			}
			$this->handler_args = array_slice($matches, 1);
		}

		// Evaluate method
		if (!$this->isMethodCatchall() && !in_array($method, $this->methods)) {
			return self::EINVALID_METHOD;
		}

		// Execute handlers
		return $this->apply(...$this->handlers);
	}

	protected function setContentType(string $content_type = Router::CONTENT_TYPE_HTML) {
		$this->content_type = $content_type;
	}

	protected function setCharset(string $charset = Router::CHARSET_UTF8) {
		$this->charset = $charset;
	}

	protected function setMinify(bool $minify = true) {
		$this->minify = $minify;
	}

	protected function apply(\Closure ...$handlers) {
		foreach ($handlers as $i => $handler) {
			$response = $handler->bindTo($this, $this)(...$this->handler_args);
			if ($response !== null) {
				Router::sendResponse($response, $this->content_type, $this->charset, $this->minify);
			}
			if (Router::isResponseSent()) {
				if ($i === count($handlers) - 1) {
					return self::OK;
				} else {
					return self::OK_EARLY_RESPONSE;
				}
			}
		}

		return Router::isResponseSent() ? self::OK : self::OK_NO_RESPONSE;
	}
}
