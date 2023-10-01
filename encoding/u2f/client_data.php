<?php namespace Obie\Encoding\U2f;

class ClientData {
	function __construct(
		public string $typ,
		public string $challenge,
		public string $origin,
	) {}
}