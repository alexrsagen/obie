<?php namespace Obie\Security\U2f;

class RegisterResponse {
	function __construct(
		public string $appId,
		public string $version,
		public string $publicKey,
		public string $keyHandle,
		public string $attCert,

		public ClientData $clientData,
		public string $clientDataHash,

		public string $signatureBase,
		public string $signature,
	) {}
}