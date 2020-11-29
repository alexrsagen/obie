<?php namespace Obie\Models;
use \Obie\Random;
use \Obie\Encoding\ArbitraryBase;

trait VirtualIdTrait {
	protected static $_use_short_virtual_id  = false;
	protected static $_virtual_id_dupe_retry = 10;
	protected static $_reserved_virtual_ids  = [];

	public static function useShortVirtualID(bool $use_short_virtual_id = true) {
		static::$_use_short_virtual_id = $use_short_virtual_id;
	}

	public static function genVirtualID(): int {
		return Random::intHash(static::$_use_short_virtual_id);
	}

	protected function beforeCreateSetVirtualID() {
		if (!static::columnExists('virtual_id') || $this->virtual_id !== null) return;

		$this->virtual_id = static::genVirtualID();
		for ($i = 0; $i < static::$_virtual_id_dupe_retry; $i++) {
			if ($i > 0) {
				$this->virtual_id = static::genVirtualID();
			}
			if (in_array($this->virtual_id, static::$_reserved_virtual_ids, true)) {
				continue;
			}
			$stmt = static::getDatabase()->prepare('SELECT COUNT(`virtual_id`) AS `rowcount` FROM ' . $this->getEscapedSource() . ' WHERE `virtual_id` = ?');
			$stmt->bindValue(1, $this->virtual_id, \PDO::PARAM_INT);
			$stmt->execute();
			if ((int)$stmt->fetch()['rowcount'] > 0) {
				continue;
			}
			break;
		}
		if ($i === static::$_virtual_id_dupe_retry) {
			throw new \Exception('Failed to generate unique virtual ID in ' . (int)static::$_virtual_id_dupe_retry . ' attempts');
		}
	}

	public function getVirtualIDBase62() {
		return ArbitraryBase::encode(ArbitraryBase::ALPHABET_BASE62, $this->virtual_id);
	}

	public function getVirtualIDBase36() {
		return ArbitraryBase::encode(ArbitraryBase::ALPHABET_BASE36LOWER, $this->virtual_id);
	}
}
