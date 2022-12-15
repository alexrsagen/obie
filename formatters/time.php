<?php namespace Obie\Formatters;
use \DateTime;
use \DateTimeZone;

class Time {
	public static function toTimestamp(int|string|DateTime|null $input): ?int {
		if (is_string($input)) {
			$input = strtotime($input);
			if ($input === false) return null;
		} elseif ($input instanceof DateTime) {
			return $input->getTimestamp();
		}
		return $input;
	}

	public static function toDateTime(int|string|DateTime|null $input, ?DateTimeZone $timezone = null): ?DateTime {
		if (is_int($input)) {
			return (new DateTime('now', $timezone))->setTimestamp($input);
		} elseif (is_string($input)) {
			return new DateTime($input, $timezone);
		}
		return $input;
	}

	public static function toRelativeString(int|string|DateTime $input, int|string|DateTime|null $now = null, ?DateTimeZone $timezone = null, int $precision = 0, string $past_prefix = '', string $past_suffix = ' ago', string $future_prefix = 'in ', string $future_suffix = '', string $nowstr = 'just now', string $fallback = 'unknown'): string {
		$input = static::toDateTime($input);
		$now = static::toDateTime($now) ?? new DateTime('now', $timezone);
		if ($input === null || $now === null) return $fallback;
		$diff = $now->diff($input);

		if ($diff->invert === 0) {
			$prefix = $future_prefix;
			$suffix = $future_suffix;
		} elseif ($diff->invert === 1) {
			$prefix = $past_prefix;
			$suffix = $past_suffix;
		} else {
			$prefix = '';
			$suffix = '';
		}

		$precision_steps = [
			'y' => 'year',
			'm' => 'month',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second'
		];
		$str = '';
		$i = 0;
		foreach ($precision_steps as $step => $display) {
			if ($precision < $i) break;
			$val = $diff->$step;
			if ($val > 0) {
				$str .= sprintf('%s%d %s%s', (strlen($str) > 0 ? ', ' : ''), $val, $display, ($val === 1 ? '' : 's'));
			}
			$i++;
		}
		if (strlen($str) === 0) return $nowstr;
		return $prefix . $str . $suffix;
	}
}