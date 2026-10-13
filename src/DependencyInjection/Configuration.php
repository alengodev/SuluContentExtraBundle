<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

use Alengo\SuluContentExtraBundle\Entity\Article;
use Alengo\SuluContentExtraBundle\Entity\ArticleDimensionContent;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use Alengo\SuluContentExtraBundle\Entity\Snippet;
use Alengo\SuluContentExtraBundle\Entity\SnippetDimensionContent;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
                    'page_class',
                    Page::class,
                    PageDimensionContent::class,
                    'page_additional_data',
                ))
                ->append($this->buildContentNode(
                    'article',
                    'article_class',
                    Article::class,
                    ArticleDimensionContent::class,
                    'article_additional_data',
                ))
                ->append($this->buildContentNode(
                    'snippet',
                    'snippet_class',
                    Snippet::class,
                    SnippetDimensionContent::class,
                    'snippet_additional_data',
                ))
            ->end();

        return $treeBuilder;
    }

    private function buildContentNode(
        string $name,
        string $rootClassKey,
        string $defaultRootClass,
        string $defaultEntityClass,
        string $defaultFormKey,
    ): ArrayNodeDefinition {
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
            ->validate()
                // The merger lays the localized data over the unlocalized one, so a key in both
                // lists would silently lose its unlocalized value.
                ->ifTrue(static fn (mixed $config): bool => [] !== self::duplicateKeys($config))
                ->then(static function (mixed $config) use ($name): never {
                    throw new InvalidConfigurationException(\sprintf(
                        'The keys "%s" of "alengo_content_extra.%s" are listed in both "unlocalized_keys" and "localized_keys". A key is stored either unlocalized or localized.',
                        \implode('", "', self::duplicateKeys($config)),
                        $name,
                    ));
                })
            ->end();

        return $node;
    }

    /**
     * @return list<string>
     */
    private static function duplicateKeys(mixed $config): array
    {
        if (!\is_array($config)
            || !\is_array($config['unlocalized_keys'] ?? null)
            || !\is_array($config['localized_keys'] ?? null)
        ) {
            return [];
        }

        $unlocalized = \array_filter($config['unlocalized_keys'], \is_string(...));
        $localized = \array_filter($config['localized_keys'], \is_string(...));

        $duplicates = \array_intersect($unlocalized, $localized);

        return \array_values(\array_unique($duplicates));
    }
}
