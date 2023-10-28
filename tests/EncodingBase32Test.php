<?php declare(strict_types=1);
use Obie\Encoding\Base32;
use PHPUnit\Framework\TestCase;

final class EncodingBase32Test extends TestCase {
	public function testDecodeEncodeRandom(): void {
		for ($i = 0; $i < 256; $i++) {
			$random = $i > 0 ? random_bytes($i) : '';
			$this->assertEquals($random, Base32::decode(Base32::encode($random)));
		}
	}

	public function testEncodeHelloWorld(): void {
		$this->assertEquals('JBSWY3DPEBLW64TMMQQQ====', Base32::encode('Hello World!'));
	}

	public function testDecodeHelloWorld(): void {
		$this->assertEquals('Hello World!', Base32::decode('JBSWY3DPEBLW64TMMQQQ===='));
	}
}