<?php namespace Obie\Encoding\Spf1;
use Obie\Encoding\Spf1;

class Directive {
	function __construct(
		public string $qualifier = Spf1::QUALIFIER_PASS,
		public string $mechanism = '',
		public string $value = '',
	) {}
}