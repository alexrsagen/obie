<?php namespace Obie\Smtp;

class Connection {
	function __construct(
		protected string $server,
		protected int $port = 587,
		protected string $username,
		protected string $password,
		protected string $ehlo_hostname = 'localhost',
		protected bool $tls = true,
		protected bool $starttls = true,
		protected int $stream_crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
		protected int $connection_timeout_seconds = 30,
		protected int $response_timeout_seconds = 10,
	) {}
}