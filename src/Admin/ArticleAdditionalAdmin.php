<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;

class ArticleAdditionalAdmin extends AbstractAdditionalAdmin
{
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly GroupProviderInterface $groupProvider,
        private readonly string $formKey,
        private readonly string $tabTitle,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        foreach ($this->groupProvider->getGroups() as $group) {
            $parentView = ArticleAdmin::EDIT_TABS_VIEW . '_' . $group->identifier;

            if (!$viewCollection->has($parentView)) {
                continue;
            }

            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createPreviewFormViewBuilder($parentView . '.additional', '/additional')
                    ->setResourceKey(ArticleInterface::RESOURCE_KEY)
                    ->setFormKey($this->formKey)
                    ->setTabTitle($this->tabTitle)
                    ->setTitleVisible(true)
                    ->addToolbarActions([self::createSaveToolbarAction()])
                    ->setPreviewCondition('availableLocales && locale in availableLocales')
                    ->setTabOrder(45)
                    ->setParent($parentView),
            );
        }
    }

    public static function getPriority(): int
    {
        return ArticleAdmin::getPriority() - 1;
    }
}
