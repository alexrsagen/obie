<?php namespace Obie\Security\U2f;

class ClientData {
	function __construct(
		public string $typ,
		public string $challenge,
		public string $origin,
	) {}
}