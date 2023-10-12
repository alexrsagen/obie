<?php namespace Obie\Security\Webauthn;

class AttestationObject {
	function __construct(
		public string $fmt,
		public string $authDataRaw,
		public AuthData $authData,
		public ?AttestationStatement $attStmt = null,
	) {}
}