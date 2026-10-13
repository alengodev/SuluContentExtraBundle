<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

class SnippetAdditionalAdmin extends AbstractAdditionalAdmin
{
    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        private readonly GroupProviderInterface $groupProvider,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly string $formKey,
        private readonly string $tabTitle,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        // Sulu 3.1 groups snippets like articles, one edit view per group
        // (`sulu_snippet.snippet.edit_tabs_<group>`); 3.0.x had a single ungrouped view.
        // Each view is keyed to the security context of its group (same resolution as SnippetAdmin).
        $groups = $this->groupProvider->getGroups(SnippetInterface::TEMPLATE_TYPE);
        $viewContexts = [SnippetAdmin::EDIT_TABS_VIEW => SnippetAdmin::SECURITY_CONTEXT];
        foreach ($groups as $group) {
            $viewContexts[SnippetAdmin::EDIT_TABS_VIEW . '_' . $group->identifier] =
                (1 === \count($groups) || GroupProviderInterface::DEFAULT_GROUP === $group->identifier)
                    ? SnippetAdmin::SECURITY_CONTEXT
                    : SnippetAdmin::getSnippetSecurityContext($group->identifier);
        }

        foreach ($viewContexts as $parentView => $securityContext) {
            if (!$viewCollection->has($parentView)) {
                continue;
            }

            $toolbarActions = self::filterToolbarActionsByContextPermission(
                self::createToolbarActions($this->contentViewBuilderFactory, SnippetInterface::class),
                $this->securityChecker,
                $securityContext,
            );

            // Snippets have no preview object provider (unlike pages/articles), so the edit
            // form tabs are plain FormViews — mirror that here instead of a PreviewFormView.
            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createFormViewBuilder($parentView . '.additional', '/additional')
                    ->setResourceKey(SnippetInterface::RESOURCE_KEY)
                    ->setFormKey($this->formKey)
                    ->setTabTitle($this->tabTitle)
                    ->setTitleVisible(true)
                    ->addToolbarActions($toolbarActions)
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
