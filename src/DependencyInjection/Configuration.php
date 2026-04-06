<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

use Alengo\SuluContentExtraBundle\Entity\Article;
use Alengo\SuluContentExtraBundle\Entity\ArticleDimensionContent;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
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
                ->arrayNode('page')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('page_class')
                            ->defaultValue(Page::class)
                        ->end()
                        ->scalarNode('entity_class')
                            ->defaultValue(PageDimensionContent::class)
                        ->end()
                        ->scalarNode('form_key')
                            ->defaultValue('page_additional_data')
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
                ->end()
                ->arrayNode('article')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('article_class')
                            ->defaultValue(Article::class)
                        ->end()
                        ->scalarNode('entity_class')
                            ->defaultValue(ArticleDimensionContent::class)
                        ->end()
                        ->scalarNode('form_key')
                            ->defaultValue('article_additional_data')
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
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
