<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\Services;

class Converter
{
    public function __construct(
        private readonly array $templatesMap,
        private readonly Attributes $attributes,
        private readonly Locales $languages,

    ) {
    }

    public function detectCurrency(string $language): string
    {
        return $this->languages->detectCurrency($language);
    }

    public function getMappedAttribute($column, $value, $language): mixed
    {
        return $this->attributes->getMappedAttribute($column, $value, $language);
    }

    public function matchConverter(string $file): ?string
    {
        foreach ($this->templatesMap as $tpl => $class) {
            if (!str_starts_with(basename($file), $tpl)) {
                continue;
            }

            return $class;
        }

        return null;
    }

}
