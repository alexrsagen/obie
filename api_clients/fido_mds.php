<?php namespace Obie\ApiClients;
use \Obie\Http\Client;
use \Obie\Encoding\Uuid;
use \Obie\Encoding\Json;
use \Obie\Validation\SimpleValidator;

/**
 * FIDO Metadata Service client
 *
 * @link https://mds2.fidoalliance.org/help
 */
class FidoMds extends Client {
	protected $token = '';

	function __construct(string $token, bool $debug = false) {
		$this->token = $token;
		$this->setDebug($debug);
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

		$res = static::request(Client::METHOD_GET, 'https://mds2.fidoalliance.org/metadata/' . urlencode($aaguid) . '/', [
			'token' => $this->token
		], null);

		// Log errors
		if ($res->hasErrors()) {
			Log::error($res->getErrors(), ['prefix' => 'Error(s) encountered during FIDO MDS request: ']);
			return null;
		}

		// Return decoded data
		if ($res->getResponseCode() !== 200 || !$res->getData()) return null;
		return Json::decode(base64_decode($res->getData()));
	}
}