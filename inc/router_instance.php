<?php namespace ZeroX;
use \ZeroX\Vars\VarCollection;

class RouterInstance {
	const OK              = 1;
	const ENOT_FOUND      = -1;
	const EINVALID_METHOD = -2;

	public $vars;

	protected $routes       = [];
	protected $deferred     = [];
	protected $instance     = null;
	protected $ran_deferred = false;

	public function __construct(VarCollection $vars = null) {
		if ($vars === null) {
			$this->vars = new VarCollection();
		} else {
			$this->vars = $vars;
		}
	}

	public function defer(\Closure ...$handlers) {
		$this->deferred = array_merge($this->deferred, $handlers);
	}

	public function getInstance(): RouterInstance {
		return $this->instance ?? $this;
	}

	public function runDeferred() {
		if ($this->ran_deferred) return;
		if ($this->instance !== null) {
			$this->instance->runDeferred();
		}
		$vc = $this->vars->getContainer();
		foreach (array_reverse($this->deferred) as $handler) {
			$handler->bindTo($vc, $vc)();
		}
	}

	public function execute(string $method = null, string $path = null) {
		$matched = false;
		$responded = false;
		$invalid_method = false;
		foreach ($this->routes as $route) {
			switch ($route->execute($method, $path)) {
			case Route::EINVALID_METHOD:
				if (!$route->isRouteCatchall()) $matched = true;
				$invalid_method = true;
				break;
			case Route::OK_NO_RESPONSE:
				if (!$route->isRouteCatchall()) {
					$matched = true;
					$this->instance = $route->getRouterInstance();
				}
				break;
			case Route::OK_EARLY_RESPONSE:
			case Route::OK:
				if (!$route->isRouteCatchall()) {
					$matched = true;
				}
				$responded = true;
				$this->instance = $route->getRouterInstance();
				break 2;
			}
		}
		if ($responded) {
			return self::OK;
		} elseif ($invalid_method) {
			return self::EINVALID_METHOD;
		}
		return self::ENOT_FOUND;
	}

	public function route(string $method_str, string $route_str, ...$handlers) {
		$methods = explode(',', strtoupper($method_str));
		$route = new Route($methods, $route_str, ...$handlers);
		$route->vars = $this->vars;
		$this->routes[] = $route;
		return $route;
	}

	public function __call(string $method_name, array $args) {
		// Shorthand methods for route
		if (in_array($method_name, ['get', 'head', 'post', 'put', 'delete', 'options', 'patch', 'use', 'any'])) {
			if (count($args) < 2) {
				throw new \Exception(get_class() . "::$method_name needs two or more argumemnts: \$route, ...\$handlers");
			}
			return $this->route($method_name, ...$args);
		}
	}
}
