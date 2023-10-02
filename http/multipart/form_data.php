<?php namespace Obie\Http\Multipart;
use Obie\Http\Multipart;
use Obie\Http\QuotedString;
use Obie\Http\Mime;

/**
 * FormData is an RFC 2388 compliant multipart/form-data implementation
 *
 * @property FormDataField[] $fields
 * @property string $boundary
 *
 * @link https://datatracker.ietf.org/doc/html/rfc2388
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

	public function encode(string $transfer_encoding = '8bit'): string {
		$segments = [];
		foreach ($this->fields as $field_name => $field) {
			if ((!is_array($field) || $field === []) && !($field instanceof FormDataField)) continue;
			if (is_array($field)) {
				if (array_keys($field) === range(0, count($field) - 1)) {
					$segment = static::filesToSegment($field, $field_name, transfer_encoding: $transfer_encoding);
				} else {
					$segment = FormDataField::fromArray($field, $field_name)->toSegment(transfer_encoding: $transfer_encoding);
				}
			} else {
				$segment = $field->toSegment(transfer_encoding: $transfer_encoding);
			}
			$segments[] = $segment;
		}
		return Multipart::encode($segments, $this->boundary);
	}

	public static function fieldToSegment(string $name, string $value, string $disposition = self::DISPOSITION_FORM_DATA, ?string $transfer_encoding = null): Segment {
		$headers = [
			'content-disposition' => sprintf(
				'%s; name=%s',
				$disposition,
				QuotedString::encode($name, true),
			)
		];
		if ($transfer_encoding !== null) {
			$headers['content-transfer-encoding'] = $transfer_encoding;
		}
		return new Segment($value, $headers);
	}

	public static function fileToSegment(string $field_name, string $file_name, string $value, string|Mime|null $type = null, array $headers = [], string $disposition = self::DISPOSITION_FORM_DATA, ?string $transfer_encoding = null): Segment {
		return (new FormDataField($value, $file_name, $field_name, $type))->toSegment($headers, $disposition, transfer_encoding: $transfer_encoding);
	}

	/**
	 * Convert an array of FormDataField (or array in $_FILES format) to multiple segments
	 *
	 * @link https://www.php.net/manual/en/features.file-upload.post-method.php $_FILES array format
	 *
	 * @param FormDataField[] $files The files to convert into segments
	 * @param string $field_name The segment name to use (in the Content-Disposition header)
	 * @return Segment[]
	 */
	public static function filesToSegments(array $files): array {
		$segments = [];
		foreach ($files as $file) {
			if (!($file instanceof FormDataField)) continue;

			$segments[] = $file->toSegment(disposition: self::DISPOSITION_FILE);
		}
		return $segments;
	}

	/**
	 * Convert an array of FormDataField (or array in $_FILES format) to a single multipart/mixed segment
	 *
	 * @link https://www.php.net/manual/en/features.file-upload.post-method.php $_FILES array format
	 *
	 * @param array<array|FormDataField> $files The files to include in the multipart/mixed segment
	 * @param string $field_name The multipart/mixed segment name to use (in the Content-Disposition header)
	 * @param null|string $boundary "Boundary" Parameter of multipart/mixed segment
	 * @param null|string $transfer_encoding The Content-Transfer-Encoding to use for each segment in the multipart/mixed body
	 * @return Segment
	 * @throws \Exception
	 */
	public static function filesToSegment(array $files, string $field_name = 'files[]', ?string $boundary = null, ?string $transfer_encoding = null): Segment {
		$boundary ??= Multipart::generateBoundary();
		$segments = [];
		foreach ($files as $file) {
			if (is_array($file)) $file = FormDataField::fromArray($file);
			if (!($file instanceof FormDataField)) continue;
			$segments[] = $file->toSegment(disposition: self::DISPOSITION_FILE, include_name: false, transfer_encoding: $transfer_encoding);
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