<?php namespace ZeroX\Security;
use ZeroX\Util;
use ZeroX\Encoding\Cbor;
use ZeroX\Validation\WebauthnClientDataValidator;
use ZeroX\Validation\WebauthnAttestationObjectValidator;
if (!defined('IN_ZEROX')) {
	return;
}

class Webauthn {
	// https://www.w3.org/TR/webauthn/#enumdef-attestationconveyancepreference
	const AT_NONE = 'none';
	const AT_INDIRECT = 'indirect';
	const AT_DIRECT = 'direct';
	const AT_ENTERPRISE = 'enterprise';

	// https://www.w3.org/TR/webauthn/#enumdef-userverificationrequirement
	const UV_REQUIRED = 'required';
	const UV_PREFERRED = 'preferred';
	const UV_DISCOURAGED = 'discouraged';

	// https://w3c.github.io/webauthn/#sctn-authenticator-data
	const FLAG_UP   = 0b00000001; // User Presence (UP) result
	const FLAG_RFU1 = 0b00000010;
	const FLAG_UV   = 0b00000100; // User Verified (UV) result
	const FLAG_RFU2 = 0b00001000;
	const FLAG_RFU3 = 0b00010000;
	const FLAG_RFU4 = 0b00100000;
	const FLAG_RFU5 = 0b01000000;
	const FLAG_ED   = 0b10000000; // Extension data included (ED)

	/**
	 * Creates a WebAuthn JS API Array<webauthn.PublicKeyCredentialDescriptor>
	 *
	 * @link https://w3c.github.io/webauthn/#dictdef-publickeycredentialdescriptor
	 * @param array $raw_key_handles - Array containing raw key handle strings
	 * @param array $transports - This OPTIONAL member contains a hint as to how the client might communicate with the managing authenticator of the public key credential the caller is referring to.
	 */
	public static function buildAllowCredentials(array $raw_key_handles, array $transports = null): array {
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
	 * @param string $challenge - Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified. See https://w3c.github.io/webauthn/#sctn-cryptographic-challenges
	 * @param array $allow_credentials - Use getWebAuthnJSAllowCredentials to generate from raw key handles
	 * @param int $timeout - Optional client sign timeout in milliseconds
	 * @param string $rp_id - Optional relying party identifier string
	 * @param string $user_verification - Optional, see https://w3c.github.io/webauthn/#enumdef-userverificationrequirement
	 * @param array $extensions - Optional, see https://w3c.github.io/webauthn/#dictdef-authenticationextensionsclientinputs
	 */
	public static function buildPublicKeyCredentialRequestOptions(string $challenge = null, array $allow_credentials = null, int $timeout = null, string $rp_id = null, string $user_verification = self::UV_PREFERRED, array $extensions = null): array {
		if ($challenge === null) $challenge = random_bytes(32);
		$res = ['challenge' => array_values(unpack('C*', $challenge))];
		if ($timeout !== null) $res['timeout'] = $timeout;
		if ($rp_id !== null) $res['rpId'] = $rp_id;
		if ($allow_credentials !== null) $res['allowCredentials'] = $allow_credentials;
		if ($user_verification !== self::UV_PREFERRED) $res['userVerification'] = $user_verification;
		if ($extensions !== null) $res['extensions'] = $extensions;
		return $res;
	}

	/**
	 * Creates a WebAuthn JS API webauthn.PublicKeyCredentialCreationOptions
	 *
	 * @link https://w3c.github.io/webauthn/#dictdef-publickeycredentialcreationoptions
	 * @param array $rp - Relying party, see https://w3c.github.io/webauthn/#dictdef-publickeycredentialrpentity
	 * @param array $user - User, see https://w3c.github.io/webauthn/#dictdef-publickeycredentialuserentity
	 * @param string $challenge - Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified. See https://w3c.github.io/webauthn/#sctn-cryptographic-challenges
	 * @param array $cred_params - Credential parameters (type and algorithm), see https://w3c.github.io/webauthn/#dictdef-publickeycredentialparameters
	 * @param int $timeout - Optional client register timeout in milliseconds
	 * @param array $exclude_creds - Optional, see https://w3c.github.io/webauthn/#dictdef-publickeycredentialdescriptor
	 * @param array $authenticator_selection - Optional, see https://w3c.github.io/webauthn/#dictdef-authenticatorselectioncriteria
	 * @param string $attestation - Optional, should be one of https://w3c.github.io/webauthn/#enumdef-attestationconveyancepreference
	 * @param array $extensions - Optional, see https://w3c.github.io/webauthn/#dictdef-authenticationextensionsclientinputs
	 */
	public static function buildPublicKeyCredentialCreationOptions(array $rp, array $user, string $challenge = null, array $cred_params = [['type' => 'public-key', 'alg' => Cose::ALG_ES256]], int $timeout = null, array $exclude_creds = null, array $authenticator_selection = null, string $attestation = self::AT_NONE, array $extensions = null): array {
		if ($challenge === null) $challenge = random_bytes(32);
		$res = ['challenge' => array_values(unpack('C*', $challenge)), 'pubKeyCredParams' => $cred_params];
		if ($rp !== null) $res['rp'] = $rp;
		if ($user !== null) $res['user'] = $user;
		if ($timeout !== null) $res['timeout'] = $timeout;
		if ($exclude_creds !== null) $res['excludeCredentials'] = $exclude_creds;
		if ($authenticator_selection !== null) $res['authenticatorSelection'] = $authenticator_selection;
		if ($attestation !== self::AT_NONE) $res['attestation'] = $attestation;
		if ($extensions !== null) $res['extensions'] = $extensions;
		return $res;
	}

	/**
	 * Decode WebAuthn attestation object
	 *
	 * @link https://www.w3.org/TR/webauthn/#dom-authenticatorattestationresponse-attestationobject
	 * @param string $input - CBOR-encoded attestation object string
	 * @return array|null
	 */
	public static function decodeAttestationObject(string $input) {
		$obj = Cbor::decode($input);
		if (!is_array($obj)) return null;
		if (array_key_exists('authData', $obj)) {
			$obj['authDataRaw'] = $obj['authData'];
			$obj['authData'] = static::decodeAuthData($obj['authDataRaw']);
		}
		return $obj;
	}

	/**
	 * Decode WebAuthn authenticator data
	 *
	 * @link https://www.w3.org/TR/webauthn/#sec-authenticator-data
	 * @param string $input - Raw authenticator data string
	 * @return array
	 */
	public static function decodeAuthData(string $input): array {
		$credential_id_len = (int)(unpack('n', substr($input, 53, 2))[1]);
		$rest_cbor = null;
		$pubkey_cose = Cbor::decode(substr($input, 55 + $credential_id_len), $rest_cbor);
		$pubkey_raw = null;
		if (is_array($pubkey_cose)) $pubkey_raw = Cose::ecPubKeyToDER($pubkey_cose, false);
		$extensions = Cbor::decode($rest_cbor);
		return [
			'rpIdHash' => substr($input, 0, 32),
			'flags' => ord(substr($input, 32, 1)),
			'signCount' => (int)(unpack('N', substr($input, 33, 4))[1]),
			'attestedCredentialData' => [
				'aaguid' => substr($input, 37, 16),
				'credentialIdLength' => $credential_id_len,
				'credentialId' => substr($input, 55, $credential_id_len),
				'credentialPublicKey' => $pubkey_raw
			],
			'extensions' => $extensions
		];
	}

	/**
	 * Implement Webauthn verification procedure, as per the W3C specification
	 *
	 * This method may be used as a generic reference, as some verification
	 * steps are unique to each relying party.
	 *
	 * A known authenticator public key MUST be provided if not registering.
	 *
	 * @link https://w3c.github.io/webauthn/#sctn-registering-a-new-credential
	 * @link https://w3c.github.io/webauthn/#sctn-verifying-assertion
	 * @param string $client_data_json - clientDataJSON string
	 * @param array $att_obj - attestationObject decoded with decodeAttestationObject
	 * @param string $known_challenge - Known raw challenge string (not encoded)
	 * @param string $known_origin - Known relying party origin
	 * @param string $known_rp_id - Known relying party ID
	 * @param string|null $public_key - Known authenticator public key, if not registering
	 * @param int $last_sign_count - Last known authenticator signCount, if not registering
	 * @param bool $uv_required - Whether UV (User Verification) flag is required
	 * @param array $cred_params - Credential parameters (type and algorithm), see https://w3c.github.io/webauthn/#dictdef-publickeycredentialparameters
	 */
	public static function verify(string $client_data_json, array $att_obj, string $known_challenge, string $known_origin, string $known_rp_id, string $public_key = null, int $last_sign_count = 0, bool $uv_required = false, bool $attestation_required = true, array $cred_params = [['type' => 'public-key', 'alg' => Cose::ALG_ES256]]): bool {
		$client_data = json_decode($client_data_json, true); // §7.1 step 6 / §7.2 step 10

		// - validate client data and attestation object against validation models
		// to ensure specification-required values are present
		// - covers verification procedure step regarding verifying that attStmt
		// conforms to the defined syntax
		if (!(new WebauthnClientDataValidator())->validate($client_data)) {
			return false;
		}
		if (!(new WebauthnAttestationObjectValidator())->validate($att_obj)) {
			return false;
		}

		// §7.1 step 7-9 / §7.2 step 11-13
		// - check clientData.type
		// - check clientData.challenge against known challenge
		// - check clientData.origin against known origin
		if (
			(
				$public_key === null && $client_data['type'] !== 'webauthn.create' || // §7.1 step 7
				$public_key !== null && $client_data['type'] !== 'webauthn.get' // §7.2 step 11
			) ||
			Base64Url::decode($client_data['challenge']) !== $known_challenge || // §7.1 step 8 / §7.2 step 12
			$client_data['origin'] !== $known_origin // §7.1 step 9 / §7.2 step 13
		) return false;

		// §7.1 step 11 / §7.2 step 19
		// - hash client data
		$client_data_hash = hash('sha256', $cred['response']['clientDataJSON'], true);

		// §7.1 step 13-15 / §7.2 step 15-17
		// - check rpIdHash against known RP ID
		// - check UP flag
		// - check UV flag
		if (
			$att_obj['authData']['rpIdHash'] !== hash('sha256', $known_rp_id, true) || // §7.1 step 13 / §7.2 step 15
			$att_obj['authData']['flags'] & Webauthn::FLAG_UP !== Webauthn::FLAG_UP || // §7.1 step 14 / §7.2 step 16
			$uv_required && $att_obj['authData']['flags'] & Webauthn::FLAG_UV !== Webauthn::FLAG_UV // §7.1 step 15 / §7.2 step 17
		) return false;

		if ($public_key === null) {
			if (array_key_exists('attStmt', $att_obj)) {
				// §7.1 step 16
				// - ensure authenticator algorithm is allowed
				$alg_found = false;
				if (array_key_exists('alg', $att_obj['attStmt'])) {
					foreach ($cred_params as $cred_param) {
						if (array_key_exists('alg', $cred_param) && $cred_param['alg'] === $att_obj['attStmt']['alg']) {
							$alg_found = true;
							break;
						}
					}
				}
				if (!$alg_found) return false;

				// §7.1 step 18-19
				// - check that attestation statement format is supported
				// - execute verification procedure
				switch (array_key_exists('fmt', $att_obj) ? $att_obj['fmt'] : '') {
					case 'packed':
						if (array_key_exists('x5c', $att_obj['attStmt']) && count($att_obj['x5c']) > 0) {
							// TODO: 2. attestation type not ECDAA
							$signature_base  = $att_obj['authDataRaw'];
							$signature_base .= $client_data_hash;
							$cert = Pem::encode($att_obj['attStmt']['x5c'][0], Pem::LABEL_CERTIFICATE);
							$cert_info = openssl_pkey_get_details(openssl_pkey_get_public($cert));
							var_dump($cert_info); exit;
							$signature_valid = Cose::verify($signature_base, $att_obj['attStmt']['sig'], $cert, $att_obj['attStmt']['alg']);
						} elseif (array_key_exists('ecdaaKeyId', $att_obj['attStmt'])) {
							// 3. attestation type ECDAA
							// not implemented due to security concerns
							return false;
						} else {
							// TODO: 4. self attestation
						}
						break;
					case 'android-key':
						break;
					case 'fido-u2f':
						break;
					default:
						return false;
				}

				// TODO: §7.1 step 20-21
			} elseif ($attestation_required) {
				return false;
			}
		} else {
			// TODO: §7.2 step 20
			// - verify signature over authData

			// §7.2 step 21
			// - if either authenticator sign count or known sign count are
			// non-zero, ensure that authenticator sign count is greater than
			// known sign count
			if ($att_obj['authData']['signCount'] !== 0 || $last_sign_count !== 0) {
				if ($att_obj['authData']['signCount'] <= $last_sign_count) {
					return false;
				}
			}
		}
	}
}