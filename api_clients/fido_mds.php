<?php namespace Obie\ApiClients;
use \Obie\Encoding\Uuid;
use \Obie\Encoding\Json;
use \Obie\Validation\SimpleValidator;

/**
 * FIDO Metadata Service client
 *
 * @link https://mds2.fidoalliance.org/help
 */
class FidoMds {
	function __construct(
		protected string $token,
		protected bool $debug = false
	) {}

	public function getToken(): string {
		return $this->token;
	}

	public function getDebug(): bool {
		return $this->debug;
	}

	public function setToken(string $token): static {
		$this->token = $token;
		return $this;
	}

	public function setDebug(bool $debug): static {
		$this->debug = $debug;
		return $this;
	}

	/**
	 * Get the authenticator metadata for a given AAGUID
	 *
	 * @param string $aaguid - Authenticator Attestation GUID - https://www.w3.org/TR/webauthn-2/#aaguid
	 * @return array|null
	 */
	public function getMetadata(string $aaguid) {
		if (!SimpleValidator::isValid($aaguid, SimpleValidator::TYPE_UUID)) {
			$aaguid = Uuid::encode($aaguid);
		}

		$req = Request::get('https://mds2.fidoalliance.org/metadata/' . urlencode($aaguid) . '/')
			->setQuery(['token' => $this->token]);

		$res = $req->perform(debug: $this->getDebug());

		// Log errors
		if ($res->hasErrors()) {
			Log::error($res->getErrors(), ['prefix' => 'Error(s) encountered during FIDO MDS request: ']);
			return null;
		}

		// Return decoded data
		if ($res->getCode() !== 200 || !$res->getRawBody()) return null;
		return Json::decode(base64_decode($res->getRawBody()));
	}
}