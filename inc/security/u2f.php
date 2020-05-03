<?php namespace ZeroX\Security;
use ZeroX\Encoding\Base64Url;
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

	// https://w3c.github.io/webauthn/#sctn-authenticator-data
	const FLAG_UP   = 0b00000001; // User Presence (UP) result
	const FLAG_RFU1 = 0b00000010;
	const FLAG_UV   = 0b00000100; // User Verified (UV) result
	const FLAG_RFU2 = 0b00001000;
	const FLAG_RFU3 = 0b00010000;
	const FLAG_RFU4 = 0b00100000;
	const FLAG_RFU5 = 0b01000000;
	const FLAG_ED   = 0b10000000; // Extension data included (ED)

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
	public static function getJSRegisteredKeys(array $raw_key_handles, string $rp_id = null, array $transports = null): array {
		$keys = [];
		foreach ($raw_key_handles as $key_handle) {
			$key = [
				'version'   => 'U2F_V2',
				'keyHandle' => Base64Url::encode($key_handle)
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
	public static function decodeJSSignResponse(array $sign_response, string $rp_id): array {
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

		$client_data['challenge'] = Base64Url::decode($client_data['challenge']);

		// decode signature data
		$signature_data = Base64Url::decode($sign_response['signatureData']);

		// parse decoded signature data
		$flags = $signature_data[0];
		$counter_raw = substr($signature_data, 1, 4);
		$counter = (int)(unpack('N', $counter_raw)[1]);

		// get signature length
		try {
			$signature_length = self::asnSeqLen(substr($signature_data, 5));
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
		$signature_base  = hash('sha256', $rp_id, true);     // appId hash
		$signature_base .= $flags;                           // flags byte
		$signature_base .= $counter_raw;                     // counter
		$signature_base .= $sign_response['clientDataHash']; // clientData hash

		return [
			'success' => true,
			'keyHandle' => Base64Url::decode($sign_response['keyHandle']),
			'clientData' => $client_data,
			'clientDataHash' => hash('sha256', $client_data_raw, true),
			'signatureData' => $signature_data,
			'signatureBase' => $signature_base,
			'signature' => $signature,
			'counter' => $counter,
			'flags' => $flags
		];
	}
}