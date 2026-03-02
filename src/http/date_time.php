<?php namespace Obie\Http;

class DateTime extends \DateTime {
	const HTTP_DATE_FORMATS = [
		\DateTime::RFC1123,
		\DateTime::RFC1036,
		'D M  j H:i:s Y',
		'D M d H:i:s Y',
	];

	public static function createFromHttpDate(string $datetime, ?\DateTimeZone $timezone = null): DateTime|false {
		foreach (static::HTTP_DATE_FORMATS as $format) {
			$output = \DateTime::createFromFormat($format, $datetime, $timezone);
			if ($output !== false) return $output;
		}
		return false;
	}
}
