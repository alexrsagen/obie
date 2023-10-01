<?php namespace Obie\Encoding;
use Obie\Encoding\Exception\Asn1Exception;

class Asn1 {
	/**
	 * Get the length of a BER/DER-encoded ASN.1 SEQUENCE
	 *
	 * @param string $asn_seq ASN.1 SEQUENCE bytes (BER/DER encoded)
	 * @return int ASN.1 SEQUENCE length (including tag/type, length bytes)
	 * @throws Asn1Exception If not a valid ASN.1 SEQUENCE (BER/DER encoded)
	 */
	public static function sequenceLength(string $asn_seq): int {
		$asn_seq_len = strlen($asn_seq);
		// NOTE: "\x30": ASN.1 SEQUENCE (constructed)
		if ($asn_seq_len < 2 || $asn_seq[0] !== "\x30") {
			throw new Asn1Exception('Not an ASN.1 DER/BER sequence', Asn1Exception::ESEQUENCE_INVALID);
		}

		$len = ord($asn_seq[1]);
		if ($len & 0x80) {
			// ASN.1 BER/DER sequence is in long form
			$byte_count = $len & 0x7F;
			if ($asn_seq_len < $byte_count + 2) {
				throw new Asn1Exception('ASN.1 DER/BER sequence not fully represented', Asn1Exception::ESEQUENCE_INVALID);
			}
			$len = 0;
			for ($i = 0; $i < $byte_count; $i++) {
				$len = $len*0x100 + ord($asn_seq[$i + 2]);
			}
			$len += $byte_count; // add bytes for length itself
		}

		return $len + 2; // add 2 initial bytes: type and length
	}
}