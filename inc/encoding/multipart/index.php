<?php namespace ZeroX\Encoding;
use \ZeroX\Encoding\Multipart\Segment;

class Multipart {
	const ENC_ALTERNATIVE = 'multipart/alternative';
	const ENC_FORM_DATA   = 'multipart/form-data';
	const ENC_MIXED       = 'multipart/mixed';
	const ENC_DIGEST      = 'multipart/digest';

	public static function decode(string $raw, string $boundary = ''): array {
		$raw_len = strlen($raw);
		$segments = [];

		// guess boundary if not provided
		if (strlen($boundary) === 0) {
			$boundary = static::findBoundary($raw);
		}

		$offset = 0;
		while (
			$offset < $raw_len &&
			($bpos_start = strpos($raw, "--" . $boundary, $offset)) !== false &&
			($lfpos_start = strpos($raw, "\n", $bpos_start + 1)) !== false &&
			($bpos_end = strpos($raw, "--" . $boundary, $lfpos_start + 1)) !== false &&
			($segment_raw = substr($raw, $lfpos_start + 1, $bpos_end - $lfpos_start - 1)) !== false
		) {
			$segment_raw = rtrim($segment_raw, "\r\n");
			$segments[] = Segment::decode($segment_raw);
			$offset = $bpos_end;
		}
		return $segments;
	}

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

	public static function findBoundary(string $raw): string {
		$bpos_start = strpos($raw, "--");
		$lfpos_start = strpos($raw, "\n", $bpos_start);
		return trim(substr($raw, $bpos_start + 2, $lfpos_start - 1));
	}
}