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

        $result = $this->inner->enhance($dimensionContent);

        if ($hasProvider && $result instanceof PageDimensionContentInterface) {
            $result->setTemplateData([
                ...$result->getTemplateData(),
                'sourceLink' => true,
                'sourceUuid' => $sourceUuid,
            ]);
        }

        return $result;
    }
}
