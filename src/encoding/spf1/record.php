<?php namespace Obie\Encoding\Spf1;

/**
 * @property string[] $modifiers
 * @property Directive[] $directives
 */
class Record {
	function __construct(
		public array $modifiers = [],
		public array $directives = [],
	) {}
}