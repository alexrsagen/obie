<?php namespace Obie\Encoding\DkimTagValue;
use Obie\Encoding\DkimTagValue;

/**
 * @property Tag[] $tags
 */
class TagList {
	function __construct(
		public array $tags = [],
	) {}

	/**
	 * Get a single tag from the list.
	 *
	 * @param string $name
	 * @return Tag[]|Tag|null Returns an array if multiple tags matching $name were found. Returns null if no tags matching $name were found.
	 */
	public function get(string $name): array|Tag|null {
		$output = [];
		foreach ($this->tags as $tag) {
			if ($tag->name === $name) {
				$output[] = $tag;
			}
		}
		if (count($output) === 0) return null;
		if (count($output) === 1) return $output[0];
		return $output;
	}

	public function isValid(string $version = DkimTagValue::VERSION_DKIM1): bool {
		$i = 0;
		foreach ($this->tags as $tag) {
			// The v= tag MUST be the first tag in the record.
			if ($tag->name === 'v' && $i !== 0) return false;
			if (!$tag->isValid($version)) return false;
			$i++;
		}
		return true;
	}

	public function __toString(): string {
		return DkimTagValue::encode($this);
	}
}