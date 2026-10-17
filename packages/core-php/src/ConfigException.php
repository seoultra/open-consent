<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

final class ConfigException extends \InvalidArgumentException
{
    /** @param list<string> $problems */
    public function __construct(private readonly array $problems)
    {
        parent::__construct('Invalid OpenConsent configuration: ' . implode('; ', $problems));
    }

    /** @return list<string> */
    public function problems(): array
    {
        return $this->problems;
    }
}
