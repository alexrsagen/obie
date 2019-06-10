<?php namespace ZeroX\Security\TwoFactor;
use ZeroX\Util;
if (!defined('IN_ZEROX')) {
	return;
}

class U2f {
	private static function armor(string $public_key_raw) {
		return "-----BEGIN PUBLIC KEY-----\n" .
			base64_encode(
				hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') .
				$public_key_raw
			) . "\n-----END PUBLIC KEY-----";
	}

	private static function asnLen(string $asn_sequence) {
		$asn_sequence_length = strlen($asn_sequence);
		if ($asn_sequence_length < 2 || $asn_sequence[0] !== '0') {
			throw new \Exception('Not an ASN/DER sequence');
		}

		$asn_len = ord($asn_sequence[1]);
		if ($asn_len & 0x80) {
			// asn/der sequence is in long form
			$byte_count = $asn_len & 0x7F;

			if ($asn_sequence_length < $byte_count + 2) {
				throw new \Exception('ASN/DER sequence not fully represented');
			}

			$asn_len = 0;

			for ($i = 0; $i < $asn_sequence_length; $i++) {
				$asn_len = $asn_len * 0x100 + ord($asn_sequence[$i + 2]);
			}

			$asn_len += $asn_sequence_length;
		}

		return $asn_len + 2; // add 2 initial bytes: type and length
	}

	public static function genRegisteredKeys(array $key_handles, string $url) {
		return json_encode(array_map(function($key_handle) use($url) {
			return [
				'version' => 'U2F_V2',
				'appId' => $url,
				'keyHandle' => Util::urlSafeBase64Encode($key_handle)
			];
		}, $key_handles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	public static function parseResult(string $result_json) {
		// parse JSON-encoded client result
		try {
			$result = json_decode($result_json, true);
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => 'invalid_json_data'
			];
		}

		// return immediately if client errored
		if (array_key_exists('errorCode', $result)) {
			return [
				'success' => false,
				'error' => 'client_error',
				'error_code' => $result['errorCode']
			];
		}

		// ensure all required data is present in result
		if (
			!array_key_exists('keyHandle', $result) ||
			!array_key_exists('clientData', $result) ||
			!array_key_exists('signatureData', $result)
		) {
			return [
				'success' => false,
				'error' => 'invalid_json_data'
			];
		}

		// get client data from result
		$client_data_raw = Util::urlSafeBase64Decode($result['clientData']);
		try {
			$client_data = json_decode($client_data_raw, true);
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => 'invalid_json_data'
			];
		}

		// ensure all required data is present in client data
		if (!array_key_exists('challenge', $client_data)) {
			return [
				'success' => false,
				'error' => 'invalid_json_data'
			];
		}

		return [
			'success' => true,
			'key_handle' => Util::urlSafeBase64Decode($result['keyHandle']),
			'client_data_hash' => hash('sha256', $client_data_raw, true),
			'signature' => Util::urlSafeBase64Decode($result['signatureData']),
			'challenge' => Util::urlSafeBase64Decode($client_data['challenge']),
		];
	}

	public static function verify(array $client_data, string $url) {
		// armor public key if not already armored
		if (strpos($client_data['public_key'], "-----BEGIN PUBLIC KEY-----\n") !== 0) {
			$client_data['public_key'] = self::armor($client_data['public_key']);
		}

		// parse raw signature data
		$user_presence_flag = $client_data['signature'][0];
		$counter_raw = substr($client_data['signature'], 1, 4);
		$counter = (int)(unpack('N', $counter_raw)[1]);

		// get signature length
		try {
			$signature_length = self::asnLen(substr($client_data['signature'], 5));
		} catch (\Exception $e) {
			// invalid asn/der sequence
			return [
				'success' => false,
				'error' => 'invalid_signature_length'
			];
		}

		// get signature
		$signature = substr($client_data['signature'], 5, $signature_length);

		// verify raw signature data length
		if (strlen($client_data['signature']) > 5 + $signature_length) {
			return [
				'success' => false,
				'error' => 'invalid_signature_length'
			];
		}

		// get ECDSA signature base
		$signature_base  = hash('sha256', $url, true);       // appId hash
		$signature_base .= $user_presence_flag;              // user presence flag
		$signature_base .= $counter_raw;                     // counter
		$signature_base .= $client_data['client_data_hash']; // clientData hash

		// verify signature of signature base
		$signature_valid = openssl_verify($signature_base, $signature, $client_data['public_key'], OPENSSL_ALGO_SHA256) === 1;

		return [
			'success' => $signature_valid,
			'counter' => $counter
		];
	}
}
