<?php namespace Obie\Encoding\Multipart;
use \Obie\Http\Mime;
use \Obie\Http\QuotedString;
use \Obie\Encoding\Multipart;
use \Obie\Encoding\Rfc8187;
use \Obie\Random;

class FormDataField {
	public ?Mime $type = null;
	function __construct(
		public string $content,
		public string $name = 'file',
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

	public static function fromArray(array $input, string $name_fallback = 'file'): ?static {
		$content = array_key_exists('body', $input) ? $input['body'] : null;
		$content ??= array_key_exists('content', $input) ? $input['content'] : null;
		if ($content === null) return null;

		$name = array_key_exists('name', $input) ? $input['name'] : null;
		$name ??= array_key_exists('field', $input) ? $input['field'] : null;
		$name ??= $name_fallback;

		$filename = array_key_exists('filename', $input) ? $input['filename'] : null;
		$filename ??= $name;

		$type = array_key_exists('type', $input) ? $input['type'] : null;

		return new static($content, $name, $filename, $type);
	}

	public static function fromSegment(Segment $segment, int $max_nesting_level = 1, ?string $name_fallback = null): static|array|null {
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
		}, explode(';', $segment->getHeader('content-disposition'))));

		// get filename
		$filename = array_key_exists('filename', $disp)
			? (is_array($disp['filename'])
				? $disp['filename'][count($disp['filename'])-1]
				: $disp['filename']
			) : null;

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
		$name_fallback ??= 'unk_' . Random::string(10);
		$name = array_key_exists('name', $disp) ? $disp['name'] : $name_fallback;

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

	public static function fromFile(string $file_path, string $name = 'file'): ?static {
		$filename = basename($file_path);
		$type = Mime::getByFilename($filename);
		$content = file_get_contents($file_path);
		if ($content === false) return null;
		return new static($content, $name, $filename, $type);
	}

	public function toSegment(array $headers = [], string $disposition = 'form-data', bool $include_name = true, bool $include_filename = true, bool $include_utf8_filename = true): Segment {
		if ($this->type !== null) $headers['content-type'] = $this->type->encode();
		$headers['content-disposition'] = $disposition;
		if ($include_name) {
			$headers['content-disposition'] .= '; name=' . QuotedString::encode($this->name, true);
		}
		if ($include_filename) {
			$headers['content-disposition'] .= '; filename=' . QuotedString::encode($this->filename, true);
			if ($include_utf8_filename) {
				$headers['content-disposition'] .= '; filename*=' . Rfc8187::encode($this->filename);
			}
		}
		$headers['content-transfer-encoding'] = 'base64';
		return new Segment($this->content, $headers);
	}
}