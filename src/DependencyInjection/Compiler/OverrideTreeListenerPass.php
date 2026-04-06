<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection\Compiler;

use Alengo\SuluContentExtraBundle\Doctrine\Hydrator\SafeTreeObjectHydrator;
use Alengo\SuluContentExtraBundle\Doctrine\Tree\SuluPageAwareTreeListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Overrides stof's tree listener and Doctrine's sulu_page_tree hydrator after all
 * extensions have loaded, ensuring the class overrides take effect regardless of
 * bundle registration order.
 */
class OverrideTreeListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->overrideTreeListener($container);
        $this->overrideTreeHydrator($container);
    }

    private function overrideTreeListener(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('stof_doctrine_extensions.listener.tree')) {
            return;
        }

        $pageEntityClass = $container->getParameter('alengo_content_extra.page_entity_class');

        $container->getDefinition('stof_doctrine_extensions.listener.tree')
            ->setClass(SuluPageAwareTreeListener::class)
            ->addMethodCall('setPageEntityClass', [$pageEntityClass]);
    }

    private function overrideTreeHydrator(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds('doctrine.orm.configuration')) as $serviceId) {
            $container->getDefinition($serviceId)
                ->addMethodCall('addCustomHydrationMode', ['sulu_page_tree', SafeTreeObjectHydrator::class]);
        }
    }
}
