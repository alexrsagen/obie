<?php namespace Obie\Ip;
use Obie\Encoding\Bits;
use Obie\Ip;
use Obie\Encoding\Spf1;
use Obie\Encoding\Spf1\Record;

class Cidr {
	function __construct(
		public string $address,
		public int $mask_bits = -1,
		protected string $address_bin = '',
		protected string $address_bits = '',
	) {
		if (strlen($address_bin) === 0) {
			$bin = inet_pton($address);
			if ($bin === false) throw new \Exception('Invalid address');
			$this->address_bin = $bin;
		}
		if (strlen($address_bits) === 0) {
			$this->address_bits = Bits::encode($this->address_bin);
		}
		if ($mask_bits === -1) {
			$this->mask_bits = strlen($this->address_bits);
		}
	}

	public static function fromCIDR(string $cidr): ?static {
		$cidr_parts = explode('/', $cidr, 2);
		if (count($cidr_parts) === 2) {
			if (!is_numeric($cidr_parts[1])) return null;
			$mask_bits = (int)$cidr_parts[1];
			if ($mask_bits < 0) return null;
		} else {
			$mask_bits = -1;
		}
		try {
			return new static($cidr_parts[0], $mask_bits);
		} catch (\Exception $e) {
			return null;
		}
	}

	public static function fromIP(string $ip): ?static {
		try {
			return new static($ip);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Extract a list of Cidr objects from an SPF Record
	 *
	 * @param Record $record The SPF Record to extract Cidr objects from
	 * @param int $max_dns_lookup Max amount of DNS lookups to perform
	 * @param int $max_dns_mx_host_lookup Max amount of MX host lookups to perform (these are separate from $max_dns_lookups)
	 * @return static[]|null
	 */
	public static function fromSpf1Record(Record $record, int &$max_dns_lookup = 9, int $max_dns_mx_host_lookup = 10): ?array {
		$host_cidr_by_qualifier = [
			Spf1::QUALIFIER_PASS => [],
			Spf1::QUALIFIER_FAIL => [],
			Spf1::QUALIFIER_SOFTFAIL => [],
			Spf1::QUALIFIER_NEUTRAL => [],
		];
		$all_redirects_host_cidr_pass = [];
		$record_has_all_mechanism = false;

		foreach ($record->directives as $directive) {
			switch ($directive->mechanism) {
			case Spf1::MECHANISM_A:
				if ($max_dns_lookup <= 0) return null; $max_dns_lookup -= 1;
				$a_host_cidr = Ip::resolve($directive->value);

				$host_cidr_by_qualifier[$directive->qualifier] = array_merge($host_cidr_by_qualifier[$directive->qualifier], $a_host_cidr);
				break;

			case Spf1::MECHANISM_MX:
				if ($max_dns_lookup <= 0) return null; $max_dns_lookup -= 1;
				if (@dns_get_mx($directive->value, $hosts)) {
					foreach ($hosts as $host) {
						if ($max_dns_mx_host_lookup <= 0) return null; $max_dns_mx_host_lookup -= 1;
						$mx_host_cidr = Ip::resolve($host);

						$host_cidr_by_qualifier[$directive->qualifier] = array_merge($host_cidr_by_qualifier[$directive->qualifier], $mx_host_cidr);
					}
				}
				break;

			case Spf1::MECHANISM_PTR:
				// explicitly ingored, as it is deprecated in RFC 7208
				break;

			case Spf1::MECHANISM_IP4:
			case Spf1::MECHANISM_IP6:
				$host_cidr_by_qualifier[$directive->qualifier][] = $directive->value;
				break;

			case Spf1::MECHANISM_INCLUDE:
				$include_host_cidr = static::fromSpf1RecordLookup($directive->value, $max_dns_lookup, $max_dns_mx_host_lookup);
				if ($include_host_cidr === null) return null;
				$host_cidr_by_qualifier[$directive->qualifier] = array_merge($host_cidr_by_qualifier[$directive->qualifier], $include_host_cidr);
				break;

			case Spf1::MECHANISM_ALL:
				$record_has_all_mechanism = true;
				break;
			}
		}

		if (!$record_has_all_mechanism && array_key_exists('redirect', $record->modifiers)) {
			$redirect_host_cidr_pass = static::fromSpf1RecordLookup($record->modifiers['redirect'], $max_dns_lookup, $max_dns_mx_host_lookup);
			if ($redirect_host_cidr_pass === null) return null;
			$all_redirects_host_cidr_pass = array_merge($all_redirects_host_cidr_pass, $redirect_host_cidr_pass);
		}

		$host_cidr_pass = $host_cidr_by_qualifier[Spf1::QUALIFIER_PASS];
		$host_cidr_pass = static::excludeMultiple($host_cidr_by_qualifier[Spf1::QUALIFIER_FAIL], $host_cidr_pass);
		$host_cidr_pass = static::excludeMultiple($host_cidr_by_qualifier[Spf1::QUALIFIER_SOFTFAIL], $host_cidr_pass);
		return array_merge($host_cidr_pass, $all_redirects_host_cidr_pass);
	}

	/**
	 * Extract a list of Cidr objects from a DNS name
	 *
	 * @param string $dns_name The DNS name in which to look for the initial SPF Record
	 * @param int $max_dns_lookup Max amount of DNS lookups to perform
	 * @param int $max_dns_mx_host_lookup Max amount of MX host lookups to perform (these are separate from $max_dns_lookups)
	 * @return static[]|null
	 */
	public static function fromSpf1RecordLookup(string $dns_name, int &$max_dns_lookup = 10, int $max_dns_mx_host_lookup = 10): ?array {
		if ($max_dns_lookup <= 0) return null; $max_dns_lookup -= 1;
		$spf_txt_records = @dns_get_record($dns_name, DNS_TXT);

		if (!is_array($spf_txt_records)) return [];

		foreach ($spf_txt_records as $txt_record) {
			if ($txt_record['type'] !== 'TXT') continue;

			$record = Spf1::decode($txt_record['txt']);
			if (!$record) continue;

			return static::fromSpf1Record($record, $max_dns_lookup, $max_dns_mx_host_lookup);
		}

		return [];
	}

	/**
	 * Extract a list of Cidr objects from a DNS name
	 *
	 * @deprecated
	 * @see static::fromSpf1RecordLookup()
	 * @param string $dns_name The DNS name in which to look for the initial SPF Record
	 * @param int $max_dns_lookup Max amount of DNS lookups to perform
	 * @param int $max_dns_mx_host_lookup Max amount of MX host lookups to perform (these are separate from $max_dns_lookups)
	 * @return static[]|null
	 */
	public static function fromSPFRecord(string $dns_name, int &$max_dns_lookup = 10, int $max_dns_mx_host_lookup = 10): ?array {
		return static::fromSpf1RecordLookup($dns_name, $max_dns_lookup, $max_dns_mx_host_lookup);
	}

	public function contains(Cidr $other): bool {
		$other_net_bits = substr($other->address_bits, 0, $this->mask_bits);
		$this_net_bits = substr($this->address_bits, 0, $this->mask_bits);
		return $other_net_bits === $this_net_bits && ($other->mask_bits >= $this->mask_bits);
	}

	/**
	 * Create an array of Cidr matching $this excluding $other
	 *
	 * @param Cidr $other
	 * @return Cidr[]
	 */
	public function exclude(Cidr $other): array {
		if ($this == $other || $other->contains($this)) return [];
		if (!$this->contains($other)) return [$this];
		if ($this->mask_bits >= strlen($this->address_bits)) return [];
		$next_mask = $this->mask_bits + 1;

		$cur_address_bits = str_pad(substr($this->address_bits, 0, $this->mask_bits) . '0', strlen($this->address_bits), '0', STR_PAD_RIGHT);
		$cur_address_bin = Bits::decode($cur_address_bits);
		$cur = new static(
			inet_ntop($cur_address_bin),
			$next_mask,
			address_bin: $cur_address_bin,
			address_bits: $cur_address_bits,
		);

		$next_address_bits = str_pad(substr($this->address_bits, 0, $this->mask_bits) . '1', strlen($this->address_bits), '0', STR_PAD_RIGHT);
		$next_address_bin = Bits::decode($next_address_bits);
		$next = new static(
			inet_ntop($next_address_bin),
			$next_mask,
			address_bin: $next_address_bin,
			address_bits: $next_address_bits,
		);

		return array_merge(
			$cur->exclude($other),
			$next->exclude($other),
		);
	}

	/**
	 * Create an array of Cidr matching $source excluding $exclude
	 *
	 * @param Cidr $other
	 * @return Cidr[]
	 */
	public static function excludeMultiple(Cidr|string|array $exclude, Cidr|string|array $source): array {
		$exclude = array_map(function(Cidr|string $cidr) {
			return is_string($cidr) ? Cidr::fromCIDR($cidr) : $cidr;
		}, is_array($exclude) ? $exclude : [$exclude]);

		$source = array_map(function(Cidr|string $cidr) {
			return is_string($cidr) ? Cidr::fromCIDR($cidr) : $cidr;
		}, is_array($source) ? $source : [$source]);

		if (count($exclude) === 0) return $source;

		$output = [];
		foreach ($source as $source_cidr) {
			foreach ($exclude as $exclude_cidr) {
				$output = array_merge($output, $source_cidr->exclude($exclude_cidr));
			}
		}
		return $output;
	}

	function __toString(): string {
		return $this->address . '/' . (string)$this->mask_bits;
	}
}