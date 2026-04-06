<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\Resolver;

use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;

/**
 * Exposes link-type marker fields (set by NavigationLinkEnhancer) to the
 * navigation tree resolver. Since TemplateResolver drops keys not defined in
 * form metadata, these fields are routed through a separate content section.
 */
class NavigationLinkTypeResolver implements ResolverInterface
{
    private const array SUPPORTED_PROPERTIES = ['sourceLink', 'sourceUuid'];

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof PageDimensionContentInterface || null === $properties) {
            return null;
        }

        $templateData = $dimensionContent->getTemplateData();
        $data = [];

        foreach ($properties as $key => $value) {
            if (\in_array($value, self::SUPPORTED_PROPERTIES, true) && \array_key_exists($value, $templateData)) {
                $data[$key] = $templateData[$value];
            }
        }

        return [] !== $data ? ContentView::create($data, []) : null;
    }
}
