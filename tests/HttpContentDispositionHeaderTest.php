<?php declare(strict_types=1);
use Obie\Http\ContentDispositionHeader;
use PHPUnit\Framework\TestCase;

final class HttpContentDispositionHeaderTest extends TestCase {
	public function testEncodeEmpty(): void {
		$cd = new ContentDispositionHeader();
		$this->assertEquals('attachment', $cd->encode());
	}

	public function testEncodeWithFilenameUsAscii(): void {
		$cd = new ContentDispositionHeader(parameters: ['filename' => 'plans.pdf']);
		$this->assertEquals('attachment; filename=plans.pdf', $cd->encode());

		$cd = new ContentDispositionHeader(parameters: ['filename' => 'the "plans".pdf']);
		$this->assertEquals('attachment; filename="the \\"plans\\".pdf"', $cd->encode());
	}

	public function testForceExtFilenameOnly(): void {
		$cd = new ContentDispositionHeader(parameters: ['filename' => 'Ø.txt']);
		$this->assertEquals("attachment; filename=\"Ø.txt\"; filename*=UTF-8''%C3%98.txt", $cd->encode());
	}

	public function testEncodeWithFilenameIsUnicode(): void {
		$cd = new ContentDispositionHeader(parameters: ['filename' => 'планы.pdf']);
		$this->assertEquals('attachment; filename="планы.pdf"; filename*=UTF-8\'\'%D0%BF%D0%BB%D0%B0%D0%BD%D1%8B.pdf', $cd->encode());

		$cd = new ContentDispositionHeader(parameters: ['filename' => '£ and € rates.pdf']);
		$this->assertEquals('attachment; filename="£ and € rates.pdf"; filename*=UTF-8\'\'%C2%A3%20and%20%E2%82%AC%20rates.pdf', $cd->encode());

		$cd = new ContentDispositionHeader(parameters: ['filename' => '€ rates.pdf']);
		$this->assertEquals('attachment; filename="€ rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf', $cd->encode());

		$cd = new ContentDispositionHeader(parameters: ['filename' => '€\'*%().pdf']);
		$this->assertEquals('attachment; filename="€\'*%().pdf"; filename*=UTF-8\'\'%E2%82%AC%27%2A%25%28%29.pdf', $cd->encode());
	}

	public function testEncodeWithFilenameContainsHexEscape(): void {
		$cd = new ContentDispositionHeader(parameters: ['filename' => 'the%20plans.pdf']);
		$this->assertEquals('attachment; filename=the%20plans.pdf', $cd->encode());

		$cd = new ContentDispositionHeader(parameters: ['filename' => '€%20£.pdf']);
		$this->assertEquals('attachment; filename="€%20£.pdf"; filename*=UTF-8\'\'%E2%82%AC%2520%C2%A3.pdf', $cd->encode());
	}

	public function testEncodeWithSpecifiedType(): void {
		$cd = new ContentDispositionHeader();
		$this->assertEquals('attachment', $cd->encode());

		$cd = new ContentDispositionHeader(ContentDispositionHeader::DISP_INLINE);
		$this->assertEquals('inline', $cd->encode());

		$cd = new ContentDispositionHeader(ContentDispositionHeader::DISP_INLINE, ['filename' => 'plans.pdf']);
		$this->assertEquals('inline; filename=plans.pdf', $cd->encode());

		$cd = new ContentDispositionHeader('INLINE');
		$this->assertEquals('inline', $cd->encode());
	}

	public function testDecodeRfc2183AttachmentFile(): void {
		$cd = ContentDispositionHeader::decode("attachment; filename=genome.jpeg;\n modification-date=\"Wed, 12 Feb 1997 16:29:51 -0500\";");
		$this->assertNotNull($cd);
		$this->assertInstanceOf(ContentDispositionHeader::class, $cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('genome.jpeg', $cd->parameters['filename']);
		$this->assertArrayHasKey('modification-date', $cd->parameters);
		$this->assertEquals('Wed, 12 Feb 1997 16:29:51 -0500', $cd->parameters['modification-date']);
	}

	public function testDecodeShouldRejectInvalidDisposition(): void {
		$cd = ContentDispositionHeader::decode('"attachment"');
		$this->assertNull($cd);
	}

	public function testDecodeShouldRejectMissingDisposition(): void {
		$cd = ContentDispositionHeader::decode('filename="plans.pdf"');
		$this->assertNull($cd);

		$cd = ContentDispositionHeader::decode('; filename="plans.pdf"');
		$this->assertNull($cd);
	}

	public function testDecodeAttachmentWithNoParameters(): void {
		$cd = ContentDispositionHeader::decode('attachment');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertEmpty($cd->parameters);
	}

	public function testDecodeInlineWithNoParameters(): void {
		$cd = ContentDispositionHeader::decode('inline');
		$this->assertNotNull($cd);
		$this->assertEquals('inline', $cd->disposition);
		$this->assertEmpty($cd->parameters);
	}

	public function testDecodeFormDataWithNoParameters(): void {
		$cd = ContentDispositionHeader::decode('form-data');
		$this->assertNotNull($cd);
		$this->assertEquals('form-data', $cd->disposition);
		$this->assertEmpty($cd->parameters);
	}

	public function testDecodeWithTrailingLWS(): void {
		$cd = ContentDispositionHeader::decode("attachment \t ");
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertEmpty($cd->parameters);
	}

	public function testDecodeShouldNormalizeTypeToLowercase(): void {
		$cd = ContentDispositionHeader::decode("ATTACHMENT");
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertEmpty($cd->parameters);
	}

	public function testDecodeShouldIgnoreEmptyParameters(): void {
		$cd = ContentDispositionHeader::decode('attachment;');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('', $cd->parameters);

		$cd = ContentDispositionHeader::decode('attachment; filename="rates.pdf";');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('rates.pdf', $cd->parameters['filename']);
		$this->assertArrayNotHasKey('', $cd->parameters);
	}

	public function testDecodeShouldIgnoreMissingParameterValues(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename=');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
	}

	public function testDecodeShouldIgnoreInvalidParameterValues(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename=trolly,trains; foo=bar');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
		$this->assertArrayHasKey('foo', $cd->parameters);
		$this->assertEquals('bar', $cd->parameters['foo']);

		$cd = ContentDispositionHeader::decode('attachment; filename=total/; foo=bar');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
		$this->assertArrayHasKey('foo', $cd->parameters);
		$this->assertEquals('bar', $cd->parameters['foo']);
	}

	public function testDecodeShouldIgnoreParametersWithUnknownCharset(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*=ISO-8859-999\'\'%A4%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
		$this->assertArrayNotHasKey('filename*', $cd->parameters);
	}

	public function testDecodeDuplicateParameters(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename=foo; filename=bar');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('foo', $cd->parameters['filename']);
	}

	public function testDecodeLowercaseParameterName(): void {
		$cd = ContentDispositionHeader::decode('attachment; FILENAME="plans.pdf"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('plans.pdf', $cd->parameters['filename']);
	}

	public function testDecodeQuotedParameterValues(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename="plans.pdf"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('plans.pdf', $cd->parameters['filename']);

		$cd = ContentDispositionHeader::decode('attachment; filename="foo-%41.html"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('foo-%41.html', $cd->parameters['filename']);

		$cd = ContentDispositionHeader::decode('attachment; filename="the \\"plans\\".pdf"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('the "plans".pdf', $cd->parameters['filename']);
	}

	public function testDecodeShouldIncludeAllParameters(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename="plans.pdf"; foo=bar');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('plans.pdf', $cd->parameters['filename']);
		$this->assertArrayHasKey('foo', $cd->parameters);
		$this->assertEquals('bar', $cd->parameters['foo']);
	}

	public function testDecodeTokenFilename(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename=plans.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('plans.pdf', $cd->parameters['filename']);
	}

	public function testDecodeISO88591Filename(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename="£ rates.pdf"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('£ rates.pdf', $cd->parameters['filename']);
	}

	public function testDecodeQuotedExtendedParam(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*="UTF-8\'\'%E2%82%AC%20rates.pdf"');
		$this->assertNull($cd);
	}

	public function testDecodeUtf8ExtendedFilename(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*=utf-8\'\'%E2%82%AC%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('€ rates.pdf', $cd->parameters['filename']);

		$cd = ContentDispositionHeader::decode('attachment; filename*=UTF-8\'\'%EF%BF%BD');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals("�", $cd->parameters['filename']);
	}

	public function testDecodeISO88591ExtendedFilename(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*=ISO-8859-1\'\'%A3%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('£ rates.pdf', $cd->parameters['filename']);

		$cd = ContentDispositionHeader::decode('attachment; filename*=ISO-8859-1\'\'%82%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals("\xC2\x82 rates.pdf", $cd->parameters['filename']);
	}

	public function testDecodeISO88592ExtendedFilename(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*=ISO-8859-2\'\'%A4%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('¤ rates.pdf', $cd->parameters['filename']);
	}

	public function testDecodeShouldAcceptEmbeddedLanguage(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*=UTF-8\'en\'%E2%82%AC%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('€ rates.pdf', $cd->parameters['filename']);
	}

	public function testDecodeShouldPreferExtendedParameterValue(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename="EURO rates.pdf"; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('€ rates.pdf', $cd->parameters['filename']);

		$cd = ContentDispositionHeader::decode('attachment; filename*=UTF-8\'\'%E2%82%AC%20rates.pdf; filename="EURO rates.pdf"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayHasKey('filename', $cd->parameters);
		$this->assertEquals('€ rates.pdf', $cd->parameters['filename']);
	}

	public function testDecodeShouldIgnoreInvalidParameterNames(): void {
		$cd = ContentDispositionHeader::decode('attachment; filename*=UTF-8\'\'f%oo.html');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
		$this->assertArrayNotHasKey('filename*', $cd->parameters);

		$cd = ContentDispositionHeader::decode('attachment; filename@="rates.pdf"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
		$this->assertArrayNotHasKey('filename@', $cd->parameters);

		$cd = ContentDispositionHeader::decode('attachment; filename*0="foo."; filename*1="html"');
		$this->assertNotNull($cd);
		$this->assertEquals('attachment', $cd->disposition);
		$this->assertArrayNotHasKey('filename', $cd->parameters);
		$this->assertArrayNotHasKey('filename*0', $cd->parameters);
		$this->assertArrayNotHasKey('filename*1', $cd->parameters);
	}
}