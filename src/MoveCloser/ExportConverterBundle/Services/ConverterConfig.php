<?php

namespace MoveCloser\ExportConverterBundle\Services;

use Kernel;
use Symfony\Component\HttpFoundation\Request;

class ConverterConfig
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly array $attributes,
        private readonly array $languages,
        private readonly array $templatesMap,
    ) {
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

    public function generateMap(): array
    {
        // @todo: add cache

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

        return $map;
    }

    public function languages(): array
    {
        return $this->languages;
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
