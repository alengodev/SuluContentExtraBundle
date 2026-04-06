<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Doctrine\Tree;

use Doctrine\Persistence\ObjectManager;
use Gedmo\Tree\Strategy;
use Gedmo\Tree\TreeListener;
use Sulu\Page\Domain\Model\Page as SuluPage;

/**
 * Fixes Gedmo's TreeListener for Sulu's mapped-superclass Page entity.
 *
 * Sulu\Page\Domain\Model\Page is declared as a <mapped-superclass> in its ORM XML.
 * Gedmo's ExtensionMetadataFactory explicitly returns [] for mapped-superclasses
 * (isMappedSuperclass guard), so getConfiguration() always returns [] for SuluPage.
 *
 * Whenever code calls getConfiguration() or getStrategy() with SuluPage::class,
 * we delegate to the configured concrete Page entity class.
 */
class SuluPageAwareTreeListener extends TreeListener
{
    private string $pageEntityClass = '';

    public function setPageEntityClass(string $pageEntityClass): void
    {
        $this->pageEntityClass = $pageEntityClass;
    }

    public function getConfiguration(ObjectManager $objectManager, $class): array
    {
        if (SuluPage::class === $class) {
            return parent::getConfiguration($objectManager, $this->pageEntityClass);
        }

        return parent::getConfiguration($objectManager, $class);
    }

    public function getStrategy(ObjectManager $om, $class): Strategy
    {
        if (SuluPage::class === $class) {
            return parent::getStrategy($om, $this->pageEntityClass);
        }

        return parent::getStrategy($om, $class);
    }
}
