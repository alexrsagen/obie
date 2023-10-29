<?php namespace Obie\Encoding;
use Obie\Encoding\Exception\Asn1Exception;

class Asn1 {
	/**
	 * Get the length of a BER/DER-encoded ASN.1 SEQUENCE
	 *
	 * @param string $input ASN.1 SEQUENCE bytes (BER/DER encoded)
	 * @return int ASN.1 SEQUENCE length (including tag/type, length bytes)
	 * @throws Asn1Exception If not a valid ASN.1 SEQUENCE (BER/DER encoded)
	 */
	public static function sequenceLength(string $input): int {
		$asn_seq_len = strlen($input);
		// NOTE: "\x30": ASN.1 SEQUENCE (constructed)
		if ($asn_seq_len < 2 || $input[0] !== "\x30") {
			throw new Asn1Exception('Not an ASN.1 DER/BER sequence', Asn1Exception::ESEQUENCE_INVALID);
		}

		$len = ord($input[1]);
		if ($len & 0x80) {
			// ASN.1 BER/DER sequence is in long form
			$byte_count = $len & 0x7F;
			if ($asn_seq_len < $byte_count + 2) {
				throw new Asn1Exception('ASN.1 DER/BER sequence not fully represented', Asn1Exception::ESEQUENCE_INVALID);
			}
			$len = 0;
			for ($i = 0; $i < $byte_count; $i++) {
				$len = $len*0x100 + ord($input[$i + 2]);
			}
			$len += $byte_count; // add bytes for length itself
		}

		return $len + 2; // add 2 initial bytes: type and length
	}

	public static function isSequence(string $input): bool {
		try {
			$len = Asn1::sequenceLength($input);
			return strlen($input) >= $len;
		} catch (Asn1Exception $e) {
			return false;
		}
	}
}