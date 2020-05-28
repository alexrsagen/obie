<?php namespace ZeroX;

class Useragent {
	const _BRACKET_BEGIN = [
		')' => '(',
		'}' => '{',
		']' => '['
	];

	// OS constants
	const OS_WINDOWS  = 'windows';
	const OS_MACOS    = 'macos';
	const OS_LINUX    = 'linux';
	const OS_ANDROID  = 'android';
	const OS_IOS      = 'ios';
	const OS_CHROMEOS = 'chromeos';

	// Arch constants
	const ARCH_64    = 'x86_64';
	const ARCH_32    = 'x86';
	const ARCH_ARM64 = 'arm64';
	const ARCH_ARM   = 'arm';

	// Product class constants
	const PRODUCT_IE        = 'ie';
	const PRODUCT_EDGEHTML  = 'edge';
	const PRODUCT_EDGE      = 'edg';
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
	const PRODUCT_YOWSER    = 'yowser';

	// Useragent and parsed data
	public $full         = ''; // Full useragent string
	public $pre_version  = '';
	public $pre_info     = []; // [...]
	public $products     = []; // [['product' => 'Mozilla', 'version' => '5.0', 'info' => [...]], ...]
	public $extra        = '';

	// Product classifications
	public $product      = '';
	public $version      = '';
	public $os           = '';
	public $os_version   = '';
	public $arch         = '';
	public $is_mobile    = null;

	// Classifier rules
	public static $product_rules = [
		[
			'product' => self::PRODUCT_CHROME,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Safari'],
			'version_product' => 'Chrome',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_EDGE,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Safari', 'Edg'],
			'version_product' => 'Edg',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_EDGE,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Mobile', 'Safari', 'EdgA'],
			'version_product' => 'EdgA',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_EDGE,
			'products' => ['Mozilla', 'AppleWebKit', 'Version', 'EdgiOS', 'Mobile', 'Safari'],
			'version_product' => 'EdgiOS',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_EDGEHTML,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Safari', 'Edge'],
			'version_product' => 'Edge',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_EDGEHTML,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Mobile', 'Safari', 'Edge'],
			'version_product' => 'Edge',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_FIREFOX,
			'products' => ['Mozilla', 'Gecko', 'Firefox'],
			'version_product' => 'Firefox',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_FIREFOX,
			'products' => ['Mozilla', 'AppleWebKit', 'FxiOS', 'Mobile', 'Safari'],
			'version_product' => 'FxiOS',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_SAFARI,
			'products' => ['Mozilla', 'AppleWebKit', 'Version', 'Safari'],
			'version_product' => 'Version',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_SAFARI,
			'products' => ['Mozilla', 'AppleWebKit', 'Version', 'Mobile', 'Safari'],
			'version_product' => 'Version',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_IE,
			'products' => ['Mozilla', 'Trident'],
			'version_product' => 'Mozilla',
			'version_info_prefix' => 'MSIE ',
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_IE,
			'products' => ['Mozilla'],
			'version_product' => 'Mozilla',
			'version_info_prefix' => 'MSIE ',
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_IE,
			'products' => ['Mozilla', 'like', 'Gecko'],
			'version_product' => 'Mozilla',
			'version_info_prefix' => 'rv:',
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_OPERA,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Safari', 'OPR'],
			'version_product' => 'OPR',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_OPERA,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Mobile', 'Safari', 'OPR'],
			'version_product' => 'OPR',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_VIVALDI,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'Safari', 'Vivaldi'],
			'version_product' => 'Vivaldi',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_YOWSER,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'YaBrowser', 'Yowser', 'Safari'],
			'version_product' => 'YaBrowser',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => null,
		],
		[
			'product' => self::PRODUCT_YOWSER,
			'products' => ['Mozilla', 'AppleWebKit', 'Chrome', 'YaBrowser', 'Mobile', 'Safari'],
			'version_product' => 'YaBrowser',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],
		[
			'product' => self::PRODUCT_YOWSER,
			'products' => ['Mozilla', 'AppleWebKit', 'Version', 'YaBrowser', 'Mobile', 'Safari'],
			'version_product' => 'YaBrowser',
			'version_info_prefix' => null,
			'os_product' => 'Mozilla',
			'arch_product' => 'Mozilla',
			'is_mobile' => true,
		],

		// TODO: Add more product rules
	];

	public static $os_rules = [
		'Windows 95' => ['os' => self::OS_WINDOWS, 'os_version' => '4.00', 'is_mobile' => false],
		'Win95' => ['os' => self::OS_WINDOWS, 'os_version' => '4.00', 'is_mobile' => false],
		'Windows 98' => ['os' => self::OS_WINDOWS, 'os_version' => '4.10', 'is_mobile' => false],
		'Win98' => ['os' => self::OS_WINDOWS, 'os_version' => '4.10', 'is_mobile' => false],
		'Windows CE' => ['os' => self::OS_WINDOWS, 'os_version' => 'CE', 'is_mobile' => false],
		'Windows 9x 4.90' => ['os' => self::OS_WINDOWS, 'os_version' => '4.90', 'is_mobile' => false],
		'Windows NT 5.0' => ['os' => self::OS_WINDOWS, 'os_version' => '5.0', 'is_mobile' => false],
		'Windows NT 4.0' => ['os' => self::OS_WINDOWS, 'os_version' => '4.0', 'is_mobile' => false],
		'Windows NT 5.1' => ['os' => self::OS_WINDOWS, 'os_version' => '5.1', 'is_mobile' => false],
		'Windows NT 5.2' => ['os' => self::OS_WINDOWS, 'os_version' => '5.2', 'is_mobile' => false],
		'Windows NT 6.0' => ['os' => self::OS_WINDOWS, 'os_version' => '6.0', 'is_mobile' => false],
		'Windows NT 6.1' => ['os' => self::OS_WINDOWS, 'os_version' => '6.1', 'is_mobile' => false],
		'Windows NT 6.2' => ['os' => self::OS_WINDOWS, 'os_version' => '6.2', 'is_mobile' => false],
		'Windows NT 6.3' => ['os' => self::OS_WINDOWS, 'os_version' => '6.3', 'is_mobile' => false],
		'Windows NT 10.0' => ['os' => self::OS_WINDOWS, 'os_version' => '10.0', 'is_mobile' => false],
		'Windows 10.0' => ['os' => self::OS_WINDOWS, 'os_version' => '10.0', 'is_mobile' => false],

		'Windows Mobile 10' => ['os' => self::OS_WINDOWS, 'os_version' => '10.0', 'is_mobile' => true],

		'Intel Mac OS X 10.15' => ['os' => self::OS_MACOS, 'os_version' => '10.15', 'is_mobile' => false],
		'Intel Mac OS X 10_15_4' => ['os' => self::OS_MACOS, 'os_version' => '10.15.4', 'is_mobile' => false],
		'Macintosh' => ['os' => self::OS_MACOS, 'os_version' => '', 'is_mobile' => false],

		'Ubuntu' => ['os' => self::OS_LINUX, 'os_version' => '', 'is_mobile' => false],
		'Fedora' => ['os' => self::OS_LINUX, 'os_version' => '', 'is_mobile' => false],
		'Linux x86_64' => ['os' => self::OS_LINUX, 'os_version' => '', 'is_mobile' => false],
		'Linux i686' => ['os' => self::OS_LINUX, 'os_version' => '', 'is_mobile' => false],

		'CPU iPhone OS 13_4_1 like Mac OS X' => ['os' => self::OS_IOS, 'os_version' => '13.4.1', 'is_mobile' => true],
		'CPU iPhone 13_4_1 like Mac OS X' => ['os' => self::OS_IOS, 'os_version' => '13.4.1', 'is_mobile' => true],
		'CPU OS 13_4_1 like Mac OS X' => ['os' => self::OS_IOS, 'os_version' => '13.4.1', 'is_mobile' => true],
		'iPhone' => ['os' => self::OS_IOS, 'os_version' => '', 'is_mobile' => true],
		'iPad' => ['os' => self::OS_IOS, 'os_version' => '', 'is_mobile' => true],
		'iPod touch' => ['os' => self::OS_IOS, 'os_version' => '', 'is_mobile' => true],

		'Android 10' => ['os' => self::OS_ANDROID, 'os_version' => '10.0', 'is_mobile' => true],

		'CrOS x86_64 12739.111.0' => ['os' => self::OS_CHROMEOS, 'os_version' => '12739.111.0', 'is_mobile' => null],
		'CrOS armv7l 12739.111.0' => ['os' => self::OS_CHROMEOS, 'os_version' => '12739.111.0', 'is_mobile' => null],
		'CrOS aarch64 12739.111.0' => ['os' => self::OS_CHROMEOS, 'os_version' => '12739.111.0', 'is_mobile' => null],

		// TODO: Add more OS rules
	];

	public static $arch_rules = [
		self::ARCH_64 => self::ARCH_64,
		'Win64' => self::ARCH_64,
		'WOW64' => self::ARCH_64,
		'x64' => self::ARCH_64,
		'Linux x86_64' => self::ARCH_64,
		'CrOS x86_64 12739.111.0' => self::ARCH_64,
		self::ARCH_32 => self::ARCH_32,
		'Win32' => self::ARCH_32,
		'Linux i686' => self::ARCH_32,
		'CrOS armv7l 12739.111.0' => self::ARCH_ARM,
		'CrOS aarch64 12739.111.0' => self::ARCH_ARM64,

		// TODO: Add more arch rules
	];

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

		// TODO: Improve classifier fallbacks for unknown products, OS and arches
		$products_flat = [];
		foreach ($this->products as $product) {
			$products_flat[] = $product['product'];
		}
		foreach (static::$product_rules as $rule) {
			if ($products_flat !== $rule['products']) continue;
			$this->product = $rule['product'];
			$this->is_mobile = $rule['is_mobile'];
			foreach ($this->products as $product) {
				if ($product['product'] === $rule['version_product'] && $rule['version_info_prefix'] === null) {
					$this->version = $product['version'];
				}
				if (
					$product['product'] === $rule['os_product'] ||
					$product['product'] === $rule['arch_product'] ||
					$product['product'] === $rule['version_product'] && $rule['version_info_prefix'] !== null ||
					$this->is_mobile === null
				) {
					foreach ($product['info'] as $info) {
						if ($product['product'] === $rule['os_product'] && array_key_exists($info, self::$os_rules)) {
							$this->os = self::$os_rules[$info]['os'];
							$this->os_version = self::$os_rules[$info]['os_version'];
							$this->is_mobile = self::$os_rules[$info]['is_mobile'];
						}
						if ($product['product'] === $rule['arch_product'] && array_key_exists($info, self::$arch_rules)) {
							$this->arch = self::$arch_rules[$info];
						}
						if ($product['product'] === $rule['version_product'] && $rule['version_info_prefix'] !== null && substr($info, 0, strlen($rule['version_info_prefix'])) === $rule['version_info_prefix']) {
							$this->version = substr($info, strlen($rule['version_info_prefix']));
						}
						if ($this->is_mobile === null && $info === 'Mobile') {
							$this->is_mobile = true;
						}
					}
				}
				if ($this->is_mobile === null && $product['product'] === 'Mobile') {
					$this->is_mobile = true;
				}
			}
			break;
		}
	}
}
