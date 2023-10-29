<?php namespace Obie\Smtp;
use Obie\Http\Multipart\Segment;
use Obie\Http\Mime;

class Attachment {
	protected Mime $content_type;

	function __construct(
		public string $filename,
		public string $data,
		string|Mime|null $mime = null,
	) {
		$this->setContentType($mime ?? Mime::getByFilename($filename));
	}

	public function getContentType(): Mime {
		return $this->content_type;
	}

	public function setContentType(string|Mime $mime): static {
		if (is_string($mime)) {
			$mime = Mime::decode($mime);
		}
		if (empty($mime->getParameter('charset'))) {
			$mime->setParameter('charset', 'utf-8');
		}
		if (empty($mime->getParameter('name'))) {
			$mime->setParameter('name', $this->filename);
		}
		if (!$mime) $mime = new Mime('application', 'octet-stream');
		$this->content_type = $mime;
		return $this;
	}

	public function toSegment(): Segment {
		return new Segment($this->data, [
			'content-type' => $this->content_type->encode(),
			'content-disposition' => sprintf('attachment; filename="%s"', addslashes($this->filename)),
			'content-transfer-encoding' => 'base64',
		]);
	}
}