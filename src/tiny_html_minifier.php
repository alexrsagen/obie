<?php namespace Obie;
use Obie\Encoding\Json;

// Originally sourced from: https://github.com/jenstornell/tiny-html-minifier
//
// MIT License
//
// Copyright (c) 2017 Jens Törnell
// Modifications copyright (c) 2019 - 2022 Alexander Sagen
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
class TinyHtmlMinifier {
	function __construct(
		public array $options = [],
		public array $tags_skip = [
			'code',
			'pre',
			'textarea'
		],
		public array $tags_inline = [
			'a',
			'abbr',
			'acronym',
			'b',
			'bdo',
			'big',
			'br',
			'cite',
			'code',
			'dfn',
			'em',
			'i',
			'img',
			'kbd',
			'map',
			'object',
			'samp',
			'small',
			'span',
			'strong',
			'sub',
			'sup',
			'tt',
			'var',
			'q',
		],
		public array $tags_hard = [
			'!doctype',
			'body',
			'html',
		],
	) {}


	public function minify(string $html): string {
		// Remove comments
		if (!empty($this->options['keep_empty_comments'])) {
			$html = preg_replace('/(?!<!-- -->)<!--(?:.|\s)*?-->/', '', $html);
		} else {
			$html = preg_replace('/(?=<!--)([\s\S]*?)-->/', '', $html);
		}

		// Walk trough html
		$output = '';
		$skip_level = 0;
		$skip_name = '';
		$rest = $html;
		do {
			$parts             = explode('<', $rest, 2);
			$tag_parts         = explode('>', $parts[0], 2);
			$tag_content       = $tag_parts[0];
			$tag_content_fixed = false;

		HANDLE_TAG_CONTENT:
			if (!empty($tag_content)) {
				$name = explode(" ", $tag_content, 2)[0];
				$name = explode(">", $name, 2)[0];
				$name = explode("\n", $name, 2)[0];
				$name = preg_replace('/\s+/', '', $name);
				$name = strtolower(str_replace('/', '', $name));

				// Strip whitespace from element
				$element = $tag_content;
				if ($skip_level === 0) {
					$element = preg_replace('/\s+/', ' ', $element);
				}
				// Add chevrons around element
				$element = '<' . $element . (str_contains($parts[0], '>') ? '>' : '');
				// Remove unneeded self slash
				if (substr($element, -3) === ' />') {
					$element = substr($element, 0, -3) . '>';
				}
				// Remove unneeded element meta
				if ($name === 'style') {
					$element = str_replace([
						' type="text/css"',
						"' type='text/css'"
					],
					['', ''], $element);
				} elseif ($name === 'script') {
					$element = str_replace([
						' type="text/javascript"',
						" type='text/javascript'"
					],
					['', ''], $element);
				}

				$type = (substr($element, 1, 1) === '/') ? 'close' : 'open';

				// Perform additional processing for "raw text elements"
				// which may contain tag open (<) and tag close (>) characters
				if (($name === 'script' || $name === 'style') && $type === 'open' && !$tag_content_fixed) {
					while (count($parts) > 1 && strlen($parts[1]) > 0 && $parts[1][0] !== '/') {
						$extra = explode('<', $parts[1], 2);
						$parts = [$parts[0] . '<' . $extra[0], count($extra) > 1 ? $extra[1] : ''];
						unset($extra);
						$tag_content_fixed = true;
					}
					if ($tag_content_fixed) {
						$tag_parts   = explode('>', $parts[0], 2);
						$tag_content = $tag_parts[0];
						goto HANDLE_TAG_CONTENT;
					}
				}

				// Add element to output
				if (!empty($this->options['collapse_whitespace']) && !in_array($name, $this->tags_inline)) {
					$element = trim($element);
				}
				if (empty($this->options['collapse_whitespace']) || strlen($element) > 0) {
					$output .= $element;
				}

				// Set skip level if elements are blocked from minification
				if (in_array($name, $this->tags_skip)) {
					if ($skip_level === 0) $skip_name = $name;
					if ($type === 'open') {
						$skip_level++;
					}
					if ($type === 'close') {
						$skip_level--;
					}
				}

				// Minify content
				if (!empty($tag_content)) {
					$content_tag_name = $skip_level !== 0 ? $skip_name : $name;
					$content = (isset($tag_parts[1])) ? $tag_parts[1] : '';
					if ($content !== '') {
						if ($skip_level === 0 && $content_tag_name !== 'script' && $content_tag_name !== 'style') {
							$content = preg_replace('/\s+/', ' ', $content);
						}
						if ($content_tag_name === 'script') {
							if (str_contains(strtolower($element), 'application/ld+json') && !empty($this->options['collapse_json_ld'])) {
								$content = Json::encode(Json::decode($content));
							} else {
								$content = Minify::JS($content);
							}
						} elseif ($content_tag_name === 'style') {
							$content = Minify::CSS($content);
						} elseif (in_array($content_tag_name, $this->tags_skip)) {
							$content = $content;
						} elseif (in_array($content_tag_name, $this->tags_hard) || $name === 'head' && $type === 'open') {
							$content = trim($content);
						}
					}

					// Add content to output
					if (!empty($this->options['collapse_whitespace']) && !in_array($content_tag_name, $this->tags_inline)) {
						$content = trim($content);
					}
					if (empty($this->options['collapse_whitespace']) || strlen($content) > 0) {
						$output .= $content;
					}
				}
			}

			$rest = count($parts) > 1 ? $parts[1] : '';
		} while (count($parts) > 1);

		return $output;
	}
}
