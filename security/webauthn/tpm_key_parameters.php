<?php namespace Obie\Security\Webauthn;

use Sop\CryptoTypes\AlgorithmIdentifier\Asymmetric\ECPublicKeyAlgorithmIdentifier;

class TpmKeyParameters {
	// https://trustedcomputinggroup.org/wp-content/uploads/TCG_TPM2_r1p59_Part2_Structures_pub.pdf
	const TPM_ALG_ERROR          = 0x0000; // should not occur
	const TPM_ALG_RSA            = 0x0001; // [IETF RFC 8017]: the RSA algorithm
	const TPM_ALG_TDES           = 0x0003; // [ISO/IEC 18033-3]: block cipher with various key sizes (Triple Data Encryption Algorithm, commonly called Triple Data Encryption Standard)
	const TPM_ALG_SHA            = 0x0004; // [ISO/IEC 10118-3]: the SHA1 algorithm
	const TPM_ALG_SHA1           = 0x0004; // [ISO/IEC 10118-3]: redefinition for documentation consistency
	const TPM_ALG_HMAC           = 0x0005; // [ISO/IEC 9797-2]: Hash Message Authentication Code (HMAC) algorithm
	const TPM_ALG_AES            = 0x0006; // [ISO/IEC 18033-3]: the AES algorithm with various key sizes
	const TPM_ALG_MGF1           = 0x0007; // [IEEE Std 1363-2000, IEEE Std 1363a-2004]: hash-based mask-generation function
	const TPM_ALG_KEYEDHASH      = 0x0008; // [TCG TPM 2.0 library specification]: an object type that may use XOR for encryption or an HMAC for signing and may also refer to a data object that is neither signing nor encrypting
	const TPM_ALG_XOR            = 0x000A; // [TCG TPM 2.0 library specification]: the XOR encryption algorithm
	const TPM_ALG_SHA256         = 0x000B; // [ISO/IEC 10118-3]: the SHA 256 algorithm
	const TPM_ALG_SHA384         = 0x000C; // [ISO/IEC 10118-3]: the SHA 384 algorithm
	const TPM_ALG_SHA512         = 0x000D; // [ISO/IEC 10118-3]: the SHA 512 algorithm
	const TPM_ALG_NULL           = 0x0010; // [TCG TPM 2.0 library specification]: Null algorithm
	const TPM_ALG_SM3_256        = 0x0012; // [GM/T 0004-2012]: SM3 hash algorithm
	const TPM_ALG_SM4            = 0x0013; // [GM/T 0002-2012]: SM4 symmetric block cipher
	const TPM_ALG_RSASSA         = 0x0014; // [IETF RFC 8017]: a signature algorithm defined in section 8.2 (RSASSA-PKCS1-v1_5)
	const TPM_ALG_RSAES          = 0x0015; // [IETF RFC 8017]: a padding algorithm defined in section 7.2 (RSAES-PKCS1-v1_5)
	const TPM_ALG_RSAPSS         = 0x0016; // [IETF RFC 8017]: a signature algorithm defined in section 8.1 (RSASSA-PSS)
	const TPM_ALG_OAEP           = 0x0017; // [IETF RFC 8017]: a padding algorithm defined in section 7.1 (RSAES_OAEP)
	const TPM_ALG_ECDSA          = 0x0018; // [ISO/IEC 14888-3]: signature algorithm using elliptic curve cryptography (ECC)
	const TPM_ALG_ECDH           = 0x0019; // [NIST SP800-56A]: secret sharing using ECC
	const TPM_ALG_ECDAA          = 0x001A; // [TCG TPM 2.0 library specification]: elliptic-curve based, anonymous signing scheme
	const TPM_ALG_SM2            = 0x001B; // [GM/T 0003.1–2012], [GM/T 0003.2–2012], [GM/T 0003.3–2012], [GM/T 0003.5–2012]: SM2 – depending on context, either an elliptic-curve based, signature algorithm or a key exchange protocol
	const TPM_ALG_ECSCHNORR      = 0x001C; // [TCG TPM 2.0 library specification]: elliptic-curve based Schnorr signature
	const TPM_ALG_ECMQV          = 0x001D; // [NIST SP800-56A]: two-phase elliptic-curve key exchange – C(2, 2, ECC MQV) section 6.1.1.4
	const TPM_ALG_KDF1_SP800_56A = 0x0020; // [NIST SP800-56A]: concatenation key derivation function (approved alternative 1) section 5.8.1
	const TPM_ALG_KDF2           = 0x0021; // [IEEE Std 1363a-2004]: key derivation function KDF2 section 13.2
	const TPM_ALG_KDF1_SP800_108 = 0x0022; // [NIST SP800-108]: a key derivation method
	const TPM_ALG_ECC            = 0x0023; // [ISO/IEC 15946-1]: prime field ECC
	const TPM_ALG_SYMCIPHER      = 0x0025; // [TCG TPM 2.0 library specification]: the object type for a symmetric block cipher
	const TPM_ALG_CAMELLIA       = 0x0026; // [ISO/IEC 18033-3]: Camellia is symmetric block cipher. The Camellia algorithm with various key sizes
	const TPM_ALG_SHA3_256       = 0x0027; // [NIST PUB FIPS 202]: Hash algorithm producing a 256-bit digest
	const TPM_ALG_SHA3_384       = 0x0028; // [NIST PUB FIPS 202]: Hash algorithm producing a 384-bit digest
	const TPM_ALG_SHA3_512       = 0x0029; // [NIST PUB FIPS 202]: Hash algorithm producing a 512-bit digest
	const TPM_ALG_CTR            = 0x0040; // [ISO/IEC 10116]: Counter mode – if implemented, all symmetric block ciphers (S type) implemented shall be capable of using this mode.
	const TPM_ALG_OFB            = 0x0041; // [ISO/IEC 10116]: Output Feedback mode – if implemented, all symmetric block ciphers (S type) implemented shall be capable of using this mode.
	const TPM_ALG_CBC            = 0x0042; // [ISO/IEC 10116]: Cipher Block Chaining mode – if implemented, all symmetric block ciphers (S type) implemented shall be capable of using this mode.
	const TPM_ALG_CFB            = 0x0043; // [ISO/IEC 10116]: Cipher Feedback mode – if implemented, all symmetric block ciphers (S type) implemented shall be capable of using this mode.
	const TPM_ALG_ECB            = 0x0044; // [ISO/IEC 10116]: Electronic Codebook mode – if implemented, all symmetric block ciphers (S type) implemented shall be capable of using this mode.

	const TPM_ECC_NONE      = 0x0000;
	const TPM_ECC_NIST_P192 = 0x0001;
	const TPM_ECC_NIST_P224 = 0x0002;
	const TPM_ECC_NIST_P256 = 0x0003;
	const TPM_ECC_NIST_P384 = 0x0004;
	const TPM_ECC_NIST_P521 = 0x0005;
	const TPM_ECC_BN_P256   = 0x0010; // curve to support ECDAA
	const TPM_ECC_BN_P638   = 0x0011; // curve to support ECDAA
	const TPM_ECC_SM2_P256  = 0x0020;

	function __construct(
		public ?int $symmetric = null, // TPM_ALG_...
		public ?int $scheme = null, // TPM_ALG_...
		public ?int $keyBits = null,
		public ?int $exponent = null,
		public ?int $curveId = null, // TPM_ECC_...
		public ?int $kdf = null, // TPM_ALG_...
	) {}

	public function curveOid(): ?string {
		return match ($this->curveId) {
			self::TPM_ECC_NIST_P192 => ECPublicKeyAlgorithmIdentifier::CURVE_PRIME192V1,
			self::TPM_ECC_NIST_P224 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP224R1,
			self::TPM_ECC_NIST_P256 => ECPublicKeyAlgorithmIdentifier::CURVE_PRIME256V1,
			self::TPM_ECC_NIST_P384 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP384R1,
			self::TPM_ECC_NIST_P521 => ECPublicKeyAlgorithmIdentifier::CURVE_SECP521R1,
			default => null,
		};
	}
}