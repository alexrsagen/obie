<?php namespace Obie;
use Obie\Encoding\Bits;
use Obie\Ip\Cidr;

class Ip {
	const CIDR_LOCALHOST_V4 = '127.0.0.0/8';
	const CIDR_LOCALHOST_V6 = '::1/128';

	public static function isIPv4(string $ip, bool $allow_private = true): bool {
		return filter_var($ip, FILTER_VALIDATE_IP, ($allow_private ? FILTER_FLAG_NONE : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) | FILTER_FLAG_IPV4) !== false;
	}

	public static function isIPv6(string $ip, bool $allow_private = true): bool {
		return filter_var($ip, FILTER_VALIDATE_IP, ($allow_private ? FILTER_FLAG_NONE : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) | FILTER_FLAG_IPV6) !== false;
	}

	public static function isLinkLocal(Cidr|string $ip): bool {
		if (is_string($ip)) $ip = Cidr::fromIP($ip);
		if ($ip === null) return false;

		/** @var Cidr[] $link_local_cidr */
		static $link_local_cidr = [];
		if (count($link_local_cidr) === 0) {
			$link_local_cidr = [
				Cidr::fromCIDR(self::CIDR_LOCALHOST_V4),
				Cidr::fromCIDR(self::CIDR_LOCALHOST_V6),
			];
		}
		foreach ($link_local_cidr as $cidr) {
			if ($cidr->contains($ip)) return true;
		}
		return false;
	}

	public static function normalize(string $ip, bool $allow_bin = true, bool $allow_oct = true, bool $allow_dec = true, bool $allow_hex = true): string|false {
		$ip = urldecode($ip);
		$ip = trim($ip, '[]'); // remove brackets, if any
		$ip = rtrim($ip, '.'); // remove trailing dot, if any
		if (empty($ip)) return false;

		// handle IPv6 addresses
		if (static::isIPv6($ip)) {
			$ipv6_raw = inet_pton($ip);
			if (!is_string($ipv6_raw)) return false;

			// handle IPv6-mapped and IPv6-compatible IPv4 addresses
			$ipv6_hex = bin2hex(inet_pton($ip));
			$ip_is_ipv4_mapped_ipv6 = substr($ipv6_hex, 0, 24) === '00000000000000000000ffff';
			$ip_is_ipv4_compatible_ipv6 = substr($ipv6_hex, 0, 24) === '000000000000000000000000';
			if ($ip_is_ipv4_mapped_ipv6 || $ip_is_ipv4_compatible_ipv6) {
				return inet_ntop(hex2bin(substr($ipv6_hex, 24)));
			}

			return inet_ntop($ipv6_raw);
		}

		// handle hexadecimal addresses
		if (strncasecmp($ip, '0x', 2) === 0 && !str_contains($ip, '.')) {
			if (!$allow_hex) return false;

			$ip_hex = substr($ip, 2);
			if (!ctype_xdigit($ip_hex)) return false; // invalid hex
			if (strlen($ip_hex) % 2 !== 0) $ip_hex = '0' . $ip_hex;

			// hexadecimal (base-16)
			return inet_ntop(hex2bin($ip_hex));
		}

		// handle integer addresses
		if (ctype_digit($ip)) {
			if ($allow_bin && strlen($ip) > 1 && strspn($ip, '01') === strlen($ip)) {
				// bitstring (base-2)
				return inet_ntop(Bits::decode(str_pad($ip, 32, '0', STR_PAD_LEFT)));
			} elseif ($allow_oct && str_starts_with($ip, '0')) {
				// octal (base-8)
				return inet_ntop(Bits::decode(str_pad(Bits::fromOctal($ip), 32, '0', STR_PAD_LEFT)));
			} elseif ($allow_dec) {
				// decimal (base-10)
				return inet_ntop(Bits::decode(str_pad(Bits::fromDecimal($ip), 32, '0', STR_PAD_LEFT)));
			}
		}

		// handle IPv4 address expansion
		$octets = explode('.', $ip, 5);
		if (count($octets) > 4) return false; // invalid IPv4 address: max 4 octets

		$bitstring = '';
		for ($i = 0; $i < count($octets); $i++) {
			if (strncasecmp($octets[$i], '0x', 2) === 0) {
				if (!$allow_hex) return false;

				$octets[$i] = substr($octets[$i], 2);
				$octets[$i] = ltrim($octets[$i], '0');
				if (!ctype_xdigit($octets[$i])) return false; // invalid hex
				if (strlen($octets[$i]) % 2 !== 0) $octets[$i] = '0' . $octets[$i];

				// hexadecimal (base-16)
				$bitstring .= str_pad(Bits::encode(hex2bin($octets[$i])), 8, '0', STR_PAD_LEFT);
			} elseif (ctype_digit($octets[$i])) {
				if ($allow_bin && strlen($octets[$i]) > 1 && strspn($octets[$i], '01') === strlen($octets[$i])) {
					// bitstring (base-2)
					$bitstring .= str_pad(Bits::encode(Bits::decode($octets[$i])), 8, '0', STR_PAD_LEFT);
				} elseif ($allow_oct && str_starts_with($octets[$i], '0')) {
					// octal (base-8)
					$bitstring .= str_pad(Bits::fromOctal($octets[$i]), 8, '0', STR_PAD_LEFT);
				} elseif ($allow_dec) {
					// decimal (base-10)
					$bitstring .= str_pad(Bits::fromDecimal($octets[$i]), 8, '0', STR_PAD_LEFT);
				}
			} else {
				return false;
			}
		}

		$ip = inet_ntop(Bits::decode($bitstring));
		if ($ip === false || !static::isIPv4($ip)) return false;
		return $ip;
	}

	public static function resolve(string $host, bool $allow_private = true): array {
		$host_ip = static::normalize($host);
		if ($host_ip !== false) {
			$host_ips = [$host_ip];
		} else {
			$dns_host_records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
			$host_ips = array_map(function($record) {
				if ($record['type'] === 'A') return $record['ip'];
				if ($record['type'] === 'AAAA') return $record['ipv6'];
				return null;
			}, $dns_host_records);
		}

		// validate each IP (checking for private/reserved range)
		$host_ips = array_filter($host_ips, function ($host_ip) use ($allow_private) {
			return filter_var($host_ip, FILTER_VALIDATE_IP, $allow_private ? FILTER_FLAG_NONE : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
		});

		natcasesort($host_ips);
		return array_values($host_ips);
	}
}