<?php namespace Obie\Encoding\Spf1;

/**
 * @property string[] $modifiers
 * @property Directive[] $directives
 *
 * @package Obie\Encoding\Spf1
 */
class Record {
	function __construct(
		public array $modifiers = [],
		public array $directives = [],
	) {}
}