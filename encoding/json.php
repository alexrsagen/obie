<?php namespace Obie\Encoding;
use \Obie\Log;

class Json {
	public static function encode($input, int $options = 0): string {
		try {
			$output = json_encode($input, $options | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
			return $output;
		} catch (\JsonException $e) {
			if ($options & JSON_THROW_ON_ERROR) throw $e;
			Log::error($e->getMessage(), ['prefix' => 'JSON encode error:']);
			Log::debug($e->getTraceAsString(), ['prefix' => 'Stack trace:']);
			return '';
		}
	}

	public static function decode(string $input, bool $assoc = true, int $depth = 512, int $options = 0): mixed {
		try {
			$output = json_decode($input, $assoc, $depth, $options | JSON_THROW_ON_ERROR);
			return $output;
		} catch (\JsonException $e) {
			if ($options & JSON_THROW_ON_ERROR) throw $e;
			Log::error($e->getMessage(), ['prefix' => 'JSON decode error:']);
			Log::debug($e->getTraceAsString(), ['prefix' => 'Stack trace:']);
			return null;
		}
	}
}