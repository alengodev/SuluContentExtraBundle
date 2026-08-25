<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\Resolver;

use Alengo\SuluContentExtraBundle\Content\SmartContent\AdditionalDataRequestCollector;
use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;

final class AdditionalDataResolver implements ResolverInterface
{
    private const PREFIX = 'additionalData.';

    public function __construct(
        private readonly AdditionalDataRequestCollector $requestCollector,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof AdditionalDataInterface) {
            return null;
        }

        // Smart-content items pass their `properties` config here. Record which additionalData
        // fields it references so the resource-loader enhancement attaches exactly those to the
        // item (the enhancement itself never sees `properties`). Nothing referenced → nothing
        // attached.
        if (null !== $properties) {
            $map = $this->parseRequestedFields($properties);
            if ([] !== $map) {
                $this->requestCollector->request($dimensionContent->getResource()->getUuid(), $map);
            }
        }

        return ContentView::create(
            $dimensionContent->getAdditionalData(),
            [],
        );
    }

    /**
     * Maps each `properties` param that points at an additionalData field to
     * alias => field, e.g. <param name="theme" value="additionalData.template_theme"/>
     * becomes ['theme' => 'template_theme'].
     *
     * @param array<string, string> $properties alias => property path
     *
     * @return array<string, string> alias => additionalData field
     */
    private function parseRequestedFields(array $properties): array
    {
        $map = [];

        foreach ($properties as $alias => $path) {
            if (!\is_string($alias) || !\is_string($path) || !\str_starts_with($path, self::PREFIX)) {
                continue;
            }

            // Only the first path segment is a real additionalData field.
            $field = \explode('.', \substr($path, \strlen(self::PREFIX)))[0];
            if ('' !== $field) {
                $map[$alias] = $field;
            }
        }

        return $map;
    }
}
