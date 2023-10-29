<?php namespace Obie\Http;
use Obie\Vars\VarTrait;

class Route {
	use VarTrait;

	const OK                = 1;
	const OK_EARLY_RESPONSE = 2;
	const OK_NO_RESPONSE    = 3;
	const EINVALID_METHOD   = -1;
	const EINVALID_PATH     = -2;

	protected array $patterns = [];
	protected array $methods = [];
	protected array $handlers = [];
	protected array $handler_args = [];
	protected string $route = '';
	protected string $content_type = Router::CONTENT_TYPE_HTML;
	protected string $charset = Router::CHARSET_UTF8;
	protected bool $minify = true;

	public function __construct(array $methods, string $route, callable ...$handlers) {
		$this->_init_vars();
		$this->methods = $methods;
		$this->route = $route;
		foreach ($handlers as $handler) {
			$this->handlers[] = $handler;
			if ($handler instanceof RouterInstance) {
				break;
			}
		}
	}

	public function getRouterInstance(): ?RouterInstance {
		if (count($this->handlers) === 0) return null;
		$last_handler = $this->handlers[count($this->handlers)-1];
		if ($last_handler instanceof RouterInstance) {
			return $last_handler;
		}
		return null;
	}

	public function isRouteCatchall(): bool {
		return $this->route === '*';
	}

	public function isMethodCatchall(): bool {
		foreach ($this->methods as $method) {
			if ($method === Router::METHOD_USE || $method === Router::METHOD_ANY) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register a regex pattern for one of the named match groups in the route
	 *
	 * @param string $part_name Named match group
	 * @param string $pattern Regular expression pattern
	 * @return static
	 */
	public function where(string $part_name, string $pattern): static {
		$this->patterns[$part_name] = $pattern;
		return $this;
	}

	/**
	 * Check if the request method and path matches. If matching, call
	 * Route::apply() with all the handlers registered in the constructor.
	 *
	 * @param string|null $method Defaults to the current request method
	 * @param string|null $path Defaults to the current request path
	 * @return int One of the status codes defined as constants of this class
	 */
	public function execute(?string $method = null, ?string $path = null): int {
		// Default values
		$method ??= Request::current()?->getMethod();
		$method = strtoupper($method);
		$path ??= Request::current()?->getPath();

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
							$regex .= '([^\[\]\/\\$&+,:;=?@ "\'{}|^~`]+)';
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

	/**
	 * Set the content type of any response sent as a result
	 * of calling Route::apply
	 *
	 * @param string $content_type The content type of responses
	 * @return static
	 */
	public function setContentType(string $content_type = Router::CONTENT_TYPE_HTML): static {
		$this->content_type = $content_type;
		return $this;
	}

	/**
	 * Set the charset of any response sent as a result
	 * of calling Route::apply
	 *
	 * @param string $charset The charset of responses
	 * @return static
	 */
	public function setCharset(string $charset = Router::CHARSET_UTF8): static {
		$this->charset = $charset;
		return $this;
	}

	/**
	 * Set the Minify of any response sent as a result
	 * of calling Route::apply
	 *
	 * @param bool $minify Whether to minify response (if the content type is supported by \Obie\Minify)
	 * @return static
	 */
	public function setMinify(bool $minify = true): static {
		$this->minify = $minify;
		return $this;
	}

	/**
	 * Execute all the handlers in sequence, terminating on the first one
	 * that returns a non-null value.
	 *
	 * Any non-null value returned is passed to Router::sendResponse.
	 *
	 * Closure handlers are bound to the context of this Route instance and
	 * passed all matches from the regex capture groups in the path as
	 * their arguments.
	 *
	 * Other callable handlers are passed this Route instance as the first
	 * argument. The second argument then contains all matches from the regex
	 * capture groups as an array.
	 *
	 * The entire path is not one of the matches.
	 *
	 * @param callable $handlers,... The request handlers to execute
	 * @return int One of the status codes defined as constants of this class
	 */
	public function apply(callable ...$handlers) {
		foreach ($handlers as $i => $handler) {
			if ($handler instanceof \Closure) {
				$response = $handler->bindTo($this, $this)(...$this->handler_args);
			} else {
				$response = $handler($this, $this->handler_args);
			}
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
