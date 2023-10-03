<?php declare(strict_types=1);
use Obie\Http\Token;
use PHPUnit\Framework\TestCase;

final class HttpTokenTest extends TestCase {
	public function testRfc5987ExtendedUtf8(): void {
		$this->assertTrue(Token::isValidParamValue("%e2%82%ac%20exchange%20rates"));
	}
}