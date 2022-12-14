<?php namespace Obie;
use \Obie\Vars\VarTrait;
use \Obie\Encoding\Json;
use \Obie\Vars\VarCollection;

class Config {
    use VarTrait;

    protected static ?self $global_config = null;

    public function __construct(array|VarCollection $data = []) {
        $this->_init_vars($data);

        if (self::$global_config === null) {
            self::$global_config = $this;
        }
    }

    public static function fromJSON(string $json, int $depth = 512, int $options = 0): static {
        return new static(Json::decode($json, $depth, $options));
    }

    public static function setGlobal(self $config) {
        self::$global_config = $config;
    }

    public static function getGlobal(): static {
        return self::$global_config;
    }
}