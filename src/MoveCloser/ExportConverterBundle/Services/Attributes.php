<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\Services;

use Kernel;
use Symfony\Component\HttpFoundation\Request;

class Attributes
{
    protected array $map = [];

    public function __construct(
        private readonly Kernel $kernel,
        private readonly array $attributes,
    ) {
    }

    public static function changeWeight(mixed $value, int|float $num): mixed
    {
        if (empty($value) || empty($num)) {
            return $value;
        }

        return $value * $num;
    }

    public static function cleanText(mixed $value): string
    {
        $pattern = '/&nbsp;/';
        $replacement = '';
        $result = preg_replace($pattern, $replacement, $value ?? '');

        return strip_tags($result ?? '');
    }

    public static function sayYes(mixed $value, string $needle, string $response): string
    {
        return str_contains($value ?? '', $needle) ? $response : '';
    }

    public static function stripTagsAndBr2Nl(?string $value): string
    {
        return strip_tags(preg_replace('/<br\s?\/?>/ius', "\n", htmlspecialchars_decode($value ?? '')));
    }

    public function getMappedAttribute($columnIndex, mixed $value, string $language): mixed
    {
        if (!$this->has($columnIndex)) {
            echo sprintf(
                    "- Err0r: cant find labels definitions for field [%s].\n",
                    $columnIndex
                ) . PHP_EOL;

            return $value;
        }

        if (!$this->has($columnIndex, $value)) {
            echo sprintf(
                    "- Err0r: cant find label for field [%s] and value [%s].\n",
                    $columnIndex,
                    $value
                ) . PHP_EOL;

            return $value;
        }

        if (!$this->has($columnIndex, $value, $language)) {
            echo sprintf(
                    "- Err0r: cant find label translation for field [%s], value [%s] and lang [%s].\n",
                    $columnIndex,
                    $value,
                    $language
                ) . PHP_EOL;

            return $value;
        }

        return $this->map[$columnIndex][$value][$language];
    }

    public function has(...$args): bool
    {
        $tmpMap = $this->generateMap();
        foreach ($args as $key) {
            if (!is_array($tmpMap) || !array_key_exists($key, $tmpMap) || $tmpMap[$key] === null) {
                return false;
            }
            $tmpMap = $tmpMap[$key];
        }

        return true;
    }

    private function generateMap(): array
    {
        if (empty($this->map)) {
            $map = [];
            foreach ($this->attributes as $attribute) {
                $map[$attribute] = [];
                $response = $this->generateSelectAttributeMapApiCall($attribute, 1, 1);

                $count = (int) $response['items_count'];

                for ($i = 1; $i <= ceil($count / 100); $i++) {
                    $response = $this->generateSelectAttributeMapApiCall($attribute, 100, $i);
                    foreach ($response['_embedded']['items'] as $apiMap) {
                        $map[$attribute][$apiMap['code']] = $apiMap['labels'];
                    }
                }
            }

            $this->map = $map;
        }

        return $this->map;
    }

    private function generateSelectAttributeMapApiCall($attribute, $limit, $page = 1)
    {
        $container = $this->kernel->getContainer();

        /** @var \Akeneo\Pim\Structure\Bundle\Controller\ExternalApi\AttributeOptionController $attributeOptionController */
        $attributeOptionController = $container->get('pim_api.controller.attribute_option');

        $jsonResponse = $attributeOptionController->listAction(
            new Request([
                'limit' => $limit,
                'page' => $page,
                'with_count' => 'true',
            ]),
            $attribute
        );

        return json_decode($jsonResponse->getContent(), true);
    }

}
