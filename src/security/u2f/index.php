<?php namespace Obie\Security;
use Obie\Encoding\Asn1;
use Obie\Encoding\Pem;
use Obie\Encoding\Json;
use Obie\Encoding\Base64Url;
use Obie\Encoding\Exception\Asn1Exception;
use Obie\Security\U2f\Exception;
use Obie\Security\U2f\ClientData;
use Obie\Security\U2f\SignResponse;
use Obie\Security\U2f\RegisterResponse;
use Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;

/**
 * @deprecated FIDO U2F is deprecated. Use FIDO2 / WebAuthn instead.
 * @package Obie\Security
 */
class U2f {
	// https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-SignResponse
	const ERROR_CODE_OK = 0;
    const ERROR_CODE_OTHER_ERROR = 1;
    const ERROR_CODE_BAD_REQUEST = 2;
    const ERROR_CODE_CONFIGURATION_UNSUPPORTED = 3;
    const ERROR_CODE_DEVICE_INELIGIBLE = 4;
	const ERROR_CODE_TIMEOUT = 5;

	const VERSION_V2 = 'U2F_V2';

	const FIX_CERTS = [
        '349bca1031f8c82c4ceca38b9cebf1a69df9fb3b94eed99eb3fb9aa3822d26e8',
        'dd574527df608e47ae45fbba75a2afdd5c20fd94a02419381813cd55a2a3398f',
        '1d8764f0f7cd1352df6150045c8f638e517270e8b5dda1c63ade9c2280240cae',
        'd0edc9a91a1677435a953390865d208c55b3183c6759c9b5a7ff494c322558eb',
        '6073c436dcd064a48127ddbf6032ac1a66fd59a0c24434f070d4e564c124c897',
        'ca993121846c464d666096d35f13bf44c1b05af205f9b4a1e00cf6cc10c5e511'
	];

	const ECDSA_CURVE = ECPublicKeyAlgorithmIdentifier::CURVE_PRIME256V1;
	const ECDSA_RAW_PUBLIC_KEY_LEN = 1 + (ECPublicKeyAlgorithmIdentifier::MAP_CURVE_TO_SIZE[self::ECDSA_CURVE]/8)*2;

	protected static function fixSignatureUnusedBits(string $cert) {
		if(in_array(hash('sha256', $cert), self::FIX_CERTS, true)) {
            $cert[strlen($cert) - 257] = "\0";
        }
        return $cert;
	}

	/**
	 * Creates a U2F JS API Array<u2f.RegisteredKey>
	 *
	 * @link https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-RegisteredKey
	 * @param array $raw_key_handles - Array containing raw key handle strings
	 * @param string $rp_id - The application id that the RP would like to assert for this key handle, if it's distinct from the application id for the overall request. (Ordinarily this will be omitted.)
	 * @param array $transports - The transport(s) this token supports, if known by the RP.
	 */
	public static function buildRegisteredKeys(array $raw_key_handles, ?string $rp_id = null, ?array $transports = null): array {
		$keys = [];
		foreach ($raw_key_handles as $key_handle_raw) {
			$key = [
				'version'   => self::VERSION_V2,
				'keyHandle' => Base64Url::encodeUnpadded($key_handle_raw)
			];
			if ($transports !== null) {
				$key['transports'] = $transports;
			}
			if ($rp_id !== null) {
				$key['appId'] = $rp_id;
			}
			$keys[] = $key;
		}
		return $keys;
	}

	/**
	 * Decodes and validates ClientData JSON
	 *
	 * @link https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-raw-message-formats-v1.2-ps-20170411.html#idl-def-ClientData
	 * @param string $client_data_json
	 * @return ClientData
	 * @throws Exception
	 */
	public static function decodeClientDataJSON(string|array $client_data_json, string $rp_id): ClientData {
		if (is_string($client_data_json)) {
			$client_data_json = Json::decode($client_data_json);
		}
		if (
			!is_array($client_data_json) ||
			!array_key_exists('typ', $client_data_json) || !is_string($client_data_json['typ']) ||
			!array_key_exists('challenge', $client_data_json) || !is_string($client_data_json['challenge']) ||
			!array_key_exists('origin', $client_data_json) || !is_string($client_data_json['origin'])
		) {
			throw new Exception('Invalid ClientData', Exception::ERR_INVALID_CLIENT_DATA);
		}
		if ($client_data_json['origin'] !== $rp_id) {
			throw new Exception('Relying Party (RP) ID mismatch in ClientData', Exception::ERR_RP_ID_MISMATCH);
		}
		// decode challenge
		$challenge = Base64Url::decode($client_data_json['challenge']);
		if (!is_string($challenge)) {
			throw new Exception('Invalid ClientData: challenge is not a valid base64url string', Exception::ERR_INVALID_CLIENT_DATA);
		}
		return new ClientData(
			typ: $client_data_json['typ'],
			challenge: $challenge,
			origin: $client_data_json['origin'],
		);
	}

	/**
	 * Decodes a U2F JS API u2f.SignResponse
	 *
	 * Does NOT verify the signature (use U2f::verify to verify the signature)
	 *
	 * @link https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-SignResponse
	 * @param SignResponse The u2f.SignResponse object obtained from the client JS API
	 * @throws Exception
	 */
	public static function decodeSignResponse(array $sign_response, string $rp_id): SignResponse {
		// return immediately if client errored
		if (array_key_exists('errorCode', $sign_response) && is_int($sign_response['errorCode']) && $sign_response['errorCode'] !== self::ERROR_CODE_OK) {
			throw new Exception('U2F client error', Exception::ERR_CLIENT_ERROR, client_error_code: $sign_response['errorCode']);
		}

		// validate input
		if (
			!array_key_exists('keyHandle', $sign_response) || !is_string($sign_response['keyHandle']) ||
			!array_key_exists('clientData', $sign_response) || !is_string($sign_response['clientData']) ||
			!array_key_exists('signatureData', $sign_response) || !is_string($sign_response['signatureData'])
		) {
			throw new Exception('Invalid SignResponse data', Exception::ERR_INVALID_DATA);
		}

		// get key handle from result
		$key_handle = Base64Url::decode($sign_response['keyHandle']);
		if (!is_string($key_handle)) {
			throw new Exception('Invalid SignResponse data: keyHandle not a valid base64url string', Exception::ERR_INVALID_DATA);
		}

		// get client data from result
		$client_data_json = Base64Url::decode($sign_response['clientData']);
		if (!is_string($client_data_json)) {
			throw new Exception('Invalid SignResponse data: clientData not a valid base64url string', Exception::ERR_INVALID_DATA);
		}

		// get signature data from result
		$signature_data = Base64Url::decode($sign_response['signatureData']);
		if (!is_string($signature_data)) {
			throw new Exception('Invalid SignResponse data: signatureData not a valid base64url string', Exception::ERR_INVALID_DATA);
		}

		// decode and validate clientData
		$client_data = static::decodeClientDataJSON($client_data_json, $rp_id);

		// get client data hash
		$client_data_hash = hash('sha256', $client_data_json, true);

		// parse decoded signature data
		$flags_raw = substr($signature_data, 0, 1);
		$counter_raw = substr($signature_data, 1, 4);
		$flags = ord($flags_raw);
		$counter = (int)(unpack('N', $counter_raw)[1]);

		// get signature length
		try {
			$signature_length = Asn1::sequenceLength(substr($signature_data, 5));
		} catch (Asn1Exception $e) {
			throw new Exception('Signature (ASN.1 BER/DER SEQUENCE) is not valid', Exception::ERR_INVALID_SIGNATURE_LENGTH);
		}

		// get signature
		$signature = substr($signature_data, 5, $signature_length);

		// verify raw signature data length
		if (strlen($signature_data) !== 5 + $signature_length) {
			throw new Exception('Signature (ASN.1 BER/DER SEQUENCE) is not valid: Value does not match expected length', Exception::ERR_INVALID_SIGNATURE_LENGTH);
		}

		// get signature base
		$signature_base  = hash('sha256', $rp_id, true); // appId hash
		$signature_base .= $flags_raw;                       // flags byte
		$signature_base .= $counter_raw;                 // counter
		$signature_base .= $client_data_hash;            // clientData hash

		return new SignResponse(
			keyHandle: $key_handle,
			counter: $counter,
			flags: $flags,
			clientData: $client_data,
			clientDataHash: $client_data_hash,
			signatureData: $signature_base,
			signatureBase: $signature_base,
			signature: $signature,
		);
	}

	/**
	 * Decodes a U2F JS API u2f.RegisterResponse
	 *
	 * @link https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-RegisterResponse
	 * @param RegisterResponse The u2f.RegisterResponse object obtained from the client JS API
	 * @throws Exception
	 */
	public static function decodeRegisterResponse(array $reg_response, string $rp_id): RegisterResponse {
		// return immediately if client errored
		if (array_key_exists('errorCode', $reg_response) && is_int($reg_response['errorCode']) && $reg_response['errorCode'] !== self::ERROR_CODE_OK) {
			throw new Exception('U2F client error', Exception::ERR_CLIENT_ERROR, client_error_code: $reg_response['errorCode']);
		}

		// validate input
		if (
			!array_key_exists('registrationData', $reg_response) || !is_string($reg_response['registrationData']) ||
			!array_key_exists('version', $reg_response) || !is_string($reg_response['version']) ||
			!array_key_exists('appId', $reg_response) || !is_string($reg_response['appId']) ||
			!array_key_exists('challenge', $reg_response) || !is_string($reg_response['challenge']) ||
			!array_key_exists('clientData', $reg_response) || !is_string($reg_response['clientData'])
		) {
			throw new Exception('Invalid RegisterResponse data', Exception::ERR_INVALID_DATA);
		}
		if ($reg_response['version'] !== self::VERSION_V2) {
			throw new Exception('RegisterResponse version not supported', Exception::ERR_VERSION_NOT_SUPPORTED);
		}
		if ($reg_response['appId'] !== $rp_id) {
			throw new Exception('Relying Party (RP) ID mismatch', Exception::ERR_RP_ID_MISMATCH);
		}

		// get client data from result
		$client_data_json = Base64Url::decode($reg_response['clientData']);
		if (!is_string($client_data_json)) {
			throw new Exception('Invalid RegisterResponse data: clientData not a valid base64url string', Exception::ERR_INVALID_DATA);
		}

		// get challenge from result
		$challenge = Base64Url::decode($reg_response['challenge']);
		if (!is_string($challenge)) {
			throw new Exception('Invalid RegisterResponse data: challenge not a valid base64url string', Exception::ERR_INVALID_DATA);
		}

		// decode and validate clientData
		$client_data = static::decodeClientDataJSON($client_data_json, $rp_id);

		// get client data hash
		$client_data_hash = hash('sha256', $client_data_json, true);

		if ($challenge !== $client_data->challenge) {
			throw new Exception('Challenge mismatch', Exception::ERR_CHALLENGE_MISMATCH);
		}

		// parse registration data
		$reg_data_raw = Base64Url::decode($reg_response['registrationData']);
		$offset = 1;

		// get public key
		$public_key_raw = substr($reg_data_raw, $offset, self::ECDSA_RAW_PUBLIC_KEY_LEN);
		$offset += self::ECDSA_RAW_PUBLIC_KEY_LEN;

		// get key handle length
		$key_handle_len = ord($reg_data_raw[$offset++]);

		// get key handle
		$key_handle_raw = substr($reg_data_raw, $offset, $key_handle_len);
		$offset += $key_handle_len;

		// get attestation certificate
		$att_cert = substr($reg_data_raw, $offset);
		try {
			$att_cert_len = Asn1::sequenceLength($att_cert);
		} catch (Asn1Exception $e) {
			throw new Exception('Attestation certificate (ASN.1 BER/DER SEQUENCE) is not valid', Exception::ERR_INVALID_DATA);
		}
		$att_cert = static::fixSignatureUnusedBits(substr($att_cert, 0, $att_cert_len));
		$offset += $att_cert_len;

		// get signature
		$signature = substr($reg_data_raw, $offset);

		// get signature base
		$signature_base  = "\0";                         // reserved
		$signature_base .= hash('sha256', $rp_id, true); // appId hash
		$signature_base .= $client_data_hash;            // clientData hash
		$signature_base .= $key_handle_raw;              // key handle
		$signature_base .= $public_key_raw;              // public key

		return new RegisterResponse(
			appId: $reg_response['appId'],
			version: $reg_response['version'],
			publicKey: $public_key_raw,
			keyHandle: $key_handle_raw,
			attCert: $att_cert,
			clientData: $client_data,
			clientDataHash: $client_data_hash,
			signatureBase: $signature_base,
			signature: $signature,
		);
	}

	/**
	 * Verifies a signature from the decodeRegisterResponse or
	 * decodeSignResponse methods.
	 *
	 * Validates challenge equality.
	 *
	 * @param SignResponse|RegisterResponse $response
	 * @param string $known_challenge Raw challenge data (not base64url encoded)
	 * @param ?string $public_key Known public key in PEM, DER or raw form (not hex-encoded). Required for SignResponse verification. For RegisterResponse we use the attestation certificate public key.
	 * @return bool Whether the response signature is correct
	 */
	public static function verify(SignResponse|RegisterResponse $response, string $known_challenge, ?string $public_key = null): bool {
		if ($response->clientData->challenge !== $known_challenge) return false;

		// Handle registration: Use attestation certificate as public key
		if ($public_key === null) {
			if ($response instanceof RegisterResponse) {
				$public_key = Pem::encode($response->attCert, Pem::LABEL_CERTIFICATE);
			} else {
				return false;
			}
		}

		return Ecdsa::verify($response->signatureBase, $response->signature, $public_key, OPENSSL_ALGO_SHA256, self::ECDSA_CURVE);
	}
}