<?php declare(strict_types=1);
use Obie\Http\ExtendedHeaderValue;
use PHPUnit\Framework\TestCase;

final class HttpExtendedHeaderValueTest extends TestCase {
	public function testRfc5987DecodePlain(): void {
		$value = ExtendedHeaderValue::decode('Economy');
		$this->assertNull($value);
	}

	public function testRfc5987DecodeQuotedString(): void {
		$value = ExtendedHeaderValue::decode("\"US-$ rates\"");
		$this->assertNull($value);
	}

	public function testRfc5987DecodeExtendedIso88591(): void {
		$value = ExtendedHeaderValue::decode("iso-8859-1'en'%A3%20rates");
		$this->assertNotNull($value);
		$this->assertEquals('£ rates', $value);
	}

	public function testRfc5987DecodeExtendedUtf8(): void {
		$value = ExtendedHeaderValue::decode("utf-8''%e2%82%ac%20exchange%20rates");
		$this->assertNotNull($value);
		$this->assertEquals('€ exchange rates', $value);
	}

	public function testRfc5987DecodeUnknownCharset(): void {
		$value = ExtendedHeaderValue::decode("iso-8859-999''invalid-charset");
		$this->assertNull($value);
	}
}