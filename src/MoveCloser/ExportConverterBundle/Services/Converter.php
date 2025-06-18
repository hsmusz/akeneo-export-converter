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

    /**
     * Determines the appropriate currency code for a given language identifier.
     */
    public function detectCurrency(string $language): string
    {
        return $this->languages->detectCurrency($language);
    }

    /**
     * Retrieves a mapped or translated attribute value based on the provided column identifier, value, and language.
     */
    public function getMappedAttribute($column, $value, $language): mixed
    {
        return $this->attributes->getMappedAttribute($column, $value, $language);
    }

    /**
     * Selects the appropriate converter class for a given file based on its name.
     * Iterates through the template-to-class mapping and returns the class associated
     * with the first matching template prefix.
     */
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
