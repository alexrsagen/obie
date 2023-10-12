<?php namespace Obie\ApiClients;
use Obie\Validation\SimpleValidator;
use Obie\Encoding\Uuid;
use Obie\Encoding\Jwt;
use Obie\Encoding\Pem;
use Obie\Http\Request;

/**
 * FIDO Metadata Service client
 *
 * @link https://fidoalliance.org/metadata/
 */
class FidoMds {
	function __construct(
		protected bool $debug = false,
		protected ?string $blob = null,
		protected ?\OpenSSLCertificate $root_cert = null,
	) {}

	public function getDebug(): bool {
		return $this->debug;
	}

	public function setDebug(bool $debug): static {
		$this->debug = $debug;
		return $this;
	}

	protected function getBlob(): ?string {
		if ($this->blob === null) {
			$res = Request::get('https://mds.fidoalliance.org')->perform(debug: $this->getDebug());
			if ($res->getCode() === 200) {
				$this->blob = $res->getRawBody();
			}
		}
		return $this->blob;
	}

	protected function getRootCert(): ?\OpenSSLCertificate {
		if ($this->root_cert === null) {
			$res = Request::get('https://secure.globalsign.com/cacert/root-r3.crt')->perform();
			if ($res->getCode() !== 200) return null;
			$der = $res->getRawBody();
			$pem = Pem::encode($der, Pem::LABEL_CERTIFICATE);
			$cert = openssl_x509_read($pem);
			if (!$cert) return null;
			$this->root_cert = $cert;
		}
		return $this->root_cert;
	}

	protected mixed $blob_data = null;
	protected function getBlobData(): mixed {
		if ($this->blob_data === null) {
			if (!($root_cert = $this->getRootCert())) return null;
			if (!($blob = $this->getBlob())) return null;
			$this->blob_data = Jwt::decode($blob, $root_cert, Jwt::ALG_RS256, key_is_x5c_root: true);
		}
		return $this->blob_data;
	}

	/**
	 * Get the authenticator metadata for a given AAGUID
	 *
	 * @param string $aaguid - Authenticator Attestation GUID - https://www.w3.org/TR/webauthn-2/#aaguid
	 * @return array|null https://fidoalliance.org/specs/mds/fido-metadata-statement-v3.0-ps-20210518.html#metadata-keys
	 */
	public function getMetadata(string $aaguid): ?array {
		if (!SimpleValidator::isValid($aaguid, SimpleValidator::TYPE_UUID)) {
			$aaguid = Uuid::encode($aaguid);
		}

		// spec: https://fidoalliance.org/specs/mds/fido-metadata-service-v3.0-ps-20210518.html#dictdef-metadatablobpayload
		$blob_data = $this->getBlobData();
		if (!is_array($blob_data) || !array_key_exists('entries', $blob_data) || !is_array($blob_data['entries'])) return null;
		file_put_contents('mds.json', \Obie\Encoding\Json::encode($blob_data)); exit;

		foreach ($blob_data['entries'] as $entry) {
			if (!array_key_exists('aaguid', $entry) || !array_key_exists('metadataStatement', $entry)) continue;
			if ($entry['aaguid'] === $aaguid) return $entry['metadataStatement'];
		}
		return null;
	}
}