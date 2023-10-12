<?php namespace Obie\Security\Webauthn;

class TpmClockInfo {
	function __construct(
		public int $clock,
		public int $resetCount,
		public int $restartCount,
		public bool $safe,
	) {}
}