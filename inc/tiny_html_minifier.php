<?php namespace ZeroX;
use ZeroX\Encoding\Json;
if (!defined('IN_ZEROX')) {
	return;
}

// Obtained from: https://github.com/jenstornell/tiny-html-minifier/blob/master/tiny-html-minifier.php
//
// MIT License
//
// Copyright (c) 2017 Jens Törnell
// Modifications (c) 2019 Alexander Sagen
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
	public function __construct($options) {
		$this->options = $options;
		$this->output = '';
		$this->build = [];
		$this->skip = 0;
		$this->skipName = '';
		$this->head = false;
		$this->elements = [
			'skip' => [
				'code',
				'pre',
				'textarea'
			],
			'inline' => [
				'b',
				'big',
				'i',
				'small',
				'tt',
				'abbr',
				'acronym',
				'cite',
				'code',
				'dfn',
				'em',
				'kbd',
				'strong',
				'samp',
				'var',
				'a',
				'bdo',
				'br',
				'img',
				'map',
				'object',
				'q',
				'span',
				'sub',
				'sup',
			],
			'hard' => [
				'!doctype',
				'body',
				'html',
			]
		];
	}

	// Run minifier
	function minify($html) {
		$html = $this->removeComments($html);
		$rest = $html;
		while ($this->walk($rest)) {}
		return $this->output;
	}

	// Walk trough html
	function walk(&$rest) {
		$parts             = explode('<', $rest, 2);
		$tag_parts         = explode('>', $parts[0], 2);
		$tag_content       = $tag_parts[0];
		$tag_content_fixed = false;

		HANDLE_TAG_CONTENT:
		if (!empty($tag_content)) {
			$name    = $this->findName($tag_content);
			$element = $this->toElement($tag_content, $parts[0], $name);
			$type    = $this->toType($element);

			// Find and set document head
			if ($name === 'head') {
				$this->head = ($type === 'open') ? true : false;
			}

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

			$this->build[] = [
				'name' => $name,
				'content' => $element,
				'type' => $type
			];

			$this->setSkip($name, $type);

			if (!empty($tag_content)) {
				$content = (isset($tag_parts[1])) ? $tag_parts[1] : '';
				if ($content !== '') {
					$this->build[] = [
						'content' => $this->compact($content, $name, $element),
						'type' => 'content'
					];
				}
			}

			$this->buildHtml();
		}

		$rest = count($parts) > 1 ? $parts[1] : '';
		return count($parts) > 1;
	}

	// Remove comments
	function removeComments($content = '') {
		return preg_replace('/<!--(.|\s)*?-->/', '', $content);
	}

	// Check if string contains string
	function contains($needle, $haystack) {
		return strpos($haystack, $needle) !== false;
	}

	// Return type of element
	function toType($element) {
		$type = (substr($element, 1, 1) === '/') ? 'close' : 'open';
		return $type;
	}

	// Create element
	function toElement($element, $noll, $name) {
		$element = $this->stripWhitespace($element);
		$element = $this->addChevrons($element, $noll);
		$element = $this->removeSelfSlash($element);
		$element = $this->removeMeta($element, $name);
		return $element;
	}

	// Remove unneeded element meta
	function removeMeta($element, $name) {
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
		return $element;
	}

	// Strip whitespace from element
	function stripWhitespace($element) {
		if ($this->skip === 0) {
			$element = preg_replace('/\s+/', ' ', $element);
		}
		return $element;
	}

	// Add chevrons around element
	function addChevrons($element, $noll) {
		$char = ($this->contains('>', $noll)) ? '>' : '';
		$element = '<' . $element . $char;
		return $element;
	}

	// Remove unneeded self slash
	function removeSelfSlash($element) {
		if (substr($element, -3) === ' />') {
			$element = substr($element, 0, -3) . '>';
		}
		return $element;
	}

	// Compact content
	function compact($content, $name, $element) {
		if ($this->skip !== 0) {
			$name = $this->skipName;
		} elseif ($name !== 'script' && $name !== 'style') {
			$content = preg_replace('/\s+/', ' ', $content);
		}

		if ($this->isSchema($name, $element) && !empty($this->options['collapse_json_ld'])) {
			return Json::encode(Json::decode($content));
		} elseif ($name === 'script') {
			return Minify::JS($content);
		} elseif ($name === 'style') {
			return Minify::CSS($content);
		} elseif (in_array($name, $this->elements['skip'])) {
			return $content;
		} elseif (in_array($name, $this->elements['hard']) || $this->head) {
			return $this->minifyHard($content);
		} else {
			return $this->minifyKeepSpaces($content);
		}
	}

	function isSchema($name, $element) {
		if ($name !== 'script') return false;

		$element = strtolower($element);
		if ($this->contains('application/ld+json', $element)) return true;
		return false;
	}

	// Build html
	function buildHtml() {
		foreach($this->build as $build) {
			if (!empty($this->options['collapse_whitespace'])) {
				if (strlen(trim($build['content'])) === 0) {
					continue;
				} elseif ($build['type'] !== 'content' && !in_array($build['name'], $this->elements['inline'])) {
					trim($build['content']);
				}
			}
			$this->output .= $build['content'];
		}

		$this->build = [];
	}

	// Find name by part
	function findName($part) {
		$name_cut = explode(" ", $part, 2)[0];
		$name_cut = explode(">", $name_cut, 2)[0];
		$name_cut = explode("\n", $name_cut, 2)[0];
		$name_cut = preg_replace('/\s+/', '', $name_cut);
		$name_cut = strtolower(str_replace('/', '', $name_cut));
		return $name_cut;
	}

	// Set skip if elements are blocked from minification
	function setSkip($name, $type) {
		foreach($this->elements['skip'] as $element) {
			if ($element === $name && $this->skip === 0) {
				$this->skipName = $name;
			}
		}
		if (in_array($name, $this->elements['skip'])) {
			if ($type === 'open') {
				$this->skip++;
			}
			if ($type === 'close') {
				$this->skip--;
			}
		}
	}

	// Minify all, even spaces between elements
	function minifyHard($element) {
		$element = preg_replace('/\s+/', ' ', $element);
		$element = trim($element);
		return trim($element);
	}

	// Strip but keep one space
	function minifyKeepSpaces($element) {
		return preg_replace('/\s+/', ' ', $element);
	}
}
