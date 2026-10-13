<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
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
     * Strip the toolbar actions the current user may not perform in the given security context.
     *
     * Articles and snippets carry no object permissions ({@see SecuredEntityInterface}), so their
     * resource data contains no `_permissions` and the toolbar visibility conditions
     * (`!_permissions || _permissions.live`, …) always evaluate to "allowed". Sulu therefore
     * filters those toolbars server-side in {@see ContentViewBuilderFactory::createViews()}; the
     * core tabs (seo, excerpt, settings) reuse that filtered toolbar, while this standalone tab
     * must redo the filtering itself. Without it the tab would offer Publish to users who may only
     * request a publish — exactly the mismatch between the Content and Options tab.
     *
     * Pages are object-secured and keep their `_permissions`-based conditions, so they do not use
     * this method.
     *
     * @param list<ToolbarAction> $toolbarActions
     *
     * @return list<ToolbarAction>
     */
    protected static function filterToolbarActionsByContextPermission(
        array $toolbarActions,
        SecurityCheckerInterface $securityChecker,
        string $securityContext,
    ): array {
        $hasEdit = $securityChecker->hasPermission($securityContext, PermissionTypes::EDIT);
        $hasLive = $securityChecker->hasPermission($securityContext, PermissionTypes::LIVE);

        $filtered = [];
        foreach ($toolbarActions as $toolbarAction) {
            $label = $toolbarAction instanceof DropdownToolbarAction
                ? ($toolbarAction->getOptions()['label'] ?? null)
                : null;

            // Save dropdown: requires edit. Without live it keeps "save as draft" and
            // "request for publish" but drops "save and publish" and "publish".
            if ('sulu_admin.save' === $label) {
                if (!$hasEdit) {
                    continue;
                }

                /** @var DropdownToolbarAction $toolbarAction */
                $filtered[] = $hasLive ? $toolbarAction : self::withoutPublishingEntries($toolbarAction);

                continue;
            }

            // Edit dropdown (delete draft / set unpublished): every entry requires live.
            if ('sulu_admin.edit' === $label && !$hasLive) {
                continue;
            }

            $filtered[] = $toolbarAction;
        }

        return $filtered;
    }

    /**
     * Rebuild the save dropdown without its publishing entries ("save and publish" / "publish"),
     * mirroring {@see ContentViewBuilderFactory::withoutPublishingEntries()}.
     */
    private static function withoutPublishingEntries(DropdownToolbarAction $saveAction): DropdownToolbarAction
    {
        $options = $saveAction->getOptions();
        $label = $options['label'] ?? null;
        $icon = $options['icon'] ?? null;

        /** @var list<ToolbarAction> $entries */
        $entries = $options['toolbarActions'] ?? [];

        return new DropdownToolbarAction(
            \is_string($label) ? $label : 'sulu_admin.save',
            \is_string($icon) ? $icon : 'su-save',
            \array_values(\array_filter($entries, static function (ToolbarAction $entry): bool {
                if ('sulu_admin.publish' === $entry->getType()) {
                    return false;
                }

                $entryOptions = $entry->getOptions()['options'] ?? null;

                return !\is_array($entryOptions) || 'publish' !== ($entryOptions['action'] ?? null);
            })),
        );
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
