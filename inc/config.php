<?php namespace ZeroX;
use ZeroX\Vars\VarTrait;
if (!defined('IN_ZEROX')) {
	return;
}

class Config {
    use VarTrait;

    protected static $global_config = null;

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

    public static function getGlobal() {
        return self::$global_config;
    }
}
