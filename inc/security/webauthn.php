<?php namespace ZeroX\Security;
use ZeroX\Util;
if (!defined('IN_ZEROX')) {
	return;
}

class Webauthn {
	/**
	 * Creates a WebAuthn JS API Array<webauthn.PublicKeyCredentialDescriptor>
	 *
	 * @link https://w3c.github.io/webauthn/#dictdef-publickeycredentialdescriptor
	 * @param array $raw_key_handles - Array containing raw key handle strings
	 * @param array $transports - This OPTIONAL member contains a hint as to how the client might communicate with the managing authenticator of the public key credential the caller is referring to.
	 */
	public static function getJSAllowCredentials(array $raw_key_handles, array $transports = null): array {
		$keys = [];
		foreach ($raw_key_handles as $key_handle) {
			$key = [
				'type' => 'public-key',
				'id'   => array_values(unpack('C*', $key_handle))
			];
			if ($transports !== null) {
				$key['transports'] = $transports;
			}
			$keys[] = $key;
		}
		return $keys;
	}

	/**
	 * Creates a WebAuthn JS API webauthn.PublicKeyCredentialRequestOptions
	 *
	 * @link https://w3c.github.io/webauthn/#dictdef-publickeycredentialrequestoptions
	 * @param string $challenge - Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified
	 * @param array $allow_credentials - Use getWebAuthnJSAllowCredentials to generate from raw key handles
	 * @param int $timeout - Optional client sign timeout in milliseconds
	 * @param string $rp_id - Optional relying party identifier string
	 * @param string $user_verification - Optional, see https://w3c.github.io/webauthn/#enumdef-userverificationrequirement
	 * @param array $extensions - Optional, see https://w3c.github.io/webauthn/#dictdef-authenticationextensionsclientinputs
	 */
	public static function getJSPublicKeyCredentialRequestOptions(string $challenge = null, array $allow_credentials = null, int $timeout = null, string $rp_id = null, string $user_verification = 'preferred', array $extensions = null): array {
		if ($challenge === null) $challenge = random_bytes(32);
		$res = ['challenge' => array_values(unpack('C*', $challenge))];
		if ($timeout !== null) $res['timeout'] = $timeout;
		if ($rp_id !== null) $res['rpId'] = $rp_id;
		if ($allow_credentials !== null) $res['allowCredentials'] = $allow_credentials;
		if ($user_verification !== 'preferred') $res['userVerification'] = $user_verification;
		if ($extensions !== null) $res['extensions'] = $extensions;
		return $res;
	}
}