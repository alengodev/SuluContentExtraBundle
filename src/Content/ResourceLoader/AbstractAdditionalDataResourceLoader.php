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
 * To avoid dumping the whole additionalData array onto every item, we attach only the
 * fields the smart_content `properties` config references (e.g. "additionalData.location")
 * — and we attach them nested under an "additionalData" key so the property paths resolve
 * (item.additionalData.location → the "location" alias). Nothing referenced → nothing
 * attached. The enhancement hook never receives `properties`, so AdditionalDataResolver —
 * which does — records the requested fields in AdditionalDataRequestCollector.
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

        if ($resource instanceof AdditionalDataInterface) {
            $uuid = $resource->getResource()->getUuid();

            if ($this->requestCollector->isRequested($uuid)) {
                $data = $resource->getAdditionalData();

                // Attach each referenced field FLAT under its alias. Sulu's property-path
                // mapping cannot resolve underscore keys (additionalData.template_theme → null),
                // so we expose the values directly as content keys (item.<alias>) instead — no
                // nested "additionalData" array is added to the item.
                foreach ($this->requestCollector->requestedMap($uuid) as $alias => $field) {
                    if (\array_key_exists($field, $data)) {
                        $content[$alias] = $data[$field];
                    }
                }
            }
        }

        return ContentView::create($content, $view);
    }
}
