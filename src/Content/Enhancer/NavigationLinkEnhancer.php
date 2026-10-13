<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Content\Enhancer;

use Sulu\Content\Application\ContentEnhancer\DimensionContentEnhancerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator('sulu_page.page_link_dimension_content_enhancer')]
class NavigationLinkEnhancer implements DimensionContentEnhancerInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly DimensionContentEnhancerInterface $inner,
    ) {
    }

    public function enhance(DimensionContentInterface $dimensionContent): DimensionContentInterface
    {
        if (!$dimensionContent instanceof PageDimensionContentInterface) {
            return $this->inner->enhance($dimensionContent);
        }

        $hasProvider = isset($dimensionContent->getLinkData()['provider']);
        $sourceUuid = $dimensionContent->getResourceId();
        $urlBefore = $dimensionContent->getTemplateData()['url'] ?? null;

        $result = $this->inner->enhance($dimensionContent);

        if ($hasProvider) {
            // `sourceLink` marks a link page, whether its target could be resolved or not.
            // `sourceLinkResolved` tells the two apart: the core enhancer returns the target's
            // content for an internal link, writes a new `url` for any other provider, and
            // leaves the link page untouched when the target is gone (deleted page, missing
            // translation, unknown media, ...).
            $resolved = $result !== $dimensionContent
                || ($result->getTemplateData()['url'] ?? null) !== $urlBefore;

            $result->setTemplateData([
                ...$result->getTemplateData(),
                'sourceLink' => true,
                'sourceLinkResolved' => $resolved,
                'sourceUuid' => $sourceUuid,
            ]);
        }

        return $result;
    }
}
