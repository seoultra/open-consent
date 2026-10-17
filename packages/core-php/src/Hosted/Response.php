<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent\Hosted;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(public readonly int $status, public readonly array $headers, public readonly string $body)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(int $status, array $data, array $headers): self
    {
        return new self($status, $headers, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}
