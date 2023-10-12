<?php namespace Obie\Security\U2f;

class SignResponse {
	function __construct(
		public string $keyHandle,
		public int $counter,
		public int $flags,

		public ClientData $clientData,
		public string $clientDataHash,

		public string $signatureData,
		public string $signatureBase,
		public string $signature,
	) {}
}