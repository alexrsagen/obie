<?php namespace Obie\Formatters;
use \DateTime;

class Time {
	public static function toTimestamp(int|string|DateTime $input): ?int {
		if (is_string($input)) {
			$input = strtotime($input);
			if ($input === false) return null;
		} elseif ($input instanceof DateTime) {
			return $input->getTimestamp();
		}
		return $input;
	}

	public static function toRelativeString(int|string|DateTime $input, int|string|DateTime $now = -1): string {
		if ($now === -1) $now = time();
		$input = static::toTimestamp($input);
		$now = static::toTimestamp($now);
		if ($input === null || $now === null) return 'unknown';

		$prefix = '';
		$suffix = '';
		if ($input > $now) {
			$prefix = 'in ';
		} else {
			$suffix = ' ago';
		}

		if (date('Y', $now) === date('Y', $input)) {
			if (date('M', $now) === date('M', $input)) {
				if (date('j', $now) === date('j', $input)) {
					if (date('H', $now) === date('H', $input)) {
						if (date('i', $now) === date('i', $input)) {
							if (date('s', $now) === date('s', $input)) {
								// Same second
								return 'just now';
							}
							// Same minute
							$offset = (int)date('s', $now) - (int)date('s', $input);
							$offset = ($offset < 0 ? -$offset : $offset);
							return sprintf('%s%d second%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
						}
						// Same hour
						$offset = (int)date('i', $now) - (int)date('i', $input);
						$offset = ($offset < 0 ? -$offset : $offset);
						return sprintf('%s%d minute%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
					}
					// Same day
					$offset = (int)date('G', $now) - (int)date('G', $input);
					$offset = ($offset < 0 ? -$offset : $offset);
					return sprintf('%s%d hour%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
				}
				// Same month
				$offset = (int)date('j', $now) - (int)date('j', $input);
				$offset = ($offset < 0 ? -$offset : $offset);
				return sprintf('%s%d day%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
			}
			// Same year
			return 'on ' . date('j M', $input);
		}
		return 'on ' . date('j M Y', $input);
	}
}