<?php namespace Obie\Encoding\Multipart;
use \Obie\Encoding\Multipart;
use \Obie\Encoding\Multipart\Segment;
use Obie\Encoding\Rfc8187;
use \Obie\Http\Mime;
use Obie\Http\QuotedString;

class FormData {
	public static function decode(string $raw, string $boundary = ''): array {
		$form_data = ['files' => [], 'fields' => []];

		// parse multipart data
		$segments = Multipart::decode($raw, $boundary);
		for ($i = count($segments) - 1; $i >= 0; $i--) {
			// parse content-disposition header
			$disp = array_merge_recursive(...array_map(function($v) {
				$kv = explode('=', trim($v), 2);
				$key = $kv[0];
				$val = count($kv) > 1 ? $kv[1] : '';
				if (str_ends_with($key, '*')) {
					$key = substr($key, 0, -1);
					$val = Rfc8187::decode($val);
				} else {
					$val = QuotedString::decode($val) ?? $val;
				}
				return [$key => $val];
			}, explode(';', $segments[$i]->getHeader('content-disposition'))));

			// get filename
			$filename = array_key_exists('filename', $disp) ? $disp['filename'] : null;

			// get file mime type
			$filetype = $segments[$i]->getHeader('content-type');
			if ($filetype !== null) {
				$filetype = trim(explode(';', $filetype, 2)[0]);
			}
			if (empty($filetype) && !empty($filename)) {
				$filetype = Mime::getTypeByFilename($filename);
			}

			// store data
			if (array_key_exists('name', $disp)) {
				if ($filename !== null) {
					$form_data['files'][$disp['name']] = [
						'name' => $filename,
						'type' => $filetype,
						'size' => $segments[$i]->getBodySize(),
						'body' => $segments[$i]->getBody()
					];
				} else {
					$form_data['fields'][$disp['name']] = $segments[$i]->getBody();
				}
			}

			// free segment
			unset($segments[$i]);
		}

		return $form_data;
	}

	public static function encode(array $fields = [], array $files = [], string $boundary): string {
		$segments = [];

		foreach ($fields as $k => $v) {
			$segments[] = static::fieldToSegment($k, $v);
		}

		foreach ($files as $k => $v) {
			if (
				!is_array($v) ||
				!array_key_exists('name', $v) ||
				!array_key_exists('body', $v)
			) continue;

			$type = null;
			if (array_key_exists('type', $v)) {
				$type = $v['type'];
			}
			$segments[] = static::fileToSegment($k, $v['name'], $v['body'], $type);
		}

		return Multipart::encode($segments, $boundary);
	}

	public static function fieldToSegment(string $name, string $value): Segment {
		return new Segment($value, [
			'content-disposition' => sprintf(
				'form-data; name=%s',
				QuotedString::encode($name, true),
			)
		]);
	}

	public static function fileToSegment(string $field_name, string $file_name, string $value, string|Mime|null $type = null): Segment {
		return new Segment($value, [
			'content-type' => $type->encode(),
			'content-disposition' => sprintf(
				'form-data; name=%s; filename=%s; filename*=%s',
				QuotedString::encode($field_name, true),
				QuotedString::encode($file_name, true),
				Rfc8187::encode($file_name)
			),
			'content-transfer-encoding' => 'base64',
		]);
	}
}