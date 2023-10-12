<?php namespace Obie\Security\Webauthn;

class AuthData {
	function __construct(
		public string $rpIdHash,
		public int $flags,
		public int $signCount,
		public ?string $aaguid = null,
		public ?string $credentialId = null,
		public ?array $credentialPublicKey = null,
		public mixed $extensions = null,
	) {}
}