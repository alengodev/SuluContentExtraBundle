<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Admin;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;

class ArticleAdditionalAdmin extends AbstractAdditionalAdmin
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
        $groups = $this->groupProvider->getGroups(ArticleInterface::TEMPLATE_TYPE);

        foreach ($groups as $group) {
            $parentView = ArticleAdmin::EDIT_TABS_VIEW . '_' . $group->identifier;

            if (!$viewCollection->has($parentView)) {
                continue;
            }

            // Same per-group security context resolution as {@see ArticleAdmin}.
            $securityContext = (1 === \count($groups) || GroupProviderInterface::DEFAULT_GROUP === $group->identifier)
                ? ArticleAdmin::SECURITY_CONTEXT
                : ArticleAdmin::getArticleSecurityContext($group->identifier);

            $toolbarActions = self::filterToolbarActionsByContextPermission(
                self::createToolbarActions($this->contentViewBuilderFactory, ArticleInterface::class),
                $this->securityChecker,
                $securityContext,
            );

            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createPreviewFormViewBuilder($parentView . '.additional', '/additional')
                    ->setResourceKey(ArticleInterface::RESOURCE_KEY)
                    ->setFormKey($this->formKey)
                    ->setTabTitle($this->tabTitle)
                    ->setTitleVisible(true)
                    ->addToolbarActions($toolbarActions)
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
