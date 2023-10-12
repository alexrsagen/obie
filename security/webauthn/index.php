<?php namespace Obie\Security;
// TODO: Write tests for WebAuthn
use Obie\Log;
use Obie\App;
use Obie\Encoding\Pem;
use Obie\Encoding\Cbor;
use Obie\Encoding\Json;
use Obie\Encoding\Base64Url;
use Obie\Security\Webauthn\AuthData;
use Obie\Security\Webauthn\ClientData;
use Obie\Security\Webauthn\ClientDataValidator;
use Obie\Security\Webauthn\AttestationObject;
use Obie\Security\Webauthn\AttestationObjectValidator;
use Obie\Security\Webauthn\AttestationStatement;
use Obie\Security\Webauthn\TpmCertInfo;
use Obie\Security\Webauthn\TpmClockInfo;
use Obie\Security\Webauthn\TpmKeyParameters;
use Obie\Security\Webauthn\TpmPubArea;
use Sop\ASN1\Exception\DecodeException;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;

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

	// https://fidoalliance.org/specs/common-specs/fido-registry-v2.2-ps-20220523.html#authenticator-attestation-types
	const ATTESTATION_BASIC_FULL = 'basic_full';
	const ATTESTATION_BASIC_SURROGATE = 'basic_surrogate';
	const ATTESTATION_ECDAA = 'ecdaa';
	const ATTESTATION_ATTCA = 'attca';
	const ATTESTATION_ANONCA = 'anonca';
	const ATTESTATION_NONE = 'none';

	const ATTESTATION_LEVELS_ANY = [
		self::ATTESTATION_BASIC_FULL,
		self::ATTESTATION_BASIC_SURROGATE,
		self::ATTESTATION_ECDAA,
		self::ATTESTATION_ATTCA,
		self::ATTESTATION_ANONCA,
		self::ATTESTATION_NONE,
	];

	// https://fidoalliance.org/specs/mds/fido-metadata-statement-v3.0-ps-20210518.html#metadata-keys - attestationRootCertificates
	const OID_FIDO_GEN_CE_AAID            = '1.3.6.1.4.1.45724.1.1.1';
	const OID_FIDO_GEN_CE_AAGUID          = '1.3.6.1.4.1.45724.1.1.4';
	const OID_TCG_AT_TPM_MANUFACTURER     = '2.23.133.2.1';
	const OID_TCG_AT_TPM_MODEL            = '2.23.133.2.2';
	const OID_TCG_AT_TPM_VERSION          = '2.23.133.2.3';
	const OID_TCG_KP_AIK_CERTIFICATE      = '2.23.133.8.3';
	const OID_GOOGLE_KEY_ATTESTATION      = '1.3.6.1.4.1.11129.2.1.17';
	const OID_APPLE_ANONYMOUS_ATTESTATION = '1.2.840.113635.100.8.2';

	/**
	 * Creates a WebAuthn JS API Array<webauthn.PublicKeyCredentialDescriptor>
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialdescriptor
	 * @param array $raw_key_handles Array containing raw key handle strings
	 * @param array $transports This OPTIONAL member contains a hint as to how the client might communicate with the managing authenticator of the public key credential the caller is referring to.
	 */
	public static function buildAllowCredentials(array $raw_key_handles, ?array $transports = null): array {
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
	 * @param string $challenge Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified. See https://www.w3.org/TR/webauthn-2/#sctn-cryptographic-challenges
	 * @param array $allow_credentials Use getWebAuthnJSAllowCredentials to generate from raw key handles
	 * @param int $timeout Optional client sign timeout in milliseconds
	 * @param string $rp_id Optional relying party identifier string
	 * @param string $user_verification Optional, see https://www.w3.org/TR/webauthn-2/#enumdef-userverificationrequirement
	 * @param array $extensions Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-authenticationextensionsclientinputs
	 */
	public static function buildPublicKeyCredentialRequestOptions(?string $challenge = null, array $allow_credentials = null, ?int $timeout = null, string $rp_id = null, string $user_verification = self::UV_PREFERRED, ?array $extensions = null): array {
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
	 * @param array $rp Relying party, see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialrpentity
	 * @param array $user User, see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialuserentity
	 * @param string $challenge Challenge string, should be 32 bytes long for ECDSA-SHA256, will be generated if not specified. See https://www.w3.org/TR/webauthn-2/#sctn-cryptographic-challenges
	 * @param array $cred_params Credential parameters (type and algorithm), see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialparameters
	 * @param int $timeout Optional client register timeout in milliseconds
	 * @param array $exclude_creds Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialdescriptor
	 * @param array $authenticator_selection Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-authenticatorselectioncriteria
	 * @param string $attestation Optional, should be one of https://www.w3.org/TR/webauthn-2/#enumdef-attestationconveyancepreference
	 * @param array $extensions Optional, see https://www.w3.org/TR/webauthn-2/#dictdef-authenticationextensionsclientinputs
	 */
	public static function buildPublicKeyCredentialCreationOptions(array $rp, array $user, ?string $challenge = null, array $cred_params = [['type' => 'public-key', 'alg' => Cose::ALG_ES256]], ?int $timeout = null, array $exclude_creds = null, array $authenticator_selection = null, string $attestation = self::AT_NONE, ?array $extensions = null): array {
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
	 * @param string $input CBOR-encoded attestation object string
	 * @return ?AttestationObject null on failure
	 */
	public static function decodeAttestationObject(string $input): ?AttestationObject {
		$obj = Cbor::decode($input);

		$validator = new AttestationObjectValidator();
		if (!$validator->validate($obj)) {
			Log::info(sprintf('Webauthn/decodeAttestationObject: %s', $validator->getMessage()));
			return null;
		}

		$att_stmt = null;
		switch ($obj['fmt']) {
		case 'packed':
			$x5c = null;
			if (array_key_exists('x5c', $obj['attStmt']) && is_array($obj['attStmt']['x5c']) && count($obj['attStmt']['x5c']) > 0) {
				$x5c = $obj['attStmt']['x5c'];
			}
			$att_stmt = new AttestationStatement(
				alg: $obj['attStmt']['alg'],
				sig: $obj['attStmt']['sig'],
				x5c: $x5c,
			);
			break;
		case 'android-key':
			$att_stmt = new AttestationStatement(
				alg: $obj['attStmt']['alg'],
				sig: $obj['attStmt']['sig'],
				x5c: $obj['attStmt']['x5c'],
			);
			break;
		case 'android-safetynet':
			$att_stmt = new AttestationStatement(
				alg: $obj['attStmt']['ver'],
				sig: $obj['attStmt']['response'],
			);
			break;
		case 'fido-u2f':
			$att_stmt = new AttestationStatement(
				sig: $obj['attStmt']['sig'],
				x5c: $obj['attStmt']['x5c'],
			);
			break;
		case 'tpm':
			$att_stmt = new AttestationStatement(
				ver: $obj['attStmt']['ver'],
				alg: $obj['attStmt']['alg'],
				x5c: $obj['attStmt']['x5c'],
				sig: $obj['attStmt']['sig'],
				certInfo: $obj['attStmt']['certInfo'],
				pubArea: $obj['attStmt']['pubArea'],
			);
			break;
		case 'apple':
			$att_stmt = new AttestationStatement(
				x5c: $obj['attStmt']['x5c'],
			);
			break;
		case 'apple-appattest':
			$att_stmt = new AttestationStatement(
				x5c: $obj['attStmt']['x5c'],
				receipt: $obj['attStmt']['receipt'],
			);
			break;
		default:
			$att_stmt = null;
			break;
		}

		$auth_data = static::decodeAuthData($obj['authData']);
		if (!$auth_data) return null;

		return new AttestationObject(
			fmt: $obj['fmt'],
			authDataRaw: $obj['authData'],
			authData: $auth_data,
			attStmt: $att_stmt,
		);
	}

	/**
	 * Decode WebAuthn client data
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#dom-authenticatorattestationresponse-attestationobject
	 * @param string $input JSON-encoded client data string
	 * @return ?ClientData null on failure
	 */
	public static function decodeClientData(string $input): ?ClientData {
		$obj = Json::decode($input);

		$validator = new ClientDataValidator();
		if (!$validator->validate($obj)) {
			Log::info(sprintf('Webauthn/decodeClientData: %s', $validator->getMessage()));
			return null;
		}

		$challenge = Base64Url::decode($obj['challenge']);
		if ($challenge === false) return null;

		return new ClientData(
			type: $obj['type'],
			challenge: $challenge,
			origin: $obj['origin'],
		);
	}

	/**
	 * Decode WebAuthn authenticator data
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#sec-authenticator-data
	 * @param string $input Raw authenticator data string
	 * @return ?AuthData null on failure
	 */
	public static function decodeAuthData(string $input): ?AuthData {
		$rest_cbor = null;
		$pubkey_cose = null;
		$credential_id_len = 0;
		$credential_id = null;
		$aaguid = null;
		if (strlen($input) > 37) {
			$credential_id_len = (int)(unpack('n', substr($input, 53, 2))[1]);
			$credential_id = substr($input, 55, $credential_id_len);
			$aaguid = substr($input, 37, 16);
			$pubkey_cose = Cbor::decode(substr($input, 55 + $credential_id_len), $rest_cbor);
			if (!is_array($pubkey_cose) || !array_key_exists(Cose::KEY_COMMON_PARAM_KTY, $pubkey_cose)) return null;
		}
		$extensions = Cbor::decode($rest_cbor);

		return new AuthData(
			rpIdHash: substr($input, 0, 32),
			flags: ord(substr($input, 32, 1)),
			signCount: (int)(unpack('N', substr($input, 33, 4))[1]),
			aaguid: $aaguid,
			credentialId: $credential_id,
			credentialPublicKey: $pubkey_cose,
			extensions: $extensions,
		);
	}

	/**
	 * Decode WebAuthn TpmCertInfo
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#sec-authenticator-data
	 * @param string $input Raw WebAuthn TPM certInfo string
	 * @return ?TpmCertInfo null on failure
	 */
	public static function decodeTpmCertInfo(string $input): ?TpmCertInfo {
		$data = [];
		$offset = 0;

		$parsed_data = unpack('Nmagic/ntype', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 4 + 2;

		if ($data['type'] !== TpmCertInfo::TPM_ST_ATTEST_CERTIFY) return null;

		$parsed_data = unpack('nqualifiedSignerLength/nqualifiedSignerHashType', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 2;

		$data['qualifiedSigner'] = substr($input, $offset, $parsed_data['qualifiedSignerLength']);
		$offset += $parsed_data['qualifiedSignerLength'];

		$parsed_data = unpack('nextraDataLength', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 2;

		$data['extraData'] = substr($input, $offset, $parsed_data['extraDataLength']);
		$offset += $parsed_data['extraDataLength'];

		$parsed_data = unpack('Jclock/NresetCount/NrestartCount/Csafe/JfirmwareVersion/nnameLength/nnameHashType', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 8 + 4 + 4 + 1 + 8 + 2;

		$data['name'] = substr($input, $offset, $parsed_data['nameLength']);
		$offset += $parsed_data['nameLength'];

		$parsed_data = unpack('nqualifiedNameLength/nqualifiedNameHashType', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 2;

		$data['qualifiedName'] = substr($input, $offset, $parsed_data['qualifiedNameLength']);
		$offset += $parsed_data['qualifiedNameLength'];

		return new TpmCertInfo(
			magic: $data['magic'],
			type: $data['type'],
			qualifiedSignerHashType: $data['qualifiedSignerHashType'],
			qualifiedSigner: $data['qualifiedSigner'],
			extraData: $data['extraData'],
			clockInfo: new TpmClockInfo(
				clock: $data['clock'],
				resetCount: $data['resetCount'],
				restartCount: $data['restartCount'],
				safe: $data['safe'] === 1,
			),
			firmwareVersion: $data['firmwareVersion'],
			nameHashType: $data['nameHashType'],
			name: $data['name'],
			qualifiedNameHashType: $data['qualifiedNameHashType'],
			qualifiedName: $data['qualifiedName'],
		);
	}

	/**
	 * Decode WebAuthn TpmPubArea
	 *
	 * @link https://www.w3.org/TR/webauthn-2/#sec-authenticator-data
	 * @param string $input Raw WebAuthn TPM pubArea string
	 * @return ?TpmPubArea null on failure
	 */
	public static function decodeTpmPubArea(string $input): ?TpmPubArea {
		$data = [];
		$offset = 0;

		$parsed_data = unpack('ntype/nnameHashType/NobjectAttributes/nauthPolicyLength', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 2 + 2 + 4 + 2 + 2 + 2;

		$data['authPolicy'] = substr($input, $offset, $parsed_data['authPolicyLength']);
		$offset += $parsed_data['authPolicyLength'];

		if ($data['type'] === TpmKeyParameters::TPM_ALG_RSA) {
			$parsed_data = unpack('nsymmetric/nscheme/nkeyBits/Nexponent', $input, $offset);
			if (!$parsed_data) return null;
			$data = array_merge($data, $parsed_data);
			$offset += 2 + 2 + 2 + 4;
		} elseif ($data['type'] === TpmKeyParameters::TPM_ALG_ECC) {
			$parsed_data = unpack('nsymmetric/nscheme/ncurveId/nkdf', $input, $offset);
			if (!$parsed_data) return null;
			$data = array_merge($data, $parsed_data);
			$offset += 2 + 2 + 2 + 2;
		}

		$parsed_data = unpack('nuniqueLength', $input, $offset);
		if (!$parsed_data) return null;
		$data = array_merge($data, $parsed_data);
		$offset += 2;

		$data['unique'] = substr($input, $offset, $parsed_data['uniqueLength']);
		$offset += $parsed_data['uniqueLength'];

		return new TpmPubArea(
			type: $data['type'],
			nameHashType: $data['nameHashType'],
			objectAttributes: $data['objectAttributes'],
			authPolicy: $data['authPolicy'],
			parameters: new TpmKeyParameters(
				symmetric: array_key_exists('symmetric', $data) ? $data['symmetric'] : null,
				scheme: array_key_exists('scheme', $data) ? $data['scheme'] : null,
				keyBits: array_key_exists('keyBits', $data) ? $data['keyBits'] : null,
				exponent: array_key_exists('exponent', $data) ? $data['exponent'] : null,
				curveId: array_key_exists('curveId', $data) ? $data['curveId'] : null,
				kdf: array_key_exists('kdf', $data) ? $data['kdf'] : null,
			),
			unique: $data['unique'],
		);
	}

	public static function getAttestationMetadata(string $aaguid): ?array {
		return App::$app::getFidoMds()->getMetadata($aaguid);
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
	 * @param string $client_data_json clientDataJSON string
	 * @param string $auth_data_raw authenticatorData raw bytes
	 * @param AuthData $auth_data authenticatorData decoded with decodeAuthData
	 * @param string $known_challenge Known raw challenge string (not encoded)
	 * @param string $known_origin Known relying party origin
	 * @param string $known_rp_id Known relying party ID
	 * @param string $public_key Known authenticator public key
	 * @param string $signature Authenticator signature
	 * @param int $last_sign_count Last known authenticator signCount
	 * @param bool $uv_required Whether UV (User Verification) flag is required
	 * @param array $cred_params Credential parameters (type and algorithm), see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialparameters
	 */
	public static function verifyAssertion(string $client_data_json, string $auth_data_raw, AuthData $auth_data, string $known_challenge, string $known_origin, string $known_rp_id, string $public_key, string $signature, int $last_sign_count, int $alg = Cose::ALG_ES256, bool $uv_required = false): bool {
		// TODO: figure out if attestation data can/should also be provided and verified during assertion (not only during registration)
		if (!($client_data = static::decodeClientData($client_data_json))) {
			Log::info('Webauthn/verifyAssertion: Client data validation failed');
			return false;
		}

		// check clientData.type - 7.2 step 11
		if ($client_data->type !== ClientData::TYPE_WEBAUTHN_GET) {
			Log::info(sprintf('Webauthn/verifyAssertion: clientData.type "%s" did not match expected value "%s"', $client_data->type, ClientData::TYPE_WEBAUTHN_GET));
			return false;
		}
		// check clientData.challenge against known challenge - 7.2 step 12
		if ($client_data->challenge !== $known_challenge) {
			Log::info(sprintf('Webauthn/verifyAssertion: clientData.challenge %s did not match expected value %s', bin2hex($client_data->challenge), bin2hex($known_challenge)));
			return false;
		}
		// check clientData.origin against known origin - 7.2 step 13
		if ($client_data->origin !== $known_origin) {
			Log::info(sprintf('Webauthn/verifyAssertion: clientData.origin "%s" did not match expected value "%s"', $client_data->origin, $known_origin));
			return false;
		}

		// hash client data - 7.2 step 19
		$client_data_hash = hash('sha256', $client_data_json, true);

		// check rpIdHash against known RP ID - 7.2 step 15
		$auth_data_expected_rp_id_hash = hash('sha256', $known_rp_id, true);
		if ($auth_data->rpIdHash !== $auth_data_expected_rp_id_hash) {
			Log::info(sprintf('Webauthn/verifyAssertion: authData.rpIdHash %s did not match expected value %s', bin2hex($auth_data->rpIdHash), bin2hex($auth_data_expected_rp_id_hash)));
			return false;
		}
		// check UP flag - 7.2 step 16
		if ($auth_data->flags & Webauthn::FLAG_UP !== Webauthn::FLAG_UP) {
			Log::info('Webauthn/verifyAssertion: authData.flags did not contain user presence flag');
			return false;
		}
		// check UV flag - 7.2 step 17
		if ($uv_required && $auth_data->flags & Webauthn::FLAG_UV !== Webauthn::FLAG_UV) {
			Log::info('Webauthn/verifyAssertion: authData.flags did not contain user verification flag');
			return false;
		}

		// verify signature over authData - 7.2 step 20

		// get signature base
		$signature_base  = $auth_data_raw;    // authData
		$signature_base .= $client_data_hash; // clientDataHash

		// verify signature
		if ($signature === null || $public_key === null) {
			return false;
		}
		$signature_valid = Cose::verify($signature_base, $signature, $public_key, $alg);
		if (!$signature_valid) {
			Log::info(sprintf('Webauthn/verifyAssertion: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($signature), bin2hex($signature_base), bin2hex($public_key), $alg));
			return false;
		}

		// 7.2 step 21
		// - if either authenticator sign count or known sign count are
		// non-zero, ensure that authenticator sign count is greater than
		// known sign count
		if ($auth_data->signCount !== 0 || $last_sign_count !== 0) {
			if ($auth_data->signCount <= $last_sign_count) {
				Log::info('Webauthn/verifyAssertion: Authenticator counter less than or equal to last known counter, possibly a duplicated authenticator');
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
	 * @param string $client_data_json clientDataJSON string
	 * @param AttestationObject $att_obj attestationObject decoded with decodeAttestationObject
	 * @param string $known_challenge Known raw challenge string (not encoded)
	 * @param string $known_origin Known relying party origin
	 * @param string $known_rp_id Known relying party ID
	 * @param bool $uv_required Whether UV (User Verification) flag is required
	 * @param bool $attestation_required Whether attestation is required
	 * @param array $cred_params Credential parameters (type and algorithm), see https://www.w3.org/TR/webauthn-2/#dictdef-publickeycredentialparameters
	 */
	public static function verifyRegistration(string $client_data_json, AttestationObject $att_obj, string $known_challenge, string $known_origin, string $known_rp_id, bool $uv_required = false, bool $attestation_required = true, array $acceptable_attestation_levels = self::ATTESTATION_LEVELS_ANY, array $cred_params = [['type' => 'public-key', 'alg' => Cose::ALG_ES256]]): bool {
		if (!($client_data = static::decodeClientData($client_data_json))) {
			Log::info('Webauthn/verifyRegistration: Client data validation failed');
			return false;
		}

		// check clientData.type - 7.1 step 7
		if ($client_data->type !== ClientData::TYPE_WEBAUTHN_CREATE) {
			Log::info(sprintf('Webauthn/verifyRegistration: clientData.type "%s" did not match expected value "%s"', $client_data->type, ClientData::TYPE_WEBAUTHN_CREATE));
			return false;
		}
		// check clientData.challenge against known challenge - 7.1 step 8
		if ($client_data->challenge !== $known_challenge) {
			Log::info(sprintf('Webauthn/verifyRegistration: clientData.challenge %s did not match expected value %s', bin2hex($client_data->challenge), bin2hex($known_challenge)));
			return false;
		}
		// check clientData.origin against known origin - 7.1 step 9
		if ($client_data->origin !== $known_origin) {
			Log::info(sprintf('Webauthn/verifyRegistration: clientData.origin "%s" did not match expected value "%s"', $client_data->origin, $known_origin));
			return false;
		}

		// hash client data - 7.1 step 11
		$client_data_hash = hash('sha256', $client_data_json, true);

		// check rpIdHash against known RP ID - 7.1 step 13
		$auth_data_expected_rp_id_hash = hash('sha256', $known_rp_id, true);
		if ($att_obj->authData->rpIdHash !== $auth_data_expected_rp_id_hash) {
			Log::info(sprintf('Webauthn/verifyRegistration: authData.rpIdHash %s did not match expected value %s', bin2hex($att_obj->authData->rpIdHash), bin2hex($auth_data_expected_rp_id_hash)));
			return false;
		}
		// check UP flag - 7.1 step 14
		if ($att_obj->authData->flags & Webauthn::FLAG_UP !== Webauthn::FLAG_UP) {
			Log::info('Webauthn/verifyRegistration: authData.flags did not contain user presence flag');
			return false;
		}
		// check UV flag - 7.1 step 15
		if ($uv_required && $att_obj->authData->flags & Webauthn::FLAG_UV !== Webauthn::FLAG_UV) {
			Log::info('Webauthn/verifyRegistration: authData.flags did not contain user verification flag');
			return false;
		}

		if ($att_obj->attStmt !== null) {
			// ensure authenticator algorithm is allowed - 7.1 step 16
			$alg_found = false;
			foreach ($cred_params as $cred_param) {
				if (array_key_exists('alg', $cred_param) && $cred_param['alg'] === $att_obj->attStmt->alg) {
					$alg_found = true;
					break;
				}
			}

			// 7.1 step 18-19
			// - check that attestation statement format is supported
			// - get attestation metadata
			// - execute verification procedure
			$att_metadata = null;
			switch ($att_obj->fmt) {
			case 'packed':
				// 7.1 step 16
				if (!$alg_found) {
					Log::info(sprintf('Webauthn/verifyRegistration: attStmt.alg did not match expected value %d', $att_obj->attStmt->alg));
					return false;
				}

				// 8.2 verification procedure
				if ($att_obj->attStmt->x5c !== null && count($att_obj->attStmt->x5c) > 0) {
					// get certificate
					$cert = Pem::encode($att_obj->attStmt->x5c[0], Pem::LABEL_CERTIFICATE);

					// verify that sig is a valid signature over the concatenation of authenticatorData and clientDataHash using the attestation public key in attestnCert with the algorithm specified in alg
					$att_to_be_signed  = $att_obj->authDataRaw;
					$att_to_be_signed .= $client_data_hash;
					$signature_valid = Cose::verify($att_to_be_signed, $att_obj->attStmt->sig, $cert, $att_obj->attStmt->alg);
					if (!$signature_valid) {
						Log::info(sprintf('Webauthn/verifyRegistration: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($att_obj->attStmt->sig), bin2hex($att_to_be_signed), bin2hex($att_obj->attStmt->x5c[0]), $att_obj->attStmt->alg));
						return false;
					}

					// verify that attestnCert meets the requirements in 8.2.1 Packed Attestation Statement Certificate Requirements
					$cert_data = openssl_x509_parse($cert);
					if (
						!array_key_exists('subject', $cert_data) || !is_array($cert_data['subject']) ||
						!array_key_exists('version', $cert_data) ||
						!array_key_exists('extensions', $cert_data) ||
						!array_key_exists('basicConstraints', $cert_data['extensions']) || !is_string($cert_data['extensions']['basicConstraints']) ||
						!array_key_exists(self::OID_FIDO_GEN_CE_AAGUID, $cert_data['extensions']) || !is_string($cert_data['extensions'][self::OID_FIDO_GEN_CE_AAGUID])
					) {
						Log::info('Webauthn/verifyRegistration: attestnCert missing subject, version, extensions or basicConstraints');
						return false;
					}
					// verify subject-C
					if (!array_key_exists('C', $cert_data['subject']) || !is_string($cert_data['subject']['C'])) {
						Log::info('Webauthn/verifyRegistration: attestnCert subject missing country');
						return false;
					}
					// verify subject-O
					if (!array_key_exists('O', $cert_data['subject']) || !is_string($cert_data['subject']['O'])) {
						Log::info('Webauthn/verifyRegistration: attestnCert subject missing organization');
						return false;
					}
					// verify subject-OU
					if (!array_key_exists('OU', $cert_data['subject']) || $cert_data['subject']['OU'] !== "Authenticator Attestation") {
						Log::info('Webauthn/verifyRegistration: attestnCert subject missing organizational unit');
						return false;
					}
					// verify subject-CN
					if (!array_key_exists('CN', $cert_data['subject']) || !is_string($cert_data['subject']['CN'])) {
						Log::info('Webauthn/verifyRegistration: attestnCert subject missing common name');
						return false;
					}
					// verify version
					// version 3 is indicated by an ASN.1 INTEGER with value 2
					if ($cert_data['version'] !== 2) {
						Log::info('Webauthn/verifyRegistration: attestnCert version is not 3');
						return false;
					}
					// verify basicConstraints
					$cert_basic_constraints = explode(', ', $cert_data['extensions']['basicConstraints']);
					if (!in_array('CA:FALSE', $cert_basic_constraints)) {
						Log::info('Webauthn/verifyRegistration: attestnCert basicConstraints CA flag is not false');
						return false;
					}
					// ensure extension id-fido-gen-ce-aaguid is present in attestnCert
					if (!array_key_exists(self::OID_FIDO_GEN_CE_AAGUID, $cert_data['extensions'])) {
						Log::info('Webauthn/verifyRegistration: attestnCert extensions missing id-fido-gen-ce-aaguid');
						return false;
					}
					// if attestnCert contains extension id-fido-gen-ce-aaguid verify that the value of this extension matches the aaguid in authenticatorData
					if (substr($cert_data['extensions'][self::OID_FIDO_GEN_CE_AAGUID], -16) !== $att_obj->authData->aaguid) {
						Log::info(sprintf(
							'Webauthn/verifyRegistration: attestnCert aaguid %s does not match authData aaguid %s',
							bin2hex(substr($cert_data['extensions'][self::OID_FIDO_GEN_CE_AAGUID], -16)),
							bin2hex($att_obj->authData->aaguid)
						));
						return false;
					}

					// get attestation metadata
					$att_metadata = static::getAttestationMetadata($att_obj->authData->aaguid);
				} else {
					// 4. self attestation
					// validate that alg matches the algorithm of the credentialPublicKey in authenticatorData
					if (
						$att_obj->authData->credentialPublicKey === null ||
						!array_key_exists(Cose::KEY_COMMON_PARAM_ALG, $att_obj->authData->credentialPublicKey) ||
						$att_obj->attStmt->alg !== $att_obj->authData->credentialPublicKey[Cose::KEY_COMMON_PARAM_ALG]
					) {
						Log::info('Webauthn/verifyRegistration: attestnCert algorithm does not match authData algorithm');
						return false;
					}

					$public_key = Cose::pubkeyToDER($att_obj->authData->credentialPublicKey);
					if (!$public_key) {
						Log::info('Webauthn/verifyRegistration: public key not valid or not supported');
						return false;
					}

					// verify that sig is a valid signature over the concatenation of authenticatorData and clientDataHash using the credential public key with alg
					$att_to_be_signed  = $att_obj->authDataRaw;
					$att_to_be_signed .= $client_data_hash;
					$signature_valid = Cose::verify($att_to_be_signed, $att_obj->attStmt->sig, $public_key, $att_obj->attStmt->alg);
					if (!$signature_valid) {
						Log::info(sprintf('Webauthn/verifyRegistration: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($att_obj->attStmt->sig), bin2hex($att_to_be_signed), bin2hex($public_key), $att_obj->attStmt->alg));
						return false;
					}

					$att_metadata = [
						'attestationTypes' => [
							self::ATTESTATION_BASIC_SURROGATE,
						],
						'attestationRootCertificates' => [
							base64_encode($public_key),
						]
					];
				}
				break;

			case 'tpm':
				// 7.1 step 16
				if (!$alg_found) {
					Log::info(sprintf('Webauthn/verifyRegistration: attStmt.alg did not match expected value %d', $att_obj->attStmt->alg));
					return false;
				}

				// 8.2 verification procedure

				// parse pubArea
				$pub_area = static::decodeTpmPubArea($att_obj->attStmt->pubArea);
				if (!$pub_area) {
					Log::info('Webauthn/verifyRegistration: attStmt.pubArea invalid');
					return false;
				}

				// verify that the public key specified by the parameters and unique fields of pubArea is identical to the credentialPublicKey in the attestedCredentialData in authenticatorData
				$att_pubkey_der = $pub_area->toDER();
				if (!$att_pubkey_der) {
					Log::info('Webauthn/verifyRegistration: attStmt.pubArea public key invalid');
					return false;
				}
				$cred_pubkey_der = Cose::pubkeyToDER($att_obj->authData->credentialPublicKey);
				if ($att_pubkey_der !== $cred_pubkey_der) {
					Log::info('Webauthn/verifyRegistration: attStmt.pubArea public key does not match authData public key');
					return false;
				}

				// parse certInfo (TCG TPM 2.0 TPMS_ATTEST structure)
				$cert_info = static::decodeTpmCertInfo($att_obj->attStmt->certInfo);
				if (!$cert_info) {
					Log::info('Webauthn/verifyRegistration: attStmt.certInfo invalid');
					return false;
				}

				// verify that magic is set to TPM_GENERATED_VALUE
				if ($cert_info->magic !== TpmCertInfo::TPM_GENERATED_VALUE) {
					Log::info('Webauthn/verifyRegistration: attStmt.certInfo.magic invalid');
					return false;
				}

				// verify that type is set to TPM_ST_ATTEST_CERTIFY
				if ($cert_info->type !== TpmCertInfo::TPM_ST_ATTEST_CERTIFY) {
					Log::info('Webauthn/verifyRegistration: attStmt.certInfo.type invalid');
					return false;
				}

				// verify that extraData is set to the hash of attToBeSigned using the hash algorithm employed in "alg"
				$att_to_be_signed  = $att_obj->authDataRaw;
				$att_to_be_signed .= $client_data_hash;
				if (!hash_equals(Cose::hash($att_to_be_signed, $att_obj->attStmt->alg), $cert_info->extraData)) {
					Log::info('Webauthn/verifyRegistration: attStmt.certInfo.extraData invalid');
					return false;
				}

				// verify that attested contains a TPMS_CERTIFY_INFO structure as specified in [TPMv2-Part2] section 10.12.3, whose name field contains a valid Name for pubArea, as computed using the algorithm in the nameAlg field of pubArea using the procedure specified in [TPMv2-Part1] section 16
				$pub_area_hash_algo = match ($pub_area->nameHashType) {
					TpmKeyParameters::TPM_ALG_SHA, TpmKeyParameters::TPM_ALG_SHA1 => 'sha1',
					TpmKeyParameters::TPM_ALG_SHA256 => 'sha256',
					TpmKeyParameters::TPM_ALG_SHA384 => 'sha384',
					TpmKeyParameters::TPM_ALG_SHA512 => 'sha512',
					TpmKeyParameters::TPM_ALG_SHA3_256 => 'sha3-256',
					TpmKeyParameters::TPM_ALG_SHA3_384 => 'sha3-384',
					TpmKeyParameters::TPM_ALG_SHA3_512 => 'sha3-512',
					default => null,
				};
				if ($pub_area_hash_algo === null || !in_array($pub_area_hash_algo, hash_algos())) {
					Log::info('Webauthn/verifyRegistration: attStmt.pubArea.nameHashType (nameAlg) not supported');
					return false;
				}
				if (!hash_equals(hash($pub_area_hash_algo, $att_obj->attStmt->pubArea), $cert_info->name)) {
					Log::info('Webauthn/verifyRegistration: attStmt.certInfo.name does not match computed name of attStmt.pubArea');
					return false;
				}

				// verify that x5c is present
				if ($att_obj->attStmt->x5c === null || count($att_obj->attStmt->x5c) < 1) {
					Log::info('Webauthn/verifyRegistration: attCert missing from attStmt');
					return false;
				}

				// verify the sig is a valid signature over certInfo using the attestation public key in aikCert with the algorithm specified in alg
				$cert = Pem::encode($att_obj->attStmt->x5c[0], Pem::LABEL_CERTIFICATE);
				$signature_valid = Cose::verify($att_obj->attStmt->certInfo, $att_obj->attStmt->sig, $cert, $att_obj->attStmt->alg);
				if (!$signature_valid) {
					Log::info(sprintf('Webauthn/verifyRegistration: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($att_obj->attStmt->sig), bin2hex($att_obj->attStmt->certInfo), bin2hex($att_obj->attStmt->x5c[0]), $att_obj->attStmt->alg));
					return false;
				}

				// verify that aikCert meets the requirements in 8.3.1 TPM Attestation Statement Certificate Requirements
				$cert_data = openssl_x509_parse($cert);
				if (
					!array_key_exists('version', $cert_data) ||
					!array_key_exists('extensions', $cert_data) ||
					!array_key_exists('subjectAltName', $cert_data['extensions']) || !is_string($cert_data['subjectAltName']) ||
					!array_key_exists('extendedKeyUsage', $cert_data['extensions']) || !is_string($cert_data['extendedKeyUsage']) ||
					!array_key_exists('basicConstraints', $cert_data['extensions']) || !is_string($cert_data['extensions']['basicConstraints']) ||
					!array_key_exists(self::OID_FIDO_GEN_CE_AAGUID, $cert_data['extensions']) || !is_string($cert_data['extensions'][self::OID_FIDO_GEN_CE_AAGUID])
				) {
					Log::info('Webauthn/verifyRegistration: aikCert missing version, extensions, subjectAltName or basicConstraints');
					return false;
				}
				// verify version
				// version 3 is indicated by an ASN.1 INTEGER with value 2
				if ($cert_data['version'] !== 2) {
					Log::info('Webauthn/verifyRegistration: aikCert version is not 3');
					return false;
				}
				// verify subject
				if (array_key_exists('subject', $cert_data) && is_array($cert_data['subject']) && count($cert_data['subject']) > 0) {
					Log::info('Webauthn/verifyRegistration: aikCert has subject, but is not supposed to');
					return false;
				}
				// verify SAN
				$cert_san = explode(', ', $cert_data['extensions']['subjectAltName']);
				$cert_san_manufacturer = null;
				$cert_san_model = null;
				$cert_san_version = null;
				foreach ($cert_san as $san) {
					$san_kv = explode(':', $san, 2);
					if (count($san_kv) < 2) continue;
					list($san_oid, $san_value) = $san_kv;

					if ($san_oid === self::OID_TCG_AT_TPM_MANUFACTURER) {
						$cert_san_manufacturer = $san_value;
					} elseif ($san_oid === self::OID_TCG_AT_TPM_MODEL) {
						$cert_san_model = $san_value;
					} elseif ($san_oid === self::OID_TCG_AT_TPM_VERSION) {
						$cert_san_version = $san_value;
					}
				}
				if ($cert_san_manufacturer === null) {
					Log::info('Webauthn/verifyRegistration: aikCert subjectAltName does not contain tcg-at-tpmManufacturer');
					return false;
				}
				if ($cert_san_model === null) {
					Log::info('Webauthn/verifyRegistration: aikCert subjectAltName does not contain tcg-at-tpmModel');
					return false;
				}
				if ($cert_san_version === null) {
					Log::info('Webauthn/verifyRegistration: aikCert subjectAltName does not contain tcg-at-tpmVersion');
					return false;
				}
				// verify contains EKU tcg-kp-AIKCertificate
				$cert_eku = explode(', ', $cert_data['extensions']['extendedKeyUsage']);
				if (!in_array(self::OID_TCG_KP_AIK_CERTIFICATE, $cert_eku)) {
					Log::info('Webauthn/verifyRegistration: aikCert extendedKeyUsage does not contain tcg-kp-AIKCertificate');
					return false;
				}
				// verify basicConstraints
				$cert_basic_constraints = explode(', ', $cert_data['extensions']['basicConstraints']);
				if (!in_array('CA:FALSE', $cert_basic_constraints)) {
					Log::info('Webauthn/verifyRegistration: aikCert basicConstraints CA flag is not false');
					return false;
				}
				// ensure extension id-fido-gen-ce-aaguid is present in aikCert
				if (!array_key_exists(self::OID_FIDO_GEN_CE_AAGUID, $cert_data['extensions'])) {
					Log::info('Webauthn/verifyRegistration: aikCert extensions missing id-fido-gen-ce-aaguid');
					return false;
				}
				// if aikCert contains extension id-fido-gen-ce-aaguid verify that the value of this extension matches the aaguid in authenticatorData
				if (substr($cert_data['extensions'][self::OID_FIDO_GEN_CE_AAGUID], -16) !== $att_obj->authData->aaguid) {
					Log::info(sprintf(
						'Webauthn/verifyRegistration: aikCert aaguid %s does not match authData aaguid %s',
						bin2hex(substr($cert_data['extensions'][self::OID_FIDO_GEN_CE_AAGUID], -16)),
						bin2hex($att_obj->authData->aaguid)
					));
					return false;
				}

				// get attestation metadata
				$att_metadata = static::getAttestationMetadata($att_obj->authData->aaguid);
				break;

			case 'android-key':
				// 7.1 step 16
				if (!$alg_found) {
					Log::info(sprintf('Webauthn/verifyRegistration: attStmt.alg did not match expected value %d', $att_obj->attStmt->alg));
					return false;
				}

				// verify that sig is a valid signature over the concatenation of authenticatorData and clientDataHash using the public key in the first certificate in x5c with the algorithm specified in alg
				$cert = Pem::encode($att_obj->attStmt->x5c[0], Pem::LABEL_CERTIFICATE);
				$att_to_be_signed  = $att_obj->authDataRaw;
				$att_to_be_signed .= $client_data_hash;
				$signature_valid = Cose::verify($att_to_be_signed, $att_obj->attStmt->sig, $cert, $att_obj->attStmt->alg);
				if (!$signature_valid) {
					Log::info(sprintf('Webauthn/verifyRegistration: Signature %s over data %s could not be verified with public key %s using COSE algorithm %d', bin2hex($att_obj->attStmt->sig), bin2hex($att_to_be_signed), bin2hex($att_obj->attStmt->x5c[0]), $att_obj->attStmt->alg));
					return false;
				}

				// verify that the public key in the first certificate in x5c matches the credentialPublicKey in the attestedCredentialData in authenticatorData
				$att_pubkey = openssl_pkey_get_public($cert);
				if (!$att_pubkey) {
					Log::info('Webauthn/verifyRegistration: credCert public key invalid');
					return false;
				}
				$att_pubkey_details = openssl_pkey_get_details($att_pubkey);
				if (!is_array($att_pubkey_details) || !array_key_exists('key', $att_pubkey_details)) {
					Log::info('Webauthn/verifyRegistration: credCert public key invalid');
					return false;
				}
				$att_pubkey_der = Pem::decode($att_pubkey_details['key']);
				$cred_pubkey_der = Cose::pubkeyToDER($att_obj->authData->credentialPublicKey);
				if ($att_pubkey_der !== $cred_pubkey_der) {
					Log::info('Webauthn/verifyRegistration: credCert public key does not match authData public key');
					return false;
				}

				$cert_data = openssl_x509_parse($cert);
				if (!array_key_exists(self::OID_GOOGLE_KEY_ATTESTATION, $cert_data['extensions']) || !is_string($cert_data['extensions'][self::OID_GOOGLE_KEY_ATTESTATION])) {
					Log::info('Webauthn/verifyRegistration: credCert missing Google key attestation extension');
					return false;
				}
				try {
					$google_key_attestation = Sequence::fromDER($cert_data['extensions'][self::OID_GOOGLE_KEY_ATTESTATION]);
				} catch (DecodeException $e) {
					Log::info(sprintf('Webauthn/verifyRegistration: Unable to decode credCert Google key attestation extension: %s', $e->getMessage()));
					return false;
				}
				if (!($google_key_attestation instanceof Sequence) || count($google_key_attestation->elements()) < 8) {
					Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension is invalid (missing data)');
					return false;
				}
				list(
					$gka_attestation_version,
					$gka_attestation_security_level,
					$gka_key_mint_version,
					$gka_key_mint_security_level,
					$gka_attestation_challenge,
					$gka_unique_id,
					$gka_software_enforced,
					$gka_tee_enforced,
				) = $google_key_attestation->elements();
				try {
					$gka_attestation_version = $gka_attestation_version->asInteger();
					$gka_attestation_security_level = $gka_attestation_security_level->asEnumerated();
					$gka_key_mint_version = $gka_key_mint_version->asInteger();
					$gka_key_mint_security_level = $gka_key_mint_security_level->asEnumerated();
					$gka_attestation_challenge = $gka_attestation_challenge->asOctetString();
					$gka_unique_id = $gka_unique_id->asOctetString();
					$gka_software_enforced = $gka_software_enforced->asSequence();
					$gka_tee_enforced = $gka_tee_enforced->asSequence();

					// verify that the attestationChallenge field in the attestation certificate extension data is identical to clientDataHash
					if ($gka_attestation_challenge->string() !== $client_data_hash) {
						Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension attestationChallenge does not match clientDataHash');
						return false;
					}
					// verify that the AuthorizationList.allApplications field is not present on either authorization list (softwareEnforced nor teeEnforced), since PublicKeyCredential MUST be scoped to the RP ID
					if ($gka_tee_enforced->hasTagged(600) || $gka_software_enforced->hasTagged(600)) {
						Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension contains AuthorizationList.allApplications field');
						return false;
					}

					// verify that the value in the AuthorizationList.origin field is equal to KM_ORIGIN_GENERATED
					if ($gka_tee_enforced->hasTagged(702)) {
						$gka_origin = $gka_tee_enforced->getTagged(702)->asUnspecified()->asInteger()->intNumber();
					} elseif ($gka_software_enforced->hasTagged(702)) {
						$gka_origin = $gka_software_enforced->getTagged(702)->asUnspecified()->asInteger()->intNumber();
					} else {
						Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension does not contain AuthorizationList.origin field');
						return false;
					}
					if ($gka_origin !== 0) {
						Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension AuthorizationList.origin field is not KM_ORIGIN_GENERATED');
						return false;
					}

					// verify that the value in the AuthorizationList.purpose field is equal to KM_PURPOSE_SIGN
					if ($gka_tee_enforced->hasTagged(1)) {
						$gka_purpose_set = $gka_tee_enforced->getTagged(1)->asUnspecified()->asSet();
					} elseif ($gka_software_enforced->hasTagged(1)) {
						$gka_purpose_set = $gka_software_enforced->getTagged(1)->asUnspecified()->asSet();
					} else {
						Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension does not contain AuthorizationList.purpose field');
						return false;
					}
					$found_sign_purpose = false;
					foreach ($gka_purpose_set->elements() as $purpose_element) {
						if ($purpose_element->asInteger()->intNumber() === 2) {
							$found_sign_purpose = true;
							break;
						}
					}
					if (!$found_sign_purpose) {
						Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension AuthorizationList.purpose field does not contain KM_PURPOSE_SIGN');
						return false;
					}
				} catch (\UnexpectedValueException $e) {
					Log::info('Webauthn/verifyRegistration: credCert Google key attestation extension is invalid (invalid data type(s))');
					return false;
				}

				// get attestation metadata
				$att_metadata = static::getAttestationMetadata($att_obj->authData->aaguid);
				break;

			case 'fido-u2f':
				// 8.6 verificaation procedure
				// 2. get certificate
				if ($att_obj->attStmt->x5c === null || count($att_obj->attStmt->x5c) < 1) {
					Log::info('Webauthn/verifyRegistration: attCert missing from attStmt');
					return false;
				}
				$cert = Pem::encode($att_obj->attStmt->x5c[0], Pem::LABEL_CERTIFICATE);
				$key_res = openssl_pkey_get_public($cert);
				if (!$key_res) {
					Log::info('Webauthn/verifyRegistration: attCert invalid');
					return false;
				}
				$key_details = openssl_pkey_get_details($key_res);
				if (
					!is_array($key_details) ||
					!array_key_exists('key', $key_details) ||
					!array_key_exists('ec', $key_details) ||
					!array_key_exists('curve_oid', $key_details['ec']) ||
					$key_details['ec']['curve_oid'] !== ECPublicKeyAlgorithmIdentifier::CURVE_PRIME256V1
				) {
					Log::info('Webauthn/verifyRegistration: attCert public key is not an EC public key over the P-256 curve');
					return false;
				}
				$att_pubkey = $key_details['key'];

				// 4. get public key
				$public_key_raw = Cose::ecPubkeyToRaw($att_obj->authData->credentialPublicKey ?? [], false);
				if (empty($public_key_raw)) {
					Log::info('Webauthn/verifyRegistration: credentialPublicKey in authData is invalid');
					return false;
				}

				// 5. get signature base
				$att_to_be_signed  = "\0";                             // reserved
				$att_to_be_signed .= $att_obj->authData->rpIdHash;     // rpIdHash
				$att_to_be_signed .= $client_data_hash;                // clientDataHash
				$att_to_be_signed .= $att_obj->authData->credentialId; // credentialId
				$att_to_be_signed .= $public_key_raw;                  // credentialPublicKey

				// 6. verify signature
				$signature_valid = Ecdsa::verify($att_to_be_signed, $att_obj->attStmt->sig, $att_pubkey, OPENSSL_ALGO_SHA256);
				if (!$signature_valid) {
					Log::info(sprintf('Webauthn/verifyRegistration: Signature %s over data %s could not be verified with public key %s using ECDSA-SHA256', bin2hex($att_obj->attStmt->sig), bin2hex($att_to_be_signed), bin2hex(Pem::decode($att_pubkey))));
					return false;
				}

				// 7. get attestation metadata
				$att_metadata = static::getAttestationMetadata($att_obj->authData->aaguid);
				break;

			case 'apple':
				// concatenate authenticatorData and clientDataHash to form nonceToHash
				$nonce_to_hash  = $att_obj->authDataRaw;
				$nonce_to_hash .= $client_data_hash;

				// perform SHA-256 hash of nonceToHash to produce nonce
				$nonce = hash('sha256', $nonce_to_hash, true);

				$cert = Pem::encode($att_obj->attStmt->x5c[0], Pem::LABEL_CERTIFICATE);
				$cert_data = openssl_x509_parse($cert);
				if (!array_key_exists('extensions', $cert_data)) {
					Log::info('Webauthn/verifyRegistration: credCert missing extensions');
					return false;
				}
				// ensure extension id-apple-anonymous-attestation is present in credCert
				if (!array_key_exists(self::OID_APPLE_ANONYMOUS_ATTESTATION, $cert_data['extensions'])) {
					Log::info('Webauthn/verifyRegistration: credCert extensions missing id-apple-anonymous-attestation');
					return false;
				}

				try {
					$apple_anonymous_attestation = Sequence::fromDER($cert_data['extensions'][self::OID_APPLE_ANONYMOUS_ATTESTATION]);
				} catch (DecodeException $e) {
					Log::info(sprintf('Webauthn/verifyRegistration: Unable to decode credCert Apple anonymous attestation extension: %s', $e->getMessage()));
					return false;
				}
				if (!($apple_anonymous_attestation instanceof Sequence) || count($apple_anonymous_attestation->elements()) < 1) {
					Log::info('Webauthn/verifyRegistration: credCert Apple anonymous attestation extension is invalid (missing data)');
					return false;
				}
				$apple_anonymous_attestation_nonce = $apple_anonymous_attestation->at(0)->asOctetString()->string();

				// verify that nonce equals the value of the extension id-apple-anonymous-attestation in credCert
				if ($apple_anonymous_attestation_nonce !== $nonce) {
					Log::info(sprintf(
						'Webauthn/verifyRegistration: credCert Apple anonymous attestation extension nonce %s does not match computed nonce %s',
						bin2hex($apple_anonymous_attestation_nonce),
						bin2hex($nonce)
					));
					return false;
				}

				// verify that the credential public key equals the Subject Public Key of credCert
				$att_pubkey = openssl_pkey_get_public($cert);
				if (!$att_pubkey) {
					Log::info('Webauthn/verifyRegistration: credCert public key invalid');
					return false;
				}
				$att_pubkey_details = openssl_pkey_get_details($att_pubkey);
				if (!is_array($att_pubkey_details) || !array_key_exists('key', $att_pubkey_details)) {
					Log::info('Webauthn/verifyRegistration: credCert public key invalid');
					return false;
				}
				$att_pubkey_der = Pem::decode($att_pubkey_details['key']);
				$cred_pubkey_der = Cose::pubkeyToDER($att_obj->authData->credentialPublicKey);
				if ($att_pubkey_der !== $cred_pubkey_der) {
					Log::info('Webauthn/verifyRegistration: credCert public key does not match authData public key');
					return false;
				}

				$att_metadata = [
					'attestationTypes' => [
						self::ATTESTATION_ANONCA,
					],
				];
				break;

			// TODO: implement Apple App Attestation
			// case 'apple-appattest':
			// 	break;

			case 'none':
				$att_metadata = [
					'attestationTypes' => [
						self::ATTESTATION_NONE,
					],
				];
				break;

			default:
				Log::info(sprintf('Webauthn/verifyRegistration: Attestation format "%s" not supported', $att_obj->fmt));
				return false;
			}

			// TODO: 7.1 step 20-21
			// TODO: verify x5c against root certificate(s) from FidoMds and Apple
			// TODO: verify achieved attestation level against $acceptable_attestation_levels
		} elseif ($attestation_required) {
			Log::info('Webauthn/verifyRegistration: Attestation required but not provided');
			return false;
		}

		return true;
	}
}