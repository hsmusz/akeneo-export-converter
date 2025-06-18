<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\Services;

class Locales
{
    public function __construct(
        private readonly array $currencies,
    ) {
    }

    public function detectCurrency(string $language): string
    {
        return $this->currencies[$language] ?? $this->currencies['default'];
    }
}
