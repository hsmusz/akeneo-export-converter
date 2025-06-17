<?php

namespace MoveCloser\ExportConverterBundle\Services;

use Kernel;
use Symfony\Component\HttpFoundation\Request;

class AttributeMap
{
    private const ATTRIBUTES = [
        'care_area',
        'skin',
        'need',
        'type',
        'day_night',
        'active_ingredients',
        'line',
        'color',
        'action',
        'tax_id',
        'Visibility',
        'website_ids',
        'locale_ids',
        'Marka',
        'Probki_minisy',
    ];

    public function __construct(
        private readonly Kernel $kernel,
    ) {
    }

    public function generate(): array
    {
        // @todo: add cache

        $map = [];
        foreach (self::ATTRIBUTES as $attribute) {
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
