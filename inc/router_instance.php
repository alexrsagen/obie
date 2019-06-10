<?php namespace ZeroX;
use ZeroX\Vars\VarCollection;
if (!defined('IN_ZEROX')) {
	return;
}

class RouterInstance {
	const OK              = 1;
	const ENOT_FOUND      = -1;
	const EINVALID_METHOD = -2;

	public $vars;

	private $status;
	private $routes       = [];
	private $deferred     = [];
	private $ran_deferred = false;

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

	public function runDeferred() {
		if ($this->ran_deferred) return;
		$vc = $this->vars->getContainer();
		foreach ($this->deferred as $handler) {
			$handler->bindTo($vc, $vc)($this->status);
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
					if (!$route->isRouteCatchall()) $matched = true;
					break;
				case Route::OK_EARLY_RESPONSE:
				case Route::OK:
					if (!$route->isRouteCatchall()) $matched = true;
					$responded = true;
					break 2;
			}
		}
		if ($responded) {
			$this->status = self::OK;
		} elseif ($invalid_method) {
			$this->status = self::EINVALID_METHOD;
		} else {
			$this->status = self::ENOT_FOUND;
		}
		$this->runDeferred();
		return $this->status;
	}

	public function route(string $method_str, string $route_str, \Closure ...$handlers) {
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
