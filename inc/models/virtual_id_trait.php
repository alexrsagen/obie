<?php namespace ZeroX\Models;
use ZeroX\Random;
use ZeroX\Encoding\ArbitraryBase;

trait VirtualIdTrait {
	protected static $_use_short_virtual_id = false;

	public static function useShortVirtualID(bool $use_short_virtual_id) {
		static::$_use_short_virtual_id = $use_short_virtual_id;
	}

	public function beforeCreateSetVirtualID() {
		if ($this->virtual_id !== null) {
			return;
		}

		$i = 0;
		do {
			$this->virtual_id = Random::intHash(static::$_use_short_virtual_id);
			$stmt = static::getDatabase()->prepare('SELECT COUNT(`virtual_id`) AS `rowcount` FROM ' . $this->getEscapedSource() . ' WHERE `virtual_id` = ?');
			$stmt->bindValue(1, $this->virtual_id, \PDO::PARAM_INT);
			$stmt->execute();
			$i++;
		} while ($i < 10 && (
			in_array($this->virtual_id, VirtualIdModel::RESERVED_IDS, true) ||
			(int)$stmt->fetch()['rowcount'] > 0));

		if ($i === 10) {
			throw new \Exception('Failed to generate unique virtual ID in 10 attempts');
		}
	}

	public function getVirtualIDBase62() {
		return ArbitraryBase::encode(ArbitraryBase::ALPHABET_BASE62, $this->virtual_id);
	}

	public function getVirtualIDBase36() {
		return ArbitraryBase::encode(ArbitraryBase::ALPHABET_BASE36LOWER, $this->virtual_id);
	}
}
