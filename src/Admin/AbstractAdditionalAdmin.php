<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;

abstract class AbstractAdditionalAdmin extends Admin
{
    protected static function createSaveToolbarAction(): DropdownToolbarAction
    {
        return new DropdownToolbarAction(
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
    }
}
