<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

class SnippetAdditionalAdmin extends AbstractAdditionalAdmin
{
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        private readonly GroupProviderInterface $groupProvider,
        private readonly string $formKey,
        private readonly string $tabTitle,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        // Sulu 3.1 groups snippets like articles, one edit view per group
        // (`sulu_snippet.snippet.edit_tabs_<group>`); 3.0.x had a single ungrouped view.
        $parentViews = [SnippetAdmin::EDIT_TABS_VIEW];
        foreach ($this->groupProvider->getGroups(SnippetInterface::TEMPLATE_TYPE) as $group) {
            $parentViews[] = SnippetAdmin::EDIT_TABS_VIEW . '_' . $group->identifier;
        }

        foreach ($parentViews as $parentView) {
            if (!$viewCollection->has($parentView)) {
                continue;
            }

            // Snippets have no preview object provider (unlike pages/articles), so the edit
            // form tabs are plain FormViews — mirror that here instead of a PreviewFormView.
            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createFormViewBuilder($parentView . '.additional', '/additional')
                    ->setResourceKey(SnippetInterface::RESOURCE_KEY)
                    ->setFormKey($this->formKey)
                    ->setTabTitle($this->tabTitle)
                    ->setTitleVisible(true)
                    ->addToolbarActions(self::createToolbarActions($this->contentViewBuilderFactory, SnippetInterface::class))
                    ->setTabOrder(45)
                    ->setParent($parentView),
            );
        }
    }

    public static function getPriority(): int
    {
        return SnippetAdmin::getPriority() - 1;
    }
}
