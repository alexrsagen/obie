<?php namespace ZeroX\Validation;
if (!defined('IN_ZEROX')) {
	return;
}

class PasswordValidator implements IValidator {
	protected $email_parts = [];
	protected $message = null;

	public function __construct(string $email) {
		$email_parts = explode('@', $email);
		if (count($email_parts) === 2) {
			$this->email_parts = preg_split('/[^a-z0-9]/', strtolower($email_parts[0]));
			$this->email_parts += preg_split('/[^a-z0-9]/', strtolower(substr($email_parts[1], 0, strpos($email_parts[1], '.'))));
		}
	}

	public function validate($input) : bool {
		if (!is_string($input)) return false;
		$this->message = null;

		// Password must be minimum 8 characters long
		if (strlen($input) < 8) {
			$this->message = 'Password must be at least 8 characters long.';
			return false;
		}

		// Password must be maximum 1024 characters long
		if (strlen($input) > 1024) {
			$this->message = 'Password must be maximum 1024 characters long. While we do hash your password, it is still practical to impose some kind of maximum limit here.';
			return false;
		}

		$input_lower = strtolower($input);
		$input_lower_denum = strtr($input_lower, '5$34@01!', 'sseaaoii');
		$input_lower_denum2 = strtr($input_lower, '5$34@01!', 'sseaaoll');

		// Password must contain:
		// - At least 1 lowercase letters
		// - At least 1 uppercase letters
		// - At least 1 numbers
		// - At least 1 non-alphanumeric characters
		// - At least 2 characters from 2 of the above categories
		// $lc = 0; $uc = 0; $num = 0; $other = 0;
		// for ($i = 0; $i < strlen($input); $i++) {
		// 	if (preg_match('/^[a-z]$/', $input[$i]) === 1) {
		// 		$lc++;
		// 	} elseif (preg_match('/^[A-Z]$/', $input[$i]) === 1) {
		// 		$uc++;
		// 	} elseif (preg_match('/^[0-9]$/', $input[$i]) === 1) {
		// 		$num++;
		// 	} else {
		// 		$other++;
		// 	}
		// }
		// $cat_ok = 0;
		// if ($lc >= 2) $cat_ok++;
		// if ($uc >= 2) $cat_ok++;
		// if ($num >= 2) $cat_ok++;
		// if ($other >= 2) $cat_ok++;
		// if ($lc < 1 || $uc < 1 || $num < 1 || $other < 1 || $cat_ok < 2) {
		// 	$this->message = 'Password must contain at least 1 lowercase and 1 uppercase letter, 1 number, 1 symbol and at least 2 characters from 2 of the aforementioned categories.';
		// 	return false;
		// }

		// Password must not contain local or domain part of email address (also in reverse)
		foreach ($this->email_parts as $part) {
			if (strpos($input_lower, $part) !== false ||
				strpos($input_lower, strrev($part)) !== false ||
				strpos($input_lower_denum, $part) !== false ||
				strpos($input_lower_denum, strrev($part)) !== false ||
				strpos($input_lower_denum2, $part) !== false ||
				strpos($input_lower_denum2, strrev($part)) !== false) {
				$this->message = 'Password must not contain any part of your email address.';
				return false;
			}
		}

		// String entropy of password must be >=2.5
		if (static::entropy($input) < 2.5) {
			$this->message = 'Password entropy is too low. Mix it up a little, use more different characters!';
			return false;
		}

		// Password must not contain a string from the wordlist
		// $fh = fopen(ZEROX_BASE_DIR . '/badpasswords.txt', 'r');
		// if ($fh !== false) {
		// 	$found = false;
		// 	while (!$found && !feof($fh)) {
		// 		$word = trim(strtolower(fgets($fh, 1024)));
		// 		if (strlen($word) === 0) continue;
		// 		if (strlen($word) > 6 && (strpos($input_lower, $word) !== false || strpos($input_lower_denum, $word) !== false) ||
		// 			$input_lower === $word || $input_lower_denum === $word) {
		// 			$found = true;
		// 			break;
		// 		}
		// 	}
		// 	fclose($fh);
		// 	if ($found) {
		// 		$this->message = 'A part of that password is very common. You should probably change it if you use it anywhere. Anyway, pick a new one.';
		// 		return false;
		// 	}
		// }

		return true;
	}

	public function getMessage() {
		return $this->message;
	}

	private static function entropy(string $input) {
		$h = 0;
		$size = strlen($input);
		foreach (count_chars($input, 1) as $v) {
			$p = $v / $size;
			$h -= $p * log($p) / log(2);
		}
		return $h;
	 }
}
