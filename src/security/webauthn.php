<?php namespace Obie\Security;
use Obie\Log;
use Obie\App;
use Obie\Encoding\Pem;
use Obie\Encoding\Cbor;
use Obie\Encoding\Json;
use Obie\Encoding\Base64Url;
use Obie\Validation\WebauthnAuthDataValidator;
use Obie\Validation\WebauthnClientDataValidator;
use Obie\Validation\WebauthnAttestationObjectValidator;

class Webauthn {
	// https://www.w3.org/TR/webauthn-2/#enumdef-attestationconveyancepreference
	const AT_NONE = 'none';
	const AT_INDIRECT = 'indirect';
	const AT_DIRECT = 'direct';
	const AT_ENTERPRISE = 'enterprise';

	// https://www.w3.org/TR/webauthn-2/#enumdef-userverificationrequirement
	const UV_REQUIRED = 'required';
	const UV_PREFERRED = 'preferred';
	const UV_DISCOURAGED = 'discouraged';

	// https://www.w3.org/TR/webauthn-2/#authenticator-data
	const FLAG_UP   = 0b00000001; // User Presence (UP) result
	const FLAG_RFU1 = 0b00000010;
	const FLAG_UV   = 0b00000100; // User Verified (UV) result
	const FLAG_RFU2 = 0b00001000;
	const FLAG_RFU3 = 0b00010000;
	const FLAG_RFU4 = 0b00100000;
	const FLAG_RFU5 = 0b01000000;
	const FLAG_ED   = 0b10000000; // Extension data included (ED)

	// https://fidoalliance.org/specs/fido-v2.0-rd-20180702/fido-registry-v2.0-rd-20180702.html#authenticator-attestation-types
	const ATTESTATION_BASIC_FULL = 0x3E07;
	const ATTESTATION_BASIC_SURROGATE = 0x3E08;
	const ATTESTATION_ECDAA = 0x3E09;
	const ATTESTATION_ATTCA = 0x3E0A;

	/**
	 * Creates a WebAuthn JS API Array<webauthn.PublicKeyCredentialDescriptor>
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialdescriptor
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
	 * @link https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialrequestoptions
	 * @param string $challenge - Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified. See https://www.w3.org/TR/webauthn-2/#sctn-cryptographic-challenges
	 * @param array $allow_credentials - Use getWebAuthnJSAllowCredentials to generate from raw key handles
	 * @param int $timeout - Optional client sign timeout in milliseconds
	 * @param string $rp_id - Optional relying party identifier string
	 * @param string $user_verification - Optional, see https://www.w3.org/TR/webauthn-2/#enumdef-userverificationrequirement
	 * @param array $extensions - Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-authenticationextensionsclientinputs
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
	 * @link https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialcreationoptions
	 * @param array $rp - Relying party, see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialrpentity
	 * @param array $user - User, see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialuserentity
	 * @param string $challenge - Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified. See https://www.w3.org/TR/webauthn-2/#sctn-cryptographic-challenges
	 * @param array $cred_params - Credential parameters (type and algorithm), see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialparameters
	 * @param int $timeout - Optional client register timeout in milliseconds
	 * @param array $exclude_creds - Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialdescriptor
	 * @param array $authenticator_selection - Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-authenticatorselectioncriteria
	 * @param string $attestation - Optional, should be one of https://www.w3.org/TR/webauthn-2/#enumdef-attestationconveyancepreference
	 * @param array $extensions - Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-authenticationextensionsclientinputs
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
	 * @link https://www.w3.org/TR/webauthn-2/#dom-authenticatorattestationresponse-attestationobject
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
	 * @link https://www.w3.org/TR/webauthn-2/#sec-authenticator-data
	 * @param string $input - Raw authenticator data string
	 * @return array
	 */
	public static function decodeAuthData(string $input): array {
		$cred_id = null;
		$rest_cbor = null;
		$pubkey_raw = null;
		$pubkey_cose = null;
		$credential_id_len = 0;
		$credential_id = null;
		$aaguid = null;
		if (strlen($input) > 37) {
			$credential_id_len = (int)(unpack('n', substr($input, 53, 2))[1]);
			$credential_id = substr($input, 55, $credential_id_len);
			$aaguid = substr($input, 37, 16);
			$pubkey_cose = Cbor::decode(substr($input, 55 + $credential_id_len), $rest_cbor);
		}
		if (is_array($pubkey_cose)) $pubkey_raw = Cose::ecPubKeyToDER($pubkey_cose, false);
		$extensions = Cbor::decode($rest_cbor);
		return [
			'rpIdHash' => substr($input, 0, 32),
			'flags' => ord(substr($input, 32, 1)),
			'signCount' => (int)(unpack('N', substr($input, 33, 4))[1]),
			'aaguid' => $aaguid,
			'credentialIdLength' => $credential_id_len,
			'credentialId' => $credential_id,
			'credentialPublicKey' => $pubkey_raw,
			'credentialPublicKeyCOSE' => $pubkey_cose,
			'extensions' => $extensions
		];
	}

	public static function getAttestationMetadata(string $aaguid, ?string $fido_mds2_token = null, bool $debug = false): array {
		$mds = App::getFidoMds();
		$debug_old = $mds->getDebug();
		$mds->setDebug($debug);
		$metadata = $mds->getMetadata($aaguid) ?? [];
		$mds->setDebug($debug_old);
		return $metadata;
	}

	/**
	 * Implement Webauthn verification procedure, as per the W3C specification
	 *
	 * This method may be used as a generic reference, as some verification
	 * steps are unique to each relying party.
	 *
	 * A known authenticator public key MUST be provided.
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#sctn-registering-a-new-credential
	 * @link https://www.w3.org/TR/webauthn-2/#sctn-verifying-assertion
	 * @param string $client_data_json - clientDataJSON string
	 * @param string $auth_data_raw - authenticatorData raw bytes
	 * @param array $auth_data - authenticatorData decoded with decodeAuthData
	 * @param string $known_challenge - Known raw challenge string (not encoded)
	 * @param string $known_origin - Known relying party origin
	 * @param string $known_rp_id - Known relying party ID
	 * @param string $public_key - Known authenticator public key
	 * @param string $signature - Authenticator signature
	 * @param int $last_sign_count - Last known authenticator signCount
	 * @param bool $uv_required - Whether UV (User Verification) flag is required
	 * @param bool $attestation_required - Whether attestation is required
	 * @param array $cred_params - Credential parameters (type and algorithm), see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialparameters
	 */
	public static function verifyAssertion(string $client_data_json, string $auth_data_raw, array $auth_data, string $known_challenge, string $known_origin, string $known_rp_id, string $public_key, string $signature, int $last_sign_count, int $alg = Cose::ALG_ES256, bool $uv_required = false, bool $attestation_required = true): bool {
		$client_data = Json::decode($client_data_json); // §7.2 step 10

		// - validate client data and attestation object against validation models
		// to ensure specification-required values are present
		// - covers verification procedure step regarding verifying that attStmt
		// conforms to the defined syntax
		if (!(new WebauthnClientDataValidator())->validate($client_data)) {
			Log::info('Webauthn/verify: Client data validation failed');
			return false;
		}
		if (!(new WebauthnAuthDataValidator())->validate($auth_data)) {
			Log::info('Webauthn/verify: Authenticator data validation failed');
			return false;
		}

		// check clientData.type - §7.2 step 11
		$client_data_expected_type = 'webauthn.get';
		if ($client_data['type'] !== $client_data_expected_type) {
			Log::info(sprintf('Webauthn/verify: clientData.type "%s" did not match expected value "%s"', $client_data['type'], $client_data_expected_type));
			return false;
		}
		// check clientData.challenge against known challenge - §7.2 step 12
		$client_data_challenge = Base64Url::decode($client_data['challenge']);
		if ($client_data_challenge !== $known_challenge) {
			Log::info(sprintf('Webauthn/verify: clientData.challenge %s did not match expected value %s', bin2hex($client_data_challenge), bin2hex($known_challenge)));
			return false;
		}
		// check clientData.origin against known origin - §7.2 step 13
		if ($client_data['origin'] !== $known_origin) {
			Log::info(sprintf('Webauthn/verify: clientData.origin "%s" did not match expected value "%s"', $client_data['origin'], $known_origin));
			return false;
		}

		// hash client data - §7.2 step 19
		$client_data_hash = hash('sha256', $client_data_json, true);

		// check rpIdHash against known RP ID - §7.2 step 15
		$auth_data_expected_rp_id_hash = hash('sha256', $known_rp_id, true);
		if ($auth_data['rpIdHash'] !== $auth_data_expected_rp_id_hash) {
			Log::info(sprintf('Webauthn/verify: authData.rpIdHash %s did not match expected value %s', bin2hex($auth_data['rpIdHash']), bin2hex($auth_data_expected_rp_id_hash)));
			return false;
		}
		// check UP flag - §7.2 step 16
		if ($auth_data['flags'] & Webauthn::FLAG_UP !== Webauthn::FLAG_UP) {
			Log::info('Webauthn/verify: authData.flags did not contain user presence flag');
			return false;
		}
		// check UV flag - §7.2 step 17
		if ($uv_required && $auth_data['flags'] & Webauthn::FLAG_UV !== Webauthn::FLAG_UV) {
			Log::info('Webauthn/verify: authData.flags did not contain user verification flag');
			return false;
		}

		// verify signature over authData - §7.2 step 20

		// get signature base
		$signature_base  = $auth_data_raw;    // authData
		$signature_base .= $client_data_hash; // clientDataHash

		// verify signature
		if ($signature === null || $public_key === null) {
			return false;
		}
		$signature_valid = Cose::verify($signature_base, $signature, $public_key, $alg);
		if (!$signature_valid) {
			Log::info(sprintf('Webauthn/verify: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($signature), bin2hex($signature_base), bin2hex($public_key), $alg));
			return false;
		}

		// §7.2 step 21
		// - if either authenticator sign count or known sign count are
		// non-zero, ensure that authenticator sign count is greater than
		// known sign count
		if ($auth_data['signCount'] !== 0 || $last_sign_count !== 0) {
			if ($auth_data['signCount'] <= $last_sign_count) {
				Log::info('Webauthn/verify: Authenticator counter less than or equal to last known counter, possibly a duplicated authenticator');
				return false;
			}
		}

		return true;
	}

	/**
	 * Implement Webauthn verification procedure, as per the W3C specification
	 *
	 * This method may be used as a generic reference, as some verification
	 * steps are unique to each relying party.
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#sctn-registering-a-new-credential
	 * @link https://www.w3.org/TR/webauthn-2/#sctn-verifying-assertion
	 * @param string $client_data_json - clientDataJSON string
	 * @param array $att_obj - attestationObject decoded with decodeAttestationObject
	 * @param string $known_challenge - Known raw challenge string (not encoded)
	 * @param string $known_origin - Known relying party origin
	 * @param string $known_rp_id - Known relying party ID
	 * @param bool $uv_required - Whether UV (User Verification) flag is required
	 * @param bool $attestation_required - Whether attestation is required
	 * @param array $cred_params - Credential parameters (type and algorithm), see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialparameters
	 */
	public static function verifyRegistration(string $client_data_json, array $att_obj, string $known_challenge, string $known_origin, string $known_rp_id, string $fido_mds2_token = null, bool $uv_required = false, bool $attestation_required = true, array $cred_params = [['type' => 'public-key', 'alg' => Cose::ALG_ES256]]): bool {
		$client_data = Json::decode($client_data_json); // §7.1 step 6

		// - validate client data and attestation object against validation models
		// to ensure specification-required values are present
		// - covers verification procedure step regarding verifying that attStmt
		// conforms to the defined syntax
		if (!(new WebauthnClientDataValidator())->validate($client_data)) {
			Log::info('Webauthn/verify: Client data validation failed');
			return false;
		}
		if (!(new WebauthnAttestationObjectValidator())->validate($att_obj)) {
			Log::info('Webauthn/verify: Attestation object validation failed');
			return false;
		}

		// check clientData.type - §7.1 step 7
		$client_data_expected_type = 'webauthn.create';
		if ($client_data['type'] !== $client_data_expected_type) {
			Log::info(sprintf('Webauthn/verify: clientData.type "%s" did not match expected value "%s"', $client_data['type'], $client_data_expected_type));
			return false;
		}
		// check clientData.challenge against known challenge - §7.1 step 8
		$client_data_challenge = Base64Url::decode($client_data['challenge']);
		if ($client_data_challenge !== $known_challenge) {
			Log::info(sprintf('Webauthn/verify: clientData.challenge %s did not match expected value %s', bin2hex($client_data_challenge), bin2hex($known_challenge)));
			return false;
		}
		// check clientData.origin against known origin - §7.1 step 9
		if ($client_data['origin'] !== $known_origin) {
			Log::info(sprintf('Webauthn/verify: clientData.origin "%s" did not match expected value "%s"', $client_data['origin'], $known_origin));
			return false;
		}

		// hash client data - §7.1 step 11
		$client_data_hash = hash('sha256', $client_data_json, true);

		// check rpIdHash against known RP ID - §7.1 step 13
		$auth_data_expected_rp_id_hash = hash('sha256', $known_rp_id, true);
		if ($att_obj['authData']['rpIdHash'] !== $auth_data_expected_rp_id_hash) {
			Log::info(sprintf('Webauthn/verify: authData.rpIdHash %s did not match expected value %s', bin2hex($att_obj['authData']['rpIdHash']), bin2hex($auth_data_expected_rp_id_hash)));
			return false;
		}
		// check UP flag - §7.1 step 14
		if ($att_obj['authData']['flags'] & Webauthn::FLAG_UP !== Webauthn::FLAG_UP) {
			Log::info('Webauthn/verify: authData.flags did not contain user presence flag');
			return false;
		}
		// check UV flag - §7.1 step 15
		if ($uv_required && $att_obj['authData']['flags'] & Webauthn::FLAG_UV !== Webauthn::FLAG_UV) {
			Log::info('Webauthn/verify: authData.flags did not contain user verification flag');
			return false;
		}

		if (array_key_exists('attStmt', $att_obj)) {
			// ensure authenticator algorithm is allowed - §7.1 step 16
			$alg_found = false;
			if (array_key_exists('alg', $att_obj['attStmt'])) {
				foreach ($cred_params as $cred_param) {
					if (array_key_exists('alg', $cred_param) && $cred_param['alg'] === $att_obj['attStmt']['alg']) {
						$alg_found = true;
						break;
					}
				}
			}

			// §7.1 step 18-19
			// - check that attestation statement format is supported
			// - get attestation metadata
			// - execute verification procedure
			switch (array_key_exists('fmt', $att_obj) ? $att_obj['fmt'] : '') {
				case 'packed':
					// §7.1 step 16
					if (!$alg_found) {
						Log::info(sprintf('Webauthn/verify: attStmt.alg did not match expected value %d', array_key_exists('alg', $att_obj['attStmt']) ? (int)$att_obj['attStmt']['alg'] : 0));
						return false;
					}

					// §8.2 verification procedure
					if (array_key_exists('x5c', $att_obj['attStmt']) && count($att_obj['attStmt']['x5c']) > 0) {
						// 2. attestation type not ECDAA
						// get certificate
						$cert = Pem::encode($att_obj['attStmt']['x5c'][0], Pem::LABEL_CERTIFICATE);

						// get signature base
						$signature_base  = $att_obj['authDataRaw'];
						$signature_base .= $client_data_hash;

						// verify signature
						$signature_valid = Cose::verify($signature_base, $att_obj['attStmt']['sig'], $cert, $att_obj['attStmt']['alg']);
						if (!$signature_valid) {
							Log::info(sprintf('Webauthn/verify: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($att_obj['attStmt']['sig']), bin2hex($signature_base), bin2hex($att_obj['attStmt']['x5c'][0]), $att_obj['attStmt']['alg']));
							return false;
						}

						// verify cert requirements
						$cert_info = openssl_x509_parse($cert);
						if (
							!array_key_exists('version', $cert_info) ||
							!array_key_exists('extensions', $cert_info) ||
							!array_key_exists('basicConstraints', $cert_info['extensions'])
						) {
							Log::info('Webauthn/verify: attestnCert info missing version, extensions or basicConstraints');
							return false;
						}
						if ($cert_info['version'] !== 2) {
							Log::info('Webauthn/verify: attestnCert version is not 3 (ASN.1 INTEGER 2)');
							return false;
						}
						if (strtoupper(trim(explode(',', $cert_info['extensions']['basicConstraints'], 2)[0])) !== 'CA:FALSE') {
							Log::info('Webauthn/verify: attestnCert basicConstraints CA flag is not false');
							return false;
						}
						if (array_key_exists('1.3.6.1.4.1.45724.1.1.4', $cert_info['extensions']) && substr($cert_info['extensions']['1.3.6.1.4.1.45724.1.1.4'], -16) !== $att_obj['authData']['aaguid']) {
							Log::info(sprintf('Webauthn/verify: attestnCert aaguid %s does not match authData aaguid %s', bin2hex(substr($cert_info['extensions']['1.3.6.1.4.1.45724.1.1.4'], -16)), bin2hex($att_obj['authData']['aaguid'])));
							return false;
						}

						// get attestation metadata
						// if ($fido_mds2_token !== null) $att_metadata = static::getAttestationMetadata($att_obj['authData']['aaguid'], $fido_mds2_token);
					} elseif (array_key_exists('ecdaaKeyId', $att_obj['attStmt'])) {
						// 3. attestation type ECDAA
						// not implemented due to security concerns
						Log::info('Webauthn/verify: Attestation type is ECDAA, which is not allowed due to security concerns');
						return false;
					} else {
						// 4. self attestation
						// verify authData alg matches cert alg
						if (
							!array_key_exists('alg', $att_obj['attStmt']) ||
							!array_key_exists(3, $att_obj['authData']['credentialPublicKeyCOSE']) ||
							$att_obj['attStmt']['alg'] !== $att_obj['authData']['credentialPublicKeyCOSE'][3]
						) {
							Log::info('Webauthn/verify: attestnCert algorithm does not match authData algorithm');
							return false;
						}

						// verify signature
						$signature_base  = $att_obj['authDataRaw'];
						$signature_base .= $client_data_hash;
						$signature_valid = Cose::verify($signature_base, $att_obj['attStmt']['sig'], $att_obj['authData']['credentialPublicKey'], $att_obj['attStmt']['alg']);
						if (!$signature_valid) {
							Log::info(sprintf('Webauthn/verify: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($att_obj['attStmt']['sig']), bin2hex($signature_base), bin2hex($att_obj['authData']['credentialPublicKey']), $att_obj['attStmt']['alg']));
							return false;
						}

						// $att_metadata = ['attestationTypes' => [self::ATTESTATION_BASIC_SURROGATE], 'attestationRootCertificates' => []];
					}
					break;
				case 'android-key':
					break;
				case 'fido-u2f':
					// §8.6 verificaation procedure
					// 2. get certificate
					if (!array_key_exists('x5c', $att_obj['attStmt']) || count($att_obj['attStmt']['x5c']) !== 1) {
						Log::info('Webauthn/verify: attCert missing from attStmt');
						return false;
					}
					$cert = Pem::encode($att_obj['attStmt']['x5c'][0], Pem::LABEL_CERTIFICATE);
					$key_res = openssl_pkey_get_public($cert);
					$key_details = openssl_pkey_get_details($key_res);
					openssl_free_key($key_res);
					if (
						!is_array($key_details) ||
						!array_key_exists('key', $key_details) ||
						!array_key_exists('ec', $key_details) ||
						!array_key_exists('curve_oid', $key_details['ec']) ||
						$key_details['ec']['curve_oid'] !== '1.2.840.10045.3.1.7'
					) {
						Log::info('Webauthn/verify: attCert public key is not an EC public key over the P-256 curve');
						return false;
					}
					$cert_pubkey = $key_details['key'];

					// 4. get public key
					$public_key_raw = Cose::ecPubkeyToRaw($att_obj['authData']['credentialPublicKeyCOSE'], false);
					if ($public_key_raw === '') {
						Log::info('Webauthn/verify: credentialPublicKey in authData is invalid');
						return false;
					}

					// 5. get signature base
					$signature_base  = "\0";                                 // reserved
					$signature_base .= $att_obj['authData']['rpIdHash'];     // rpIdHash
					$signature_base .= $client_data_hash;                    // clientDataHash
					$signature_base .= $att_obj['authData']['credentialId']; // credentialId
					$signature_base .= $public_key_raw;                      // credentialPublicKey

					// 6. verify signature
					$signature_valid = Ecdsa::verify($signature_base, $att_obj['attStmt']['sig'], $cert_pubkey, OPENSSL_ALGO_SHA256);
					if (!$signature_valid) {
						Log::info(sprintf('Webauthn/verify: Signature %s over data %s could not be verified with public key %s using ECDSA-SHA256', bin2hex($att_obj['attStmt']['sig']), bin2hex($signature_base), bin2hex(Pem::decode($cert_pubkey))));
						return false;
					}

					// 7. get attestation metadata
					// if ($fido_mds2_token !== null) $att_metadata = static::getAttestationMetadata($att_obj['authData']['aaguid'], $fido_mds2_token);
					break;
				default:
					Log::info(sprintf('Webauthn/verify: Attestation format "%s" not supported', array_key_exists('fmt', $att_obj) ? $att_obj['fmt'] : ''));
					return false;
			}

			// TODO: §7.1 step 20-21
		} elseif ($attestation_required) {
			Log::info('Webauthn/verify: Attestation required but not provided');
			return false;
		}

		return true;
	}
}