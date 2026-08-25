<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\ResourceLoader;

use Alengo\SuluContentExtraBundle\Content\SmartContent\AdditionalDataRequestCollector;
use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderContentViewEnhancementInterface;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderInterface;

/**
 * Wraps a Sulu resource loader to expose the additional-data tab on smart-content items.
 *
 * Sulu resolves each smart-content item in a nested pass whose result does NOT surface
 * custom content_resolver output (like the "additional" extension) — so the smart_content
 * `properties` mapping cannot reach it. The content-view enhancement hook, however,
 * receives the aggregated DimensionContent (which carries the additional data) and its
 * content is merged onto each resolved item.
 *
 * To avoid attaching additionalData to every item unconditionally, we only attach it when
 * the smart_content `properties` config references it (e.g. a param value "additionalData"
 * or "additionalData.location"). The enhancement hook itself never receives `properties`,
 * so AdditionalDataResolver — which does — flags the resource via AdditionalDataRequestCollector.
 * Result in templates: `item.additionalData.location`.
 *
 * Concrete subclasses only bind the loader key (article, page, ...) via getKey().
 */
abstract class AbstractAdditionalDataResourceLoader implements ResourceLoaderContentViewEnhancementInterface
{
    public function __construct(
        private readonly ResourceLoaderInterface $inner,
        private readonly AdditionalDataRequestCollector $requestCollector,
    ) {
    }

    public function load(array $ids, ?string $locale, array $params = []): array
    {
        return $this->inner->load($ids, $locale, $params);
    }

    public function resolveContentViewEnhancement(mixed $resource): ContentView
    {
        $content = [];
        $view = [];

        // Preserve the wrapped loader's own enhancement (uuid, template, authored, ...).
        if ($this->inner instanceof ResourceLoaderContentViewEnhancementInterface) {
            $enhancement = $this->inner->resolveContentViewEnhancement($resource);
            /** @var array<string, mixed> $content */
            $content = (array) $enhancement->getContent();
            $view = $enhancement->getView();
        }

        if (
            $resource instanceof AdditionalDataInterface
            && $this->requestCollector->isRequested($resource->getResource()->getUuid())
        ) {
            $content['additionalData'] = $resource->getAdditionalData();
        }

        return ContentView::create($content, $view);
    }
}
