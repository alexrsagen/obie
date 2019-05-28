<?php namespace ZeroX;
if (!defined('IN_ZEROX')) {
	exit;
}

class Useragent {
	const _BRACKET_BEGIN = [
		')' => '(',
		'}' => '{',
		']' => '['
	];

	// OS constants
	const OS_WINDOWS = 'windows';
	const OS_MACOS   = 'macos';
	const OS_LINUX   = 'linux';
	const OS_ANDROID = 'android';
	const OS_IOS     = 'ios';

	// Arch constants
	const ARCH_64 = 'x86_64';
	const ARCH_32 = 'x86';

	// Product class constants
	const PRODUCT_IE        = 'ie';
	const PRODUCT_EDGE      = 'edge';
	const PRODUCT_CHROME    = 'chrome';
	const PRODUCT_SAFARI    = 'safari';
	const PRODUCT_OPERA     = 'opera';
	const PRODUCT_FIREFOX   = 'firefox';
	const PRODUCT_WATERFOX  = 'waterfox';
	const PRODUCT_VIVALDI   = 'vivaldi';
	const PRODUCT_MAXTHON   = 'maxthon';
	const PRODUCT_SEAMONKEY = 'seamonkey';
	const PRODUCT_AVANT     = 'avant';
	const PRODUCT_AVAST     = 'avast';
	const PRODUCT_ANDROID   = 'android';

	// Useragent and parsed data
	public $full         = ''; // Full useragent string
	public $pre_version  = '';
	public $pre_info     = []; // [...]
	public $products     = []; // [['product' => 'Mozilla', 'version' => '5.0', 'info' => [...]], ...]
	public $extra        = '';

	// Product classifications
	public $product      = '';
	public $os           = '';
	public $arch         = '';
	public $is_mobile    = false;

	public function __construct(string $ua) {
		$this->full = $ua;

		$ua              = trim($ua, "+ \t\n\r\0\x0B");
		$expect_version  = false;
		$expect_info     = false;
		$is_quoted_value = false;
		$is_escape       = false;
		$is_value_done   = false;
		$quote_type      = '';
		$bracket_level   = []; // ['(', '{', '[', ...]
		$cur_token_type  = 'product';
		$cur_token       = '';
		$cur_product_i   = -1;

		for ($i = 0; $i <= strlen($ua); $i++) {
			if ($i < strlen($ua)) {
				switch ($ua[$i]) {
					case '/':
						if (!$is_escape && !$is_quoted_value && $cur_token_type === 'product') {
							$is_value_done  = true;
							$expect_version = true;
						} else {
							$cur_token .= $ua[$i];
						}
						break;

					case ' ':
						if (!$is_escape && !$is_quoted_value && $cur_token_type !== 'info') {
							$is_value_done = true;
						} else {
							$cur_token .= $ua[$i];
						}
						break;

					case ';':
						if (!$is_escape && !$is_quoted_value && $cur_token_type === 'info') {
							if (strlen(trim($cur_token, "+ \t\n\r\0\x0B")) > 0) {
								if ($cur_product_i > -1) {
									$this->products[$cur_product_i]['info'][] = trim($cur_token, "+ \t\n\r\0\x0B");
								} else {
									$this->pre_info[] = trim($cur_token, "+ \t\n\r\0\x0B");
								}
							}
							$cur_token  = '';
						} elseif (!$is_escape && !$is_quoted_value && $cur_token_type === 'product') {
							$is_value_done  = true;
						} else {
							$cur_token .= $ua[$i];
						}
						break;

					case '(':
					case '{':
					case '[':
						if (!$is_escape && !$is_quoted_value) {
							if (count($bracket_level) > 0) {
								$cur_token .= $ua[$i];
							}
							$bracket_level[] = $ua[$i];
							$is_value_done   = true;
							$expect_info     = true;
						} else {
							$cur_token .= $ua[$i];
						}
						break;

					case ')':
					case '}':
					case ']':
						if (!$is_escape && !$is_quoted_value && count($bracket_level) > 0 && $bracket_level[count($bracket_level)-1] === self::_BRACKET_BEGIN[$ua[$i]]) {
							if (count($bracket_level) > 1) {
								$cur_token .= $ua[$i];
							}
							$bracket_level = array_slice($bracket_level, 0, count($bracket_level)-1);
							if (count($bracket_level) === 0) {
								$is_value_done = true;
							}
						} else {
							$cur_token .= $ua[$i];
						}
						break;

					case '\'':
					case '"':
						if ($is_quoted_value) {
							if ($quote_type === $ua[$i] && !$is_escape) {
								$is_quoted_value = false;
							} else {
								$cur_token .= $quote_type . $ua[$i] . $quote_type;
							}
						} else {
							$is_quoted_value = true;
							$quote_type = $ua[$i];
						}
						break;

					case '\\':
						if ($is_escape) {
							$cur_token .= $ua[$i];
						} else {
							$is_escape = true;
							continue 2;
						}
						break;

					default:
						$cur_token .= $ua[$i];
						break;
				}
			}

			if ($is_value_done || $i === strlen($ua)) {
				switch ($cur_token_type) {
					case 'product':
						if (strlen(trim($cur_token, "+ \t\n\r\0\x0B")) > 0) {
							$this->products[] = [
								'product' => trim($cur_token, "+ \t\n\r\0\x0B"),
								'version' => '',
								'info'    => []
							];
							$cur_product_i++;
						}
						$cur_token      = '';
						$cur_token_type = ($expect_version ? 'version' : ($expect_info ? 'info' : 'product'));
						break;

					case 'version':
						if (strlen(trim($cur_token, "+ \t\n\r\0\x0B")) > 0) {
							if ($cur_product_i > -1) {
								$this->products[$cur_product_i]['version'] = trim($cur_token, "+ \t\n\r\0\x0B");
							} else {
								$this->pre_version = trim($cur_token, "+ \t\n\r\0\x0B");
							}
						}
						$cur_token      = '';
						$cur_token_type = ($expect_version ? 'version' : ($expect_info ? 'info' : 'product'));
						break;

					case 'info':
						if (strlen(trim($cur_token, "+ \t\n\r\0\x0B")) > 0) {
							if ($cur_product_i > -1) {
								$this->products[$cur_product_i]['info'][] = trim($cur_token, "+ \t\n\r\0\x0B");
							} else {
								$this->pre_info[] = trim($cur_token, "+ \t\n\r\0\x0B");
							}
						}
						$cur_token      = '';
						$cur_token_type = ($expect_version ? 'version' : ($expect_info ? 'info' : 'product'));
						break;
				}
				$is_value_done  = false;
				$expect_version = false;
				$expect_info    = false;
			}

			$is_escape = false;
		}

		if (strlen($cur_token) > 0) {
			$this->extra = $cur_token;
		}

		// TODO: Try to classify OS, Arch, Product
	}
}
