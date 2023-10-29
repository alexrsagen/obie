<?php namespace Obie\Http;
use Obie\Vars\VarCollection;

class RouterInstance {
	const OK              = 1;
	const ENOT_FOUND      = -1;
	const EINVALID_METHOD = -2;
	const ENO_CONTENT     = -3;

	public ?VarCollection $vars = null;

	protected array $routes             = [];
	protected array $deferred           = [];
	protected ?RouterInstance $instance = null;
	protected bool $ran_deferred        = false;
	protected ?Route $matched_route     = null;

	public function __construct(?VarCollection $vars = null) {
		if ($vars === null) {
			$this->vars = new VarCollection();
		} else {
			$this->vars = $vars;
		}
	}

	/**
	 * Defer one or more request handlers for execution after a response
	 * is sent.
	 *
	 * The handlers are called by RouterInstance::runDeferred().
	 *
	 * This method is called by Router::defer() on the global RouterInstance.
	 *
	 * @param callable $handlers,...
	 * @return static
	 */
	public function defer(callable ...$handlers): static {
		$this->deferred = array_merge($this->deferred, $handlers);
		return $this;
	}

	public function getInstance(): RouterInstance {
		return $this->instance ?? $this;
	}

	/**
	 * Run all the handlers deferred by calling RouterInstance::defer().
	 *
	 * Closure handlers are bound to the context of the matched Route instance
	 * and passed all matches from the regex capture groups in the path as
	 * their arguments.
	 *
	 * Other callable handlers are passed the matched Route instance as the
	 * first argument. The second argument then contains all matches from the
	 * regex capture groups as an array.
	 *
	 * The entire path is not one of the matches.
	 *
	 * This method is called by Router::runDeferred() and Router::sendResponse()
	 * on the global RouterInstance.
	 *
	 * @return bool Whether the deferred handlers were executed
	 */
	public function runDeferred(): bool {
		if ($this->ran_deferred) return false;
		$this->ran_deferred = true;
		if ($this->instance !== null) {
			$this->instance->runDeferred();
		}
		foreach (array_reverse($this->deferred) as $handler) {
			if ($handler instanceof \Closure) {
				$handler->bindTo($this->matched_route, $this->matched_route)();
			} else {
				$handler($this->matched_route);
			}
		}
		return true;
	}

	/**
	 * Call the Route::execute() method on all registered routes
	 *
	 * @param string|null $method Defaults to the current request method
	 * @param string|null $path Defaults to the current request path
	 * @return int One of the status codes defined as constants of this class
	 */
	public function execute(?string $method = null, ?string $path = null): int {
		$this->matched_route = null;
		$responded = false;
		$invalid_method = false;
		foreach ($this->routes as $route) {
			switch ($route->execute($method, $path)) {
			case Route::EINVALID_METHOD:
				$invalid_method = true;
				break;
			case Route::OK_NO_RESPONSE:
				if (!$route->isRouteCatchall()) {
					$this->matched_route = $route;
					$this->instance = $route->getRouterInstance();
				}
				break;
			case Route::OK_EARLY_RESPONSE:
			case Route::OK:
				if (!$route->isRouteCatchall()) {
					$this->matched_route = $route;
				}
				$responded = true;
				$this->instance = $route->getRouterInstance();
				break 2;
			}
		}
		if ($responded) {
			return self::OK;
		} elseif ($this->matched_route !== null) {
			return self::ENO_CONTENT;
		} elseif ($invalid_method) {
			return self::EINVALID_METHOD;
		}
		return self::ENOT_FOUND;
	}

	/**
	 * Register a new route with a set of handlers
	 *
	 * @param string $method_str A comma-separated list of HTTP methods to handle (case insensitive)
	 * @param string $route_str A slash-delimited list of regexes or match groups, for example: /path/(r?eg[ex]+)/:some_match_group
	 * @param callable $handlers,... A list of middleware/request handlers to apply when executing the route, if the route matches
	 * @return Route The new route
	 */
	public function route(string $method_str, string $route_str, callable ...$handlers): Route {
		$methods = explode(',', strtoupper($method_str));
		$route = new Route($methods, $route_str, ...$handlers);
		$route->vars = $this->vars;
		$this->routes[] = $route;
		return $route;
	}

	/** @see self::route() */
	public function get(string $route_str, callable ...$handlers): Route {
		return $this->route('GET', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function head(string $route_str, callable ...$handlers): Route {
		return $this->route('HEAD', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function post(string $route_str, callable ...$handlers): Route {
		return $this->route('POST', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function put(string $route_str, callable ...$handlers): Route {
		return $this->route('PUT', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function delete(string $route_str, callable ...$handlers): Route {
		return $this->route('DELETE', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function options(string $route_str, callable ...$handlers): Route {
		return $this->route('OPTIONS', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function patch(string $route_str, callable ...$handlers): Route {
		return $this->route('PATCH', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function use(string $route_str, callable ...$handlers): Route {
		return $this->route('USE', $route_str, ...$handlers);
	}
	/** @see self::route() */
	public function any(string $route_str, callable ...$handlers): Route {
		return $this->route('ANY', $route_str, ...$handlers);
	}
}
