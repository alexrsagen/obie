<?php namespace ZeroX;
if (!defined('IN_ZEROX')) {
	return;
}

class Util {
	public static function realIP(bool $pack = false) {
		$ip = $_SERVER['REMOTE_ADDR'];
		if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if ($pack) {
			return inet_pton($ip);
		}
		return $ip;
	}

	public static function randomString(int $length, string $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789') {
		$random_string = '';
		for ($i = 0; $i < $length; $i++) {
			$random_string .= $charset[random_int(0, strlen($charset) - 1)];
		}
		return $random_string;
	}

	public static function UUIDToBin(string $uuid) {
		$uuid = hex2bin(str_replace(array('-', '{', '}'), '', $uuid));
		if (!$uuid || strlen($uuid) !== 16) {
			throw new \Exception('Invalid UUID');
		}
		return $uuid;
	}

	public static function binToUUID(string $uuid, bool $brackets = false) {
		$uuid = bin2hex($uuid);
		return ($brackets ? '{' : '') . substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12) . ($brackets ? '}' : '');
	}

	public static function formatBytes(int $bytes) {
		if ($bytes < 1024) { // bytes (< 1024 b)
			return rtrim(rtrim(str_replace(',', '', number_format(floor($bytes * 100) / 100, 2)), '0'), '.') . ' byte';
		} elseif ($bytes < 1048576) { // kibibytes (< 1048576 b)
			return rtrim(rtrim(str_replace(',', '', number_format(floor(($bytes / 1024) * 100) / 100, 2)), '0'), '.') . ' kB';
		} elseif ($bytes < 1073741824) { // mebibytes (< 1073741824 b)
			return rtrim(rtrim(str_replace(',', '', number_format(floor(($bytes / 1048576) * 100) / 100, 2)), '0'), '.') . ' MB';
		} else { // gibibytes
			return rtrim(rtrim(str_replace(',', '', number_format(floor(($bytes / 1073741824) * 100) / 100, 2)), '0'), '.') . ' GB';
		}
	}

	public static function formatTime($input) {
		if (is_string($input)) {
			$time = strtotime($input);
		} elseif ($input instanceof \DateTime) {
			$time = $input->getTimestamp();
		} else {
			throw new \InvalidArgumentException('Input must be a string or DateTime object');
		}
		$now = time();

		$prefix = '';
		$suffix = '';
		$unit = '';
		if ($time > $now) {
			$prefix = 'in ';
		} else {
			$suffix = ' ago';
		}

		if (date('Y', $now) === date('Y', $time)) {
			if (date('M', $now) === date('M', $time)) {
				if (date('j', $now) === date('j', $time)) {
					if (date('H', $now) === date('H', $time)) {
						if (date('i', $now) === date('i', $time)) {
							if (date('s', $now) === date('s', $time)) {
								// Same second
								return 'just now';
							}
							// Same minute
							$offset = (int)date('s', $now) - (int)date('s', $time);
							$offset = ($offset < 0 ? -$offset : $offset);
							return sprintf('%s%d second%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
						}
						// Same hour
						$offset = (int)date('i', $now) - (int)date('i', $time);
						$offset = ($offset < 0 ? -$offset : $offset);
						return sprintf('%s%d minute%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
					}
					// Same day
					$offset = (int)date('G', $now) - (int)date('G', $time);
					$offset = ($offset < 0 ? -$offset : $offset);
					return sprintf('%s%d hour%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
				}
				// Same month
				$offset = (int)date('j', $now) - (int)date('j', $time);
				$offset = ($offset < 0 ? -$offset : $offset);
				return sprintf('%s%d day%s%s', $prefix, $offset, ($offset > 1 ? 's' : ''), $suffix);
			}
			// Same year
			return 'on ' . date('j M', $time);
		}
		return 'on ' . date('j M Y', $time);
	}

	public static function sendMail($recipients, string $subject, string $body, bool $is_html = false) {
		if (is_string($recipients)) {
			$recipients = [$recipients];
		}
		if (!is_array($recipients)) {
			throw new \TypeError('Recipients must be string or array');
		}
		if (!Config::getGlobal()->get('mail', 'enable')) {
			throw new \Exception('Mail is not enabled in the server configuration');
		}

		$content_type = 'text/plain';
		if ($is_html) {
			$content_type = 'text/html';
		}

		$transport = new \Swift_SmtpTransport(Config::getGlobal()->get('mail', 'host'), Config::getGlobal()->get('mail', 'port'), Config::getGlobal()->get('mail', 'security'));
		$transport->setUsername(Config::getGlobal()->get('mail', 'username'));
		$transport->setPassword(Config::getGlobal()->get('mail', 'password'));

		$mailer = new \Swift_Mailer($transport);

		$message = new \Swift_Message($subject);
		$message->setFrom([Config::getGlobal()->get('mail', 'from_email') => Config::getGlobal()->get('mail', 'from_name')]);
		$message->setTo($recipients);
		$message->setBody($body, $content_type);

		try {
			return $mailer->send($message);
		} catch (\Exception $e) {
			return false;
		}
	}

	protected static $server_virtual_id = 0;

	public static function setServerVirtualID(int $server_virtual_id) {
		self::$server_virtual_id = $server_virtual_id;
	}

	public static function genVirtualID(bool $short = false) {
		$id_part_time = (string)round(microtime(true) * 1000);
		$id_part_svid = (string)static::$server_virtual_id;
		$id_part_prng = random_bytes(32);

		$id_hash_ctx = hash_init('sha256');
		hash_update($id_hash_ctx, $id_part_time);
		hash_update($id_hash_ctx, $id_part_svid);
		hash_update($id_hash_ctx, $id_part_prng);
		$id_hex = hash_final($id_hash_ctx, false);

		$id_l_gmp = gmp_init(substr($id_hex, 0, 16), 16);
		$id_r_gmp = gmp_init(substr($id_hex, 16, 16), 16);
		$id_gmp = gmp_xor($id_l_gmp, $id_r_gmp);

		if ($short) {
			$id_hex = gmp_strval($id_gmp, 16);
			$id_l_gmp = gmp_init(substr($id_hex, 0, 8), 16);
			$id_r_gmp = gmp_init(substr($id_hex, 8, 8), 16);
			$id_gmp = gmp_xor($id_l_gmp, $id_r_gmp);
		}

		return gmp_intval($id_gmp);
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		switch ($errno){
			case E_ERROR: // 1
				$typestr = 'E_ERROR'; break;
			case E_WARNING: // 2
				$typestr = 'E_WARNING'; break;
			case E_PARSE: // 4
				$typestr = 'E_PARSE'; break;
			case E_NOTICE: // 8
				$typestr = 'E_NOTICE'; break;
			case E_CORE_ERROR: // 16
				$typestr = 'E_CORE_ERROR'; break;
			case E_CORE_WARNING: // 32
				$typestr = 'E_CORE_WARNING'; break;
			case E_COMPILE_ERROR: // 64
				$typestr = 'E_COMPILE_ERROR'; break;
			case E_CORE_WARNING: // 128
				$typestr = 'E_COMPILE_WARNING'; break;
			case E_USER_ERROR: // 256
				$typestr = 'E_USER_ERROR'; break;
			case E_USER_WARNING: // 512
				$typestr = 'E_USER_WARNING'; break;
			case E_USER_NOTICE: // 1024
				$typestr = 'E_USER_NOTICE'; break;
			case E_STRICT: // 2048
				$typestr = 'E_STRICT'; break;
			case E_RECOVERABLE_ERROR: // 4096
				$typestr = 'E_RECOVERABLE_ERROR'; break;
			case E_DEPRECATED: // 8192
				$typestr = 'E_DEPRECATED'; break;
			case E_USER_DEPRECATED: // 16384
				$typestr = 'E_USER_DEPRECATED'; break;
		}

		$error_plain = $typestr . ': ' . $errstr . ' in ' . $errfile . ' on line ' . $errline;
		$error_html = "<p>A fatal error occurred at " . date(DATE_ATOM) . ".</p><p><b>" . $typestr . ": </b>" . $errstr . " in <b>" . $errfile . "</b> on line <b>" . $errline . "</b></p>";
		error_log($error_plain);

		if (Config::getGlobal()->get('errors', 'mail')) {
			static::sendMail(Config::getGlobal()->get('errors', 'mail_address'), 'Internal server error on ' . Config::getGlobal()->get('site_name'), $error_html, true);
		}

		if (Config::getGlobal()->get('errors', 'dump')) {
			Router::sendResponse($error_html);
		} else {
			Router::sendResponse('Something went wrong while processing the request.', Router::CONTENT_TYPE_TEXT);
		}
		exit;
	}

	public static function shutdownHandler() {
		// Handle fatal errors
		if (Config::getGlobal()->get('errors', 'handle')) {
			$error = error_get_last();
			if ($error && ($error['type'] & (E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))){
				Util::errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
			}
		}
	}
}
