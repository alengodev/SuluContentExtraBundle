<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\Resolver;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;

final class AdditionalDataResolver implements ResolverInterface
{
    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof AdditionalDataInterface) {
            return null;
        }

        return ContentView::create(
            $dimensionContent->getAdditionalData(),
            [],
        );
    }
}
