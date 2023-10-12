<?php namespace Obie\Security\Webauthn;

class TpmCertInfo {
	const TPM_GENERATED_VALUE = 0xFF544347; // 0xFF 'TCG' (FF 54 43 47)

	// https://trustedcomputinggroup.org/wp-content/uploads/TCG_TPM2_r1p59_Part2_Structures_pub.pdf
	const TPM_ST_ATTEST_NV            = 0x8014;
	const TPM_ST_ATTEST_COMMAND_AUDIT = 0x8015;
	const TPM_ST_ATTEST_SESSION_AUDIT = 0x8016;
	const TPM_ST_ATTEST_CERTIFY       = 0x8017;
	const TPM_ST_ATTEST_QUOTE         = 0x8018;
	const TPM_ST_ATTEST_TIME          = 0x8019;
	const TPM_ST_ATTEST_CREATION      = 0x801A;
	const TPM_ST_ATTEST_NV_DIGEST     = 0x801C;

	function __construct(
		public int $magic,
		public int $type,
		public string $qualifiedSignerHashType,
		public string $qualifiedSigner,
		public string $extraData,
		public TpmClockInfo $clockInfo,
		public int $firmwareVersion,
		public int $nameHashType,
		public string $name,
		public string $qualifiedNameHashType,
		public string $qualifiedName,
	) {}
}