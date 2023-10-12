<?php namespace Obie\Security\Webauthn;

class ClientData {
	const TYPE_WEBAUTHN_GET = 'webauthn.get';
	const TYPE_WEBAUTHN_CREATE = 'webauthn.create';

	function __construct(
		public string $type,
		public string $challenge,
		public string $origin,
	) {}
}