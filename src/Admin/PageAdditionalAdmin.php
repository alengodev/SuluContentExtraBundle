<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;

class PageAdditionalAdmin extends Admin
{
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly string $formKey,
        private readonly string $tabTitle,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$viewCollection->has(PageAdmin::EDIT_FORM_VIEW)) {
            return;
        }

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

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createPreviewFormViewBuilder(PageAdmin::EDIT_FORM_VIEW . '.additional', '/additional')
                ->setResourceKey('pages')
                ->setFormKey($this->formKey)
                ->setTabTitle($this->tabTitle)
                ->setTitleVisible(true)
                ->addToolbarActions([$saveToolbarAction])
                ->addRouterAttributesToFormRequest(['parentId', 'webspace'])
                ->disablePreviewWebspaceChooser()
                ->setPreviewCondition('linkOn == false && shadowOn == false && availableLocales && locale in availableLocales')
                ->setTabCondition('linkOn == false && shadowOn == false')
                ->setTabOrder(45)
                ->setParent(PageAdmin::EDIT_FORM_VIEW),
        );
    }

    public static function getPriority(): int
    {
        return PageAdmin::getPriority() - 1;
    }
}
