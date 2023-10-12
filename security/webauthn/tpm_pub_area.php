<?php namespace Obie\Security\Webauthn;

use Obie\Encoding\Pem;
use Sop\CryptoTypes\Asymmetric\EC\ECPublicKey;
use Sop\CryptoTypes\Asymmetric\RSA\RSAPublicKey;

class TpmPubArea {
	// https://trustedcomputinggroup.org/wp-content/uploads/TCG_TPM2_r1p59_Part2_Structures_pub.pdf
	// TPMA_OBJECT bits                     31.....................9876543210
	//                     reserved bits =   ************    ****  **    *  *
	const OBJ_ATTR_FIXED_TPM             = 0b00000000000000000000000000000010;
	const OBJ_ATTR_ST_CLEAR              = 0b00000000000000000000000000000100;
	const OBJ_ATTR_FIXED_PARENT          = 0b00000000000000000000000000010000;
	const OBJ_ATTR_SENSITIVE_DATA_ORIGIN = 0b00000000000000000000000000100000;
	const OBJ_ATTR_USER_WITH_AUTH        = 0b00000000000000000000000001000000;
	const OBJ_ATTR_ADMIN_WITH_POLICY     = 0b00000000000000000000000010000000;
	const OBJ_ATTR_NO_DA                 = 0b00000000000000000000010000000000;
	const OBJ_ATTR_ENCRYPTED_DUPLICATION = 0b00000000000000000000100000000000;
	const OBJ_ATTR_RESTRICTED            = 0b00000000000000010000000000000000;
	const OBJ_ATTR_DECRYPT               = 0b00000000000000100000000000000000;
	const OBJ_ATTR_SIGN_OR_ENCRYPT       = 0b00000000000001000000000000000000;
	const OBJ_ATTR_X509_SIGN             = 0b00000000000010000000000000000000;

	function __construct(
		public int $type,
		public int $nameHashType,
		public int $objectAttributes,
		public string $authPolicy,
		public TpmKeyParameters $parameters,
		public string $unique,
	) {}

	public function toDER(): ?string {
		if ($this->type === TpmKeyParameters::TPM_ALG_RSA) {
			return (new RSAPublicKey($this->unique, $this->parameters->exponent))->toDER();
		} elseif ($this->type === TpmKeyParameters::TPM_ALG_ECC) {
			return (new ECPublicKey($this->unique, $this->parameters->curveOid()))->toDER();
		} else {
			return null;
		}
	}

	public function toPEM(): ?string {
		$der = $this->toDER();
		if (!$der) return null;
		return Pem::encode($der, Pem::LABEL_PUBLICKEY);
	}
}