<?php namespace Obie\Http;
use Obie\Http\Multipart\Segment;

class Multipart {
	const MIME_BASETYPE = 'multipart';
	const MIME_SUBTYPE_ALTERNATIVE = 'alternative';
	const MIME_SUBTYPE_FORM_DATA = 'form-data';
	const MIME_SUBTYPE_MIXED = 'mixed';
	const MIME_SUBTYPE_DIGEST = 'digest';
	const MIME_SUBTYPE_RELATED = 'related';

	const ENC_ALTERNATIVE = self::MIME_BASETYPE . '/' . self::MIME_SUBTYPE_ALTERNATIVE;
	const ENC_FORM_DATA   = self::MIME_BASETYPE . '/' . self::MIME_SUBTYPE_FORM_DATA;
	const ENC_MIXED       = self::MIME_BASETYPE . '/' . self::MIME_SUBTYPE_MIXED;
	const ENC_DIGEST      = self::MIME_BASETYPE . '/' . self::MIME_SUBTYPE_DIGEST;
	const ENC_RELATED     = self::MIME_BASETYPE . '/' . self::MIME_SUBTYPE_RELATED;

	/**
	 * Decode a multipart body into an array of segments
	 *
	 * @param string $raw
	 * @param string $boundary
	 * @return Segment[]|null Returns null on decode failure
	 */
	public static function decode(string $raw, ?string $boundary = null): ?array {
		// guess boundary if not provided
		$boundary ??= static::findBoundary($raw);
		if (!$boundary) return null;

		$raw_len = strlen($raw);
		$segments = [];

		$offset = 0;
		while ($offset < $raw_len) {
			$bpos_start = strpos($raw, "--" . $boundary, $offset);
			if ($bpos_start === false) break;

			$lfpos_start = strpos($raw, "\n", $bpos_start + 1);
			if ($lfpos_start === false) break;

			$bpos_end = strpos($raw, "--" . $boundary, $lfpos_start + 1);
			if ($bpos_end === false) break;

			$segment_raw = substr($raw, $lfpos_start + 1, $bpos_end - $lfpos_start - 1);
			if ($segment_raw === false) return null;

			$segment_raw = rtrim($segment_raw, "\r\n");
			$segment = Segment::decode($segment_raw);
			if ($segment !== null) $segments[] = $segment;

			$offset = $bpos_end;
		}
		return $segments;
	}

	/**
	 * Encode an array of segments into a multipart body
	 *
	 * @param Segment[] $segments Ignores any item that is not a Segment
	 * @param string $boundary
	 * @return string
	 */
	public static function encode(array $segments, string $boundary): string {
		$raw = '';
		foreach ($segments as $segment) {
			if (!($segment instanceof Segment)) continue;
			$raw .= '--' . $boundary . "\r\n";
			$raw .= $segment->build() . "\r\n";
		}
		$raw .= '--' . $boundary . "--\r\n";
		return $raw;
	}

	public static function generateBoundary(int $length = 60) {
		return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
	}

	public static function findBoundary(string $raw): ?string {
		$bpos_start = strpos($raw, "--");
		if ($bpos_start === false) return null;
		$lfpos_start = strpos($raw, "\n", $bpos_start);
		if ($lfpos_start === false) return null;
		return trim(substr($raw, $bpos_start + 2, $lfpos_start - 1));
	}
}