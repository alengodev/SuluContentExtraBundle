<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

class SnippetAdditionalAdmin extends AbstractAdditionalAdmin
{
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly string $formKey,
        private readonly string $tabTitle,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$viewCollection->has(SnippetAdmin::EDIT_TABS_VIEW)) {
            return;
        }

        // Snippets have no preview object provider (unlike pages/articles), so the edit
        // form tabs are plain FormViews — mirror that here instead of a PreviewFormView.
        $viewCollection->add(
            $this->viewBuilderFactory
                ->createFormViewBuilder(SnippetAdmin::EDIT_TABS_VIEW . '.additional', '/additional')
                ->setResourceKey(SnippetInterface::RESOURCE_KEY)
                ->setFormKey($this->formKey)
                ->setTabTitle($this->tabTitle)
                ->setTitleVisible(true)
                ->addToolbarActions([self::createSaveToolbarAction(), self::createEditToolbarAction()])
                ->setTabOrder(45)
                ->setParent(SnippetAdmin::EDIT_TABS_VIEW),
        );
    }

    public static function getPriority(): int
    {
        return SnippetAdmin::getPriority() - 1;
    }
}
