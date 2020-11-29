<?php namespace Obie;

class Util {
	public static function formatBytes(int $bytes): string {
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

	public static function formatTime($input): string {
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
		if (!App::getConfig()->get('mail', 'enable')) {
			throw new \Exception('Mail is not enabled in the server configuration');
		}

		$content_type = 'text/plain';
		if ($is_html) {
			$content_type = 'text/html';
		}

		$transport = new \Swift_SmtpTransport(App::getConfig()->get('mail', 'host'), App::getConfig()->get('mail', 'port'), App::getConfig()->get('mail', 'security'));
		$transport->setUsername(App::getConfig()->get('mail', 'username'));
		$transport->setPassword(App::getConfig()->get('mail', 'password'));

		$mailer = new \Swift_Mailer($transport);

		$message = new \Swift_Message($subject);
		$message->setFrom([App::getConfig()->get('mail', 'from_email') => App::getConfig()->get('mail', 'from_name')]);
		$message->setTo($recipients);
		$message->setBody($body, $content_type);

		try {
			return $mailer->send($message);
		} catch (\Exception $e) {
			return false;
		}
	}
}
