<?php namespace Obie\Http\Multipart;
use Obie\Http\Multipart;
use Obie\Http\Mime;

/**
 * FormData is an RFC 7578 compliant multipart/form-data implementation
 *
 * @property FormDataField[] $fields
 * @property string $boundary
 *
 * @link https://datatracker.ietf.org/doc/html/rfc7578
 * @package Obie\Encoding\Multipart
 */
class FormData {
	const DISPOSITION_FORM_DATA = 'form-data';
	const DISPOSITION_FILE = 'file';

	function __construct(
		public array $fields = [],
		public string $boundary = '',
	) {
		if (strlen($boundary) === 0) $boundary = Multipart::generateBoundary();
		$this->boundary = $boundary;
	}

	public static function decode(string $raw, ?string $boundary = null): ?static {
		// guess boundary if not provided
		$boundary ??= Multipart::findBoundary($raw);
		if (!$boundary) return null;

		// parse multipart data
		$form_data = new static(boundary: $boundary);
		$segments = Multipart::decode($raw, $boundary);
		if (!$segments) return null;

		foreach ($segments as $segment) {
			$segment_fields = FormDataField::fromSegment($segment);
			if (!$segment_fields) continue;

			if (is_array($segment_fields)) {
				$form_data->fields = array_merge($form_data->fields, $segment_fields);
			} else {
				$form_data->fields[] = $segment_fields;
			}
		}

		return $form_data;
	}

	public function encode(): string {
		$segments = [];
		foreach ($this->fields as $field) {
			if (!($field instanceof FormDataField)) continue;
			$segments[] = $field->toSegment();
		}
		return Multipart::encode($segments, $this->boundary);
	}

	public static function newFieldSegment(string $name, string $value, array $headers = [], string $disposition = self::DISPOSITION_FORM_DATA): Segment {
		return (new FormDataField($value, $name))->toSegment($headers, $disposition);
	}

	public static function newFileSegment(string $field_name, string $file_name, string $value, string|Mime|null $type = null, array $headers = [], string $disposition = self::DISPOSITION_FILE): Segment {
		return (new FormDataField($value, $field_name, $file_name, $type))->toSegment($headers, $disposition);
	}
}