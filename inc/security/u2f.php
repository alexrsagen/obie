<?php namespace ZeroX\Security;
use ZeroX\Encoding\Pem;
use ZeroX\Encoding\Base64Url;
use ZeroX\Security\Ecdsa;
if (!defined('IN_ZEROX')) {
	return;
}

class U2f {
	// https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-SignResponse
	const ERROR_CODE_OK = 0;
    const ERROR_CODE_OTHER_ERROR = 1;
    const ERROR_CODE_BAD_REQUEST = 2;
    const ERROR_CODE_CONFIGURATION_UNSUPPORTED = 3;
    const ERROR_CODE_DEVICE_INELIGIBLE = 4;
	const ERROR_CODE_TIMEOUT = 5;

	const FIX_CERTS = [
        '349bca1031f8c82c4ceca38b9cebf1a69df9fb3b94eed99eb3fb9aa3822d26e8',
        'dd574527df608e47ae45fbba75a2afdd5c20fd94a02419381813cd55a2a3398f',
        '1d8764f0f7cd1352df6150045c8f638e517270e8b5dda1c63ade9c2280240cae',
        'd0edc9a91a1677435a953390865d208c55b3183c6759c9b5a7ff494c322558eb',
        '6073c436dcd064a48127ddbf6032ac1a66fd59a0c24434f070d4e564c124c897',
        'ca993121846c464d666096d35f13bf44c1b05af205f9b4a1e00cf6cc10c5e511'
	];

	protected static function fixSignatureUnusedBits(string $cert) {
		if(in_array(hash('sha256', $cert), self::FIX_CERTS, true)) {
            $cert[strlen($cert) - 257] = "\0";
        }
        return $cert;
	}

	protected static function asnSeqLen(string $asn_seq) {
		$asn_seq_len = strlen($asn_seq);
		if ($asn_seq_len < 2 || $asn_seq[0] !== '0') {
			throw new \Exception('Not an ASN/DER sequence');
		}

		$len = ord($asn_seq[1]);
		if ($len & 0x80) {
			// asn/der sequence is in long form
			$byte_count = $len & 0x7F;
			if ($asn_seq_len < $byte_count + 2) {
				throw new \Exception('ASN/DER sequence not fully represented');
			}
			$len = 0;
			for ($i = 0; $i < $byte_count; $i++) {
				$len = $len*0x100 + ord($asn_seq[$i + 2]);
			}
			$len += $byte_count; // add bytes for length itself
		}

		return $len + 2; // add 2 initial bytes: type and length
	}

	/**
	 * Creates a U2F JS API Array<u2f.RegisteredKey>
	 *
	 * @link https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-RegisteredKey
	 * @param array $raw_key_handles - Array containing raw key handle strings
	 * @param string $rp_id - The application id that the RP would like to assert for this key handle, if it's distinct from the application id for the overall request. (Ordinarily this will be omitted.)
	 * @param array $transports - The transport(s) this token supports, if known by the RP.
	 */
	public static function buildRegisteredKeys(array $raw_key_handles, string $rp_id = null, array $transports = null): array {
		$keys = [];
		foreach ($raw_key_handles as $key_handle_raw) {
			$key = [
				'version'   => 'U2F_V2',
				'keyHandle' => Base64Url::encode($key_handle_raw)
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
	 * Decodes a U2F JS API u2f.SignResponse
	 *
	 * @link https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-javascript-api-v1.2-ps-20170411.html#idl-def-SignResponse
	 * @param array $sign_response - The u2f.SignResponse object obtained from the client JS API
	 */
	public static function decodeSignResponse(array $sign_response, string $rp_id): array {
		// return immediately if client errored
		if (array_key_exists('errorCode', $sign_response)) {
			return [
				'success' => false,
				'error' => 'client_error',
				'error_code' => $sign_response['errorCode']
			];
		}

		// validate input
		if (
			!array_key_exists('keyHandle', $sign_response) ||
			!array_key_exists('clientData', $sign_response) ||
			!array_key_exists('signatureData', $sign_response)
		) {
			return [
				'success' => false,
				'error' => 'invalid_data'
			];
		}

		// get client data from result
		$client_data_raw = Base64Url::decode($sign_response['clientData']);
		try {
			$client_data = json_decode($client_data_raw, true);
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => 'invalid_data'
			];
		}

		// validate clientData
		// https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-raw-message-formats-v1.2-ps-20170411.html#idl-def-ClientData
		if (
			!array_key_exists('typ', $client_data) ||
			!array_key_exists('challenge', $client_data) ||
			!array_key_exists('origin', $client_data)
		) {
			return [
				'success' => false,
				'error' => 'invalid_client_data'
			];
		}

		if ($client_data['origin'] !== $rp_id) {
			return [
				'success' => false,
				'error' => 'rp_id_mismatch'
			];
		}

		// get client data hash
		$client_data_hash = hash('sha256', $client_data_raw, true);

		// decode challenge
		$client_data['challenge'] = Base64Url::decode($client_data['challenge']);

		// decode signature data
		$signature_data = Base64Url::decode($sign_response['signatureData']);

		// parse decoded signature data
		$flags = $signature_data[0];
		$counter_raw = substr($signature_data, 1, 4);
		$counter = (int)(unpack('N', $counter_raw)[1]);

		// get signature length
		try {
			$signature_length = static::asnSeqLen(substr($signature_data, 5));
		} catch (\Exception $e) {
			// invalid asn/der sequence
			return [
				'success' => false,
				'error' => 'invalid_signature_length'
			];
		}

		// get signature
		$signature = substr($signature_data, 5, $signature_length);

		// verify raw signature data length
		if (strlen($signature_data) > 5 + $signature_length) {
			return [
				'success' => false,
				'error' => 'invalid_signature_length'
			];
		}

		// get signature base
		$signature_base  = hash('sha256', $rp_id, true); // appId hash
		$signature_base .= $flags;                       // flags byte
		$signature_base .= $counter_raw;                 // counter
		$signature_base .= $client_data_hash;            // clientData hash

		return [
			'success' => true,
			'keyHandle' => Base64Url::decode($sign_response['keyHandle']),
			'clientData' => $client_data,
			'clientDataHash' => $client_data_hash,
			'signatureData' => $signature_data,
			'signatureBase' => $signature_base,
			'signature' => $signature,
			'counter' => $counter,
			'flags' => $flags
		];
	}

	public static function decodeRegisterResponse(array $reg_response, string $rp_id) {
		// return immediately if client errored
		if (array_key_exists('errorCode', $reg_response)) {
			return [
				'success' => false,
				'error' => 'client_error',
				'error_code' => $reg_response['errorCode']
			];
		}

		// validate input
		if (
			!array_key_exists('registrationData', $reg_response) ||
			!array_key_exists('version', $reg_response) ||
			!array_key_exists('appId', $reg_response) ||
			!array_key_exists('challenge', $reg_response) ||
			!array_key_exists('clientData', $reg_response)
		) {
			return [
				'success' => false,
				'error' => 'invalid_data'
			];
		}

		if ($reg_response['version'] !== 'U2F_V2') {
			return [
				'success' => false,
				'error' => 'version_not_supported'
			];
		}

		if ($reg_response['appId'] !== $rp_id) {
			return [
				'success' => false,
				'error' => 'rp_id_mismatch'
			];
		}

		// get client data from result
		$client_data_raw = Base64Url::decode($reg_response['clientData']);
		try {
			$client_data = json_decode($client_data_raw, true);
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => 'invalid_data'
			];
		}

		// validate clientData
		// https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-raw-message-formats-v1.2-ps-20170411.html#idl-def-ClientData
		if (
			!array_key_exists('typ', $client_data) ||
			!array_key_exists('challenge', $client_data) ||
			!array_key_exists('origin', $client_data)
		) {
			return [
				'success' => false,
				'error' => 'invalid_client_data'
			];
		}

		if ($client_data['origin'] !== $rp_id) {
			return [
				'success' => false,
				'error' => 'rp_id_mismatch'
			];
		}

		// get client data hash
		$client_data_hash = hash('sha256', $client_data_raw, true);

		// decode challenges
		$reg_response['challenge'] = Base64Url::decode($reg_response['challenge']);
		$client_data['challenge'] = Base64Url::decode($client_data['challenge']);

		if ($reg_response['challenge'] !== $client_data['challenge']) {
			return [
				'success' => false,
				'error' => 'challenge_mismatch'
			];
		}

		// parse registration data
		$reg_data_raw = Base64Url::decode($reg_response['registrationData']);
		$offset = 1;

		// get public key
		$public_key_raw = substr($reg_data_raw, $offset, Ecdsa::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 1);
		$offset += Ecdsa::PUBKEYLEN_SECP256R1_UNCOMPRESSED + 1;

		// get key handle length
		$key_handle_len = ord($reg_data_raw[$offset++]);

		// get key handle
		$key_handle_raw = substr($reg_data_raw, $offset, $key_handle_len);
		$offset += $key_handle_len;

		// get attestation certificate
		$att_cert = substr($reg_data_raw, $offset);
		$att_cert_len = static::asnSeqLen($att_cert);
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

		return [
			'success' => true,
			'appId' => $reg_response['appId'],
			'version' => $reg_response['version'],
			'signatureBase' => $signature_base,
			'publicKey' => $public_key_raw,
			'keyHandle' => $key_handle_raw,
			'attCert' => $att_cert,
			'signature' => $signature,
			'clientData' => $client_data,
			'clientDataHash' => $client_data_hash
		];
	}

	/**
	 * Verifies a signature from the decodeRegisterResponse or
	 * decodeSignResponse methods.
	 *
	 * Validates challenge equality.
	 *
	 * @param array $response
	 * @param string $challenge
	 * @return bool
	 */
	public static function verify(array $response, string $known_challenge, string $public_key = null): bool {
		if (
			!array_key_exists('success', $response) ||
			!$response['success'] ||
			!array_key_exists('signature', $response) ||
			!array_key_exists('signatureBase', $response) ||
			!array_key_exists('clientData', $response) ||
			!array_key_exists('challenge', $response['clientData']) ||
			$response['clientData']['challenge'] !== $known_challenge
		) return false;

		// Handle registration: Use attestation certificate as public key
		if ($public_key === null) {
			if (!array_key_exists('attCert', $response)) return false;
			$public_key = Pem::encode($response['attCert'], Pem::LABEL_CERTIFICATE);
		}

		return Ecdsa::verify($response['signatureBase'], $response['signature'], $public_key, OPENSSL_ALGO_SHA256);
	}
}