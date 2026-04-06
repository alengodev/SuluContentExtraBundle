<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;

class ArticleAdditionalAdmin extends Admin
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
        $saveToolbarAction = new DropdownToolbarAction(
            'sulu_admin.save',
            'su-save',
            [
                new ToolbarAction(
                    'sulu_admin.save',
                    [
                        'label' => 'sulu_admin.save_draft',
                        'options' => ['action' => 'draft'],
                        'visible_condition' => '(!_permissions || _permissions.edit)',
                    ],
                ),
                new ToolbarAction(
                    'sulu_admin.save',
                    [
                        'label' => 'sulu_admin.save_publish',
                        'options' => ['action' => 'publish'],
                        'visible_condition' => '(!_permissions || _permissions.edit) && (!_permissions || _permissions.live)',
                    ],
                ),
                new ToolbarAction(
                    'sulu_admin.publish',
                    [
                        'visible_condition' => '(!_permissions || _permissions.live)',
                    ],
                ),
            ],
        );

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
                    ->addToolbarActions([$saveToolbarAction])
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
