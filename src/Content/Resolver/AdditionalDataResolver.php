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
    public function __construct(
        private readonly AdditionalDataRequestCollector $requestCollector,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof AdditionalDataInterface) {
            return null;
        }

        // Smart-content items pass their `properties` config here. When it references
        // additionalData, flag this resource so the resource-loader enhancement attaches
        // the data to the item (the enhancement itself never sees `properties`).
        if (null !== $properties && $this->referencesAdditionalData($properties)) {
            $this->requestCollector->request($dimensionContent->getResource()->getUuid());
        }

        return ContentView::create(
            $dimensionContent->getAdditionalData(),
            [],
        );
    }

    /**
     * @param array<string, string> $properties alias => property path
     */
    private function referencesAdditionalData(array $properties): bool
    {
        foreach ($properties as $path) {
            if (\is_string($path) && ('additionalData' === $path || \str_starts_with($path, 'additionalData.'))) {
                return true;
            }
        }

        return false;
    }
}
