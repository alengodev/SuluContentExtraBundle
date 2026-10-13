<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\View\PreviewFormViewBuilder;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;

class PageAdditionalAdmin extends AbstractAdditionalAdmin
{
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        private readonly string $formKey,
        private readonly string $tabTitle,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$viewCollection->has(PageAdmin::EDIT_FORM_VIEW)) {
            return;
        }

        // Pages carry object permissions, so plain `!_permissions` does not mean "allowed" here.
        // Same conditions as the edit form of {@see PageAdmin} (the tab exists on saved pages only).
        $toolbarActions = self::createToolbarActions(
            $this->contentViewBuilderFactory,
            PageInterface::class,
            '_permissions && _permissions.edit',
            '_permissions && _permissions.live',
            '(!_permissions || _permissions.review || _permissions.edit || _permissions.live)',
        );

        $viewBuilder = $this->viewBuilderFactory
            ->createPreviewFormViewBuilder(PageAdmin::EDIT_FORM_VIEW . '.additional', '/additional')
            ->setResourceKey('pages')
            ->setFormKey($this->formKey)
            ->setTabTitle($this->tabTitle)
            ->setTitleVisible(true)
            ->addToolbarActions($toolbarActions)
            ->addRouterAttributesToFormRequest(['parentId', 'webspace'])
            ->setPreviewCondition('linkOn == false && shadowOn == false && availableLocales && locale in availableLocales')
            ->setTabCondition('linkOn == false && shadowOn == false')
            ->setTabOrder(45)
            ->setParent(PageAdmin::EDIT_FORM_VIEW);

        // Not part of PreviewFormViewBuilderInterface, only of the concrete builder.
        if ($viewBuilder instanceof PreviewFormViewBuilder) {
            $viewBuilder->disablePreviewWebspaceChooser();
        }

        $viewCollection->add($viewBuilder);
    }

    public static function getPriority(): int
    {
        return PageAdmin::getPriority() - 1;
    }
}
