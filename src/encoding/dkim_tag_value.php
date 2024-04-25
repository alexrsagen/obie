<?php namespace Obie\Encoding;
use Obie\Encoding\DkimTagValue\Tag;
use Obie\Encoding\DkimTagValue\TagList;
use Obie\Log;

/**
 * RFC 6376 compliant DKIM tag-value decoder/encoder
 */
class DkimTagValue {

	const VERSION_DKIM1 = 'DKIM1';
	const VERSION_DMARC1 = 'DMARC1';

	/**
	 * Decode a DKIM tag-value list
	 *
	 * @param string $input
	 * @param bool $strict
	 * @param string $version
	 * @return ?TagList
	 */
	public static function decode(string $input, bool $strict = true, string $version = self::VERSION_DKIM1): ?TagList {
		$output = new TagList();

		$tag_list = explode(';', $input);
		foreach ($tag_list as $tag_spec) {
			$tag_name_value = explode('=', $tag_spec, 2);
			if (count($tag_name_value) !== 2) {
				Log::warning('DkimTagValue: unexpected tag-spec without tag-value');
				return null;
			}
			list($tag_name, $tag_value) = $tag_name_value;

			// remove leading and trailing folding white space (FWS)
			$tag_name = trim($tag_name);

			// remove leading, trailing and contained folding white space (FWS)
			$tag_value = preg_replace('/[ \t]*\r?\n[ \t]+/', '', trim($tag_value));

			// perform quoted-printable decoding
			if ($tag_name === 'n') {
				$tag_value = quoted_printable_decode($tag_value);
			}

			$output->tags[] = new Tag($tag_name, $tag_value);
		}

		if ($strict && !$output->isValid($version)) return null;
		return $output;
	}

	/**
	 * Encode a DKIM tag-value list
	 *
	 * @param TagList $input
	 * @param string $version
	 * @return string
	 */
	public static function encode(TagList $input, string $version = self::VERSION_DKIM1): string {
		$output = '';
		$i = 0;
		foreach ($input->tags as $tag) {
			if ($i > 0) {
				$output .= ';';
			}
			$output .= $tag->name;
			$output .= '=';
			if ($version === self::VERSION_DKIM1 && $tag->name === 'n') {
				$value = quoted_printable_encode($tag->value);
				for ($j = strlen($value) - 1; $j >= 0; $j--) {
					$ord = ord($value[$j]);
					if ($ord <= 0x20 || $ord >= 0x7F || $ord === 0x3B) {
						$value = substr($value, 0, $j) . '=' . strtoupper(dechex($ord)) . substr($value, $j+1);
					}
				}
				$output .= $value;
			} else {
				$output .= $tag->value;
			}
			$i++;
		}
		return $output;
	}
}