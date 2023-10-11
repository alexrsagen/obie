<?php namespace Obie\Http\Multipart;
use Obie\Http\ContentDispositionHeader;
use Obie\Http\Mime;
use Obie\Http\Multipart;
use Obie\Random;

class FormDataField {
	const DEFAULT_FIELD_NAME = 'file';

	public ?Mime $type = null;
	function __construct(
		public string $content,
		public string $name = self::DEFAULT_FIELD_NAME,
		public ?string $filename = null,
		string|Mime|null $type = null,
	) {
		if (is_string($type)) {
			$type = Mime::decode($type);
		}
		if ($filename !== null && $type === null) {
			$type = Mime::getByFilename($filename);
		}
		if ($type instanceof Mime) {
			$this->type = $type;
		}
	}

	/**
	 * Convert a Segment to a single FormDataField or an array of FormDataField
	 *
	 * @param Segment $segment
	 * @param int $max_nesting_level Max amount of nested multipart/mixed segments to explore
	 * @param null|string $name_fallback Fixed fallback name to use if the Content-Disposition header is missing a name (a random one starting with 'unk_' will be generated if not specified)
	 * @return static|array|null A single FormDataField, an array of FormDataField or null on failure (max nesting level exceeded, nested decoding failure)
	 * @throws \Exception
	 */
	public static function fromSegment(Segment $segment, int $max_nesting_level = 1, ?string $name_fallback = null): static|array|null {
		$name_fallback ??= 'unk_' . Random::string(10);

		// parse content-disposition header
		$cd = ContentDispositionHeader::decode($segment->getHeader('content-disposition'));

		// get filename
		$filename = array_key_exists('filename', $cd->parameters) ? $cd->parameters['filename']: null;

		// parse content-type header
		$type = $segment->getHeader('content-type');
		if ($type !== null) {
			$type = Mime::decode($type);
		}
		// as a fallback, get content-type from filename
		if (empty($type) && !empty($filename)) {
			$type = Mime::getByFilename($filename);
		}

		// get field name (generate random if not found)
		$name = array_key_exists('name', $cd->parameters) ? $cd->parameters['name'] : $name_fallback;

		// get field(s) from segment
		if ($type?->type === Multipart::MIME_BASETYPE && $type?->subtype === Multipart::MIME_SUBTYPE_MIXED) {
			if ($max_nesting_level < 1) return null;

			$boundary = $type?->getParameter('boundary');
			$nested_segments = Multipart::decode($segment->getBody(), $boundary);
			if ($nested_segments === null) return null;

			$fields = [];
			foreach ($nested_segments as $nested_segment) {
				$nested_fields = static::fromSegment($nested_segment, $max_nesting_level - 1, $name);
				if ($nested_fields === null) continue;
				if (is_array($nested_fields)) {
					$fields = array_merge($fields, $nested_fields);
				} else {
					$fields[] = $nested_fields;
				}
			}

			if (count($fields) === 1) return $fields[0];
			return $fields;
		}

		return new static($segment->getBody(), $name, $filename, $type);
	}

	public static function fromFile(string $file_path, string $name = self::DEFAULT_FIELD_NAME): ?static {
		$filename = basename($file_path);
		$type = Mime::getByFilename($filename);
		$content = file_get_contents($file_path);
		if ($content === false) return null;
		return new static($content, $name, $filename, $type);
	}

	public function toSegment(array $headers = [], string $disposition = ContentDispositionHeader::DISP_FORM_DATA, bool $include_name = true, bool $include_filename = true, bool $include_utf8_filename = false, ?string $transfer_encoding = null): Segment {
		if ($this->type !== null) {
			$headers['content-type'] = $this->type->encode();
		}

		$cd = new ContentDispositionHeader($disposition);
		if ($include_name && strlen($this->name) > 0) {
			$cd->parameters['name'] = $this->name;
		}
		if ($include_filename && is_string($this->filename) && strlen($this->filename) > 0) {
			$cd->parameters['filename'] = $this->filename;
		}
		$headers['content-disposition'] = $cd->encode(extended_header_value: $include_utf8_filename);

		if ($transfer_encoding !== null) {
			$headers['content-transfer-encoding'] = $transfer_encoding;
		}
		return new Segment($this->content, $headers);
	}
}