<?php namespace ZeroX\Models;
use ZeroX\Util;
if (!defined('IN_ZEROX')) {
	return;
}

trait VirtualIdTrait {
	private static $_use_short_virtual_id = false;

	public static function useShortVirtualID(bool $use_short_virtual_id) {
		static::$_use_short_virtual_id = $use_short_virtual_id;
	}

	public function beforeCreateSetVirtualID() {
		if ($this->virtual_id !== null) {
			return;
		}

		$i = 0;
		do {
			$this->virtual_id = Util::genVirtualID(static::$_use_short_virtual_id);
			$stmt = static::$db->prepare('SELECT COUNT(`virtual_id`) AS `rowcount` FROM ' . $this->getEscapedSource() . ' WHERE `virtual_id` = ?');
			$stmt->bindValue(1, $this->virtual_id, \PDO::PARAM_INT);
			$stmt->execute();
			$i++;
		} while ($i < 10 && (
			in_array($this->virtual_id, VirtualIDModel::RESERVED_IDS, true) ||
			(int)$stmt->fetch()['rowcount'] > 0));

		if ($i === 10) {
			throw new \Exception('Failed to generate unique virtual ID in 10 attempts');
		}
	}

	public function getVirtualIDBase62() {
		return Util::baseEncode(Util::BASE62_ALPHABET, $this->virtual_id);
	}

	public function getVirtualIDBase36() {
		return Util::baseEncode(Util::BASE36_ALPHABET, $this->virtual_id);
	}
}
