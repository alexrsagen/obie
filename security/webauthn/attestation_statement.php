<?php namespace Obie\Security\Webauthn;

class AttestationStatement {
	function __construct(
		public ?int $alg = null,
		public ?string $sig = null,
		public ?array $x5c = null,
		public ?string $ver = null,
		public ?string $pubArea = null,
		public ?string $response = null,
		public ?string $certInfo = null,
		public ?string $receipt = null,
	) {}
}