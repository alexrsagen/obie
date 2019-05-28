<?php namespace ZeroX;
use ZeroX\Vars\VarTrait;

class Config {
    use VarTrait;

    private static $global_config = null;

    public function __construct($data = null) {
        $this->_init_vars($data);

        if (self::$global_config === null) {
            self::$global_config = $this;
        }
    }

    public static function fromJSON(string $json) {
        return new self(json_decode($json, true));
    }

    public static function setGlobal(Config $config) {
        self::$global_config = $config;
    }

	public static function __callStatic(string $method_name, array $args) {
		if (self::$global_config !== null && is_callable([self::$global_config, $method_name])) {
			return call_user_func_array([self::$global_config, $method_name], $args);
		}
	}
}
