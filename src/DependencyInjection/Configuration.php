<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

use Alengo\SuluContentExtraBundle\Entity\Article;
use Alengo\SuluContentExtraBundle\Entity\ArticleDimensionContent;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alengo_content_extra');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->buildContentNode(
                    'page',
                    Page::class,
                    PageDimensionContent::class,
                    'page_additional_data',
                ))
                ->append($this->buildContentNode(
                    'article',
                    Article::class,
                    ArticleDimensionContent::class,
                    'article_additional_data',
                ))
            ->end()
        ;

        return $treeBuilder;
    }

    private function buildContentNode(
        string $name,
        string $defaultRootClass,
        string $defaultEntityClass,
        string $defaultFormKey,
    ): ArrayNodeDefinition {
        $rootClassKey = 'page' === $name ? 'page_class' : 'article_class';

        $node = (new TreeBuilder($name))->getRootNode();

        $node
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode($rootClassKey)
                    ->defaultValue($defaultRootClass)
                ->end()
                ->scalarNode('entity_class')
                    ->defaultValue($defaultEntityClass)
                ->end()
                ->scalarNode('form_key')
                    ->defaultValue($defaultFormKey)
                ->end()
                ->scalarNode('tab_title')
                    ->defaultValue('sulu_admin.app.additional_data')
                ->end()
                ->arrayNode('unlocalized_keys')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('localized_keys')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
