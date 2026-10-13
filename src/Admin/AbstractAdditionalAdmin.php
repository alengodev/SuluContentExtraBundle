<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowResolverInterface;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;

abstract class AbstractAdditionalAdmin extends Admin
{
    /**
     * The toolbar of the Additional Data tab, matching the standard content tabs.
     *
     * Sulu 3.1 replaced the plain save dropdown by the review flow (request for publish,
     * approval overlay, everything hidden while a request is open). Taking it from the
     * {@see ContentViewBuilderFactoryInterface} keeps this tab in line with the core tabs;
     * on Sulu 3.0.x, which has no review flow, the previous save dropdown is used.
     *
     * The conditions are only passed through on 3.1; null keeps the factory's defaults.
     *
     * @template T of DimensionContentInterface
     *
     * @param class-string<ContentRichEntityInterface<T>> $contentRichEntityClass
     *
     * @return list<ToolbarAction>
     */
    protected static function createToolbarActions(
        ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        string $contentRichEntityClass,
        ?string $saveVisibleCondition = null,
        ?string $publishVisibleCondition = null,
        ?string $reviewVisibleCondition = null,
    ): array {
        // The review flow (and with it getWorkflowTransitionRequestToolbarActions()) arrived in 3.1.
        if (!\interface_exists(RequestWorkflowResolverInterface::class)) {
            return [self::createSaveToolbarAction(), self::createEditToolbarAction()];
        }

        $conditions = \array_filter([
            'saveVisibleCondition' => $saveVisibleCondition,
            'publishVisibleCondition' => $publishVisibleCondition,
            'reviewVisibleCondition' => $reviewVisibleCondition,
        ], static fn (?string $condition): bool => null !== $condition);

        $workflowToolbarActions = $contentViewBuilderFactory->getWorkflowTransitionRequestToolbarActions(
            $contentRichEntityClass,
            ...$conditions,
        );

        return [
            $workflowToolbarActions['save'],
            $workflowToolbarActions['approval'],
            self::createEditToolbarAction(),
        ];
    }

    /**
     * The save dropdown of Sulu 3.0.x, used as fallback by {@see createToolbarActions()}.
     */
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

    /**
     * The "Edit" dropdown (delete draft / set unpublished) that Sulu shows on the standard
     * content tabs (seo, excerpt, settings). Mirrors
     * {@see \Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactory::getDefaultToolbarActions}.
     */
    protected static function createEditToolbarAction(): DropdownToolbarAction
    {
        return new DropdownToolbarAction(
            'sulu_admin.edit',
            'su-pen',
            [
                new ToolbarAction(
                    'sulu_admin.delete_draft',
                    [
                        'visible_condition' => '(!_permissions || _permissions.live)',
                    ],
                ),
                new ToolbarAction(
                    'sulu_admin.set_unpublished',
                    [
                        'visible_condition' => '(!_permissions || _permissions.live)',
                    ],
                ),
            ],
        );
    }
}
