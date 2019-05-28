<?php namespace ZeroX;
if (!defined('IN_ZEROX')) {
	exit;
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

	public static function randomString(int $length, string $charset = self::BASE62_ALPHABET) {
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

	// XXX: Changing these constants is not a good idea
	const BASE64URL_ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
	const BASE64_ALPHABET    = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
	const BASE62_ALPHABET    = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
	const BASE36_ALPHABET    = "abcdefghijklmnopqrstuvwxyz0123456789";
	const BASE16_ALPHABET    = "0123456789abcdef";

	public static function baseEncode(string $alphabet, int $input) {
		$input_gmp = gmp_init($input);
		$base_gmp = gmp_init(strlen($alphabet));
		$zero_gmp = gmp_init(0);
		$output = "";

		do {
			$offset = gmp_intval(gmp_mod($input_gmp, $base_gmp));
			$output = $alphabet[$offset] . $output;
			$input_gmp = gmp_div($input_gmp, $base_gmp);
		} while (gmp_cmp($input_gmp, $zero_gmp) > 0);

		return $output;
	}

	public static function baseDecode(string $alphabet, string $input) {
		$base_gmp = gmp_init(strlen($alphabet));
		$output_gmp = gmp_init(0);
		$len = strlen($input);

		if ($len > 11) {
			throw new \Exception("Too long input string");
		}

		for ($i = 0; $i < $len; $i++) {
			$offset = strpos($alphabet, $input[$i]);
			if ($offset === false) {
				throw new \Exception("Invalid input string");
			}
			$offset_gmp = gmp_init($offset);
			$output_gmp = gmp_add($output_gmp, gmp_mul($offset_gmp, gmp_pow($base_gmp, $len - $i - 1)));
		}

		return gmp_intval($output_gmp);
	}

	public static function urlSafeBase64Encode(string $input) {
		return str_replace(array('/', '+', '='), array('_', '-', ''), base64_encode($input));
	}

	public static function urlSafeBase64Decode(string $input) {
		return base64_decode(str_replace(array('_', '-'), array('/', '+'), $input));
	}

	public static function sendMail($recipients, string $subject, string $body, bool $is_html = false) {
		if (is_string($recipients)) {
			$recipients = [$recipients];
		}
		if (!is_array($recipients)) {
			throw new \TypeError('Recipients must be string or array');
		}
		if (!Config::get('mail', 'enable')) {
			throw new \Exception('Mail is not enabled in the server configuration');
		}

		$content_type = 'text/plain';
		if ($is_html) {
			$content_type = 'text/html';
		}

		$transport = new \Swift_SmtpTransport(Config::get('mail', 'host'), Config::get('mail', 'port'), Config::get('mail', 'security'));
		$transport->setUsername(Config::get('mail', 'username'));
		$transport->setPassword(Config::get('mail', 'password'));

		$mailer = new \Swift_Mailer($transport);

		$message = new \Swift_Message($subject);
		$message->setFrom([Config::get('mail', 'from_email') => Config::get('mail', 'from_name')]);
		$message->setTo($recipients);
		$message->setBody($body, $content_type);

		try {
			return $mailer->send($message);
		} catch (\Exception $e) {
			return false;
		}
	}

	public static function genVirtualID(bool $short = false) {
		$upload_id_part_time = (string)round(microtime(true) * 1000);
		$upload_id_part_svid = (string)self::$server_virtual_id;
		$upload_id_part_prng = random_bytes(32);

		$upload_id_hash_ctx = hash_init('sha256');
		hash_update($upload_id_hash_ctx, $upload_id_part_time);
		hash_update($upload_id_hash_ctx, $upload_id_part_svid);
		hash_update($upload_id_hash_ctx, $upload_id_part_prng);
		$upload_id_hex = hash_final($upload_id_hash_ctx, false);

		$upload_id_l_gmp = gmp_init(substr($upload_id_hex, 0, 16), 16);
		$upload_id_r_gmp = gmp_init(substr($upload_id_hex, 16, 16), 16);
		$upload_id_gmp = gmp_xor($upload_id_l_gmp, $upload_id_r_gmp);

		if ($short) {
			$upload_id_hex = gmp_strval($upload_id_gmp, 16);
			$upload_id_l_gmp = gmp_init(substr($upload_id_hex, 0, 8), 16);
			$upload_id_r_gmp = gmp_init(substr($upload_id_hex, 8, 8), 16);
			$upload_id_gmp = gmp_xor($upload_id_l_gmp, $upload_id_r_gmp);
		}

		return gmp_intval($upload_id_gmp);
	}

	public static function outputError(int $code, User $user = null, string $error_message = null, string $error_title = null, bool $close = false) {
		$title = $error_title ?? (array_key_exists($code, Router::HTTP_STATUSTEXT) ? Router::HTTP_STATUSTEXT[$code] : 'Error');
		Router::setResponseCode($code);
		$accept = Router::parseRequestHeader('accept');
		if (in_array('application/json', $accept)) {
			Router::sendJSON([
				'Success' => false,
				'Error' => [
					'Title' => $title,
					'Message' => $error_message
				]
			]);
		} elseif (in_array('text/html', $accept) || in_array('*/*', $accept)) {
			Router::sendResponse(View::render('error', [
				'page_title' => $title,
				'error_icon' => ($code >= 400 && $code < 404 || $code >= 500 && $code < 600 ? 'warning' : 'help'),
				'error_class' => ($code >= 200 && $code < 300 ? 'success' : ($code >= 400 && $code < 404 || $code >= 500 && $code < 600 ? 'error' : 'info')),
				'error_message' => $error_message,
				'error_title' => $error_title,
				'close' => $close
			]));
		} else {
			if ($error_message !== null) {
				Router::sendResponse($title . ': ' . $error_message, Router::CONTENT_TYPE_TEXT);
			} else {
				Router::sendResponse($title, Router::CONTENT_TYPE_TEXT);
			}
		}
	}

	public static function addMessage($message) {
		if (is_string($message)) {
			$message = [
				'body' => $message
			];
		}
		if (is_array($message)) {
			Session::set('messages', array_merge([
				$message
			], (Session::get('messages') ?? [])));
		} else {
			throw new \InvalidArgumentException('Message must be string or array');
		}
	}
}
