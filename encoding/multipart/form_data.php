<?php namespace Obie\Encoding\Multipart;
use \Obie\Encoding\Multipart;
use \Obie\Http\Mime;
use \Obie\Http\QuotedString;

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
		foreach ($this->fields as $field_name => $field) {
			if ((!is_array($field) || $field === []) && !($field instanceof FormDataField)) continue;
			if (is_array($field)) {
				if (array_keys($field) === range(0, count($field) - 1)) {
					$segment = static::filesToSegment($field, $field_name);
				} else {
					$segment = FormDataField::fromArray($field, $field_name)->toSegment();
				}
			} else {
				$segment = $field->toSegment();
			}
			$segments[] = $segment;
		}
		return Multipart::encode($segments, $this->boundary);
	}

	public static function fieldToSegment(string $name, string $value, string $disposition = self::DISPOSITION_FORM_DATA): Segment {
		return new Segment($value, [
			'content-disposition' => sprintf(
				'%s; name=%s',
				$disposition,
				QuotedString::encode($name, true),
			)
		]);
	}

	public static function fileToSegment(string $field_name, string $file_name, string $value, string|Mime|null $type = null, array $headers = [], string $disposition = self::DISPOSITION_FORM_DATA): Segment {
		return (new FormDataField($value, $file_name, $field_name, $type))->toSegment($headers, $disposition);
	}

	public static function filesToSegment(array $files, string $field_name = 'files[]', ?string $boundary = null): Segment {
		$boundary ??= Multipart::generateBoundary();
		$segments = [];
		foreach ($files as $file) {
			if (is_array($file)) $file = FormDataField::fromArray($file);
			if (!($file instanceof FormDataField)) continue;
			$segments[] = $file->toSegment(disposition: self::DISPOSITION_FILE, include_name: false);
		}
		$content = Multipart::encode($segments, $boundary);
		return new Segment($content, [
			'content-disposition' => sprintf(
				'%s; name=%s',
				self::DISPOSITION_FORM_DATA,
				QuotedString::encode($field_name, true),
			),
			'content-type' => Mime::decode(Multipart::ENC_MIXED)->setParameter('boundary', $boundary)->encode(),
		]);
	}
}