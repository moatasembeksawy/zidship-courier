<?php

namespace App\DTOs\Courier;

/**
 * Waybill label data
 */
final readonly class WaybillLabel
{
    public function __construct(
        public string $content,        // Base64 encoded content or URL
        public string $format = 'pdf', // pdf, zpl, png, etc.
        public string $contentType = 'application/pdf',
        public bool $isUrl = false,    // true if content is a URL, false if base64
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'format' => $this->format,
            'content_type' => $this->contentType,
            'is_url' => $this->isUrl,
        ];
    }
}
