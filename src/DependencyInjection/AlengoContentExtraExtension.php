<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

use Alengo\SuluContentExtraBundle\Admin\ArticleAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Admin\PageAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Content\DataMapper\AdditionalDataMapper;
use Alengo\SuluContentExtraBundle\Content\Merger\AdditionalDataMerger;
use Alengo\SuluContentExtraBundle\Content\Normalizer\AdditionalDataNormalizer;
use Alengo\SuluContentExtraBundle\Content\Resolver\AdditionalDataResolver;
use Alengo\SuluContentExtraBundle\Doctrine\EventSubscriber\InheritedAssociationDeclaredFixerSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class AlengoContentExtraExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $pageClass = \Alengo\SuluContentExtraBundle\Entity\Page::class;
        $pageContentClass = \Alengo\SuluContentExtraBundle\Entity\PageDimensionContent::class;
        $articleClass = \Alengo\SuluContentExtraBundle\Entity\Article::class;
        $articleContentClass = \Alengo\SuluContentExtraBundle\Entity\ArticleDimensionContent::class;
        $articleEnabled = true;

        foreach ($configs as $c) {
            if (isset($c['page']['page_class'])) {
                $pageClass = $c['page']['page_class'];
            }
            if (isset($c['page']['entity_class'])) {
                $pageContentClass = $c['page']['entity_class'];
            }
            if (isset($c['article']['article_class'])) {
                $articleClass = $c['article']['article_class'];
            }
            if (isset($c['article']['entity_class'])) {
                $articleContentClass = $c['article']['entity_class'];
            }
            if (isset($c['article']['enabled'])) {
                $articleEnabled = $c['article']['enabled'];
            }
        }

        if ($container->hasExtension('sulu_page')) {
            $container->prependExtensionConfig('sulu_page', [
                'objects' => [
                    'page' => ['model' => $pageClass],
                    'page_content' => ['model' => $pageContentClass],
                ],
            ]);
        }

        if ($articleEnabled && $container->hasExtension('sulu_article')) {
            $container->prependExtensionConfig('sulu_article', [
                'objects' => [
                    'article' => ['model' => $articleClass],
                    'article_content' => ['model' => $articleContentClass],
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Parameter for OverrideTreeListenerPass
        $container->setParameter('alengo_content_extra.page_entity_class', $config['page']['page_class']);

        // Doctrine: fix inherited association mappings for Page/Article extension
        $subscriberDef = new Definition(InheritedAssociationDeclaredFixerSubscriber::class);
        $subscriberDef->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
        $container->setDefinition(InheritedAssociationDeclaredFixerSubscriber::class, $subscriberDef);

        // Merger
        $mergerDef = new Definition(AdditionalDataMerger::class);
        $mergerDef->addTag('sulu_content.merger', ['priority' => 20]);
        $container->setDefinition(AdditionalDataMerger::class, $mergerDef);

        // Normalizer
        $normalizerDef = new Definition(AdditionalDataNormalizer::class);
        $normalizerDef->addTag('sulu_content.normalizer', ['priority' => 20]);
        $container->setDefinition(AdditionalDataNormalizer::class, $normalizerDef);

        // Resolver
        $resolverDef = new Definition(AdditionalDataResolver::class);
        $resolverDef->addTag('sulu_content.content_resolver', ['type' => 'additional']);
        $container->setDefinition(AdditionalDataResolver::class, $resolverDef);

        // Page DataMapper
        $pageMapperDef = new Definition(AdditionalDataMapper::class);
        $pageMapperDef->addArgument($config['page']['entity_class']);
        $pageMapperDef->addArgument($config['page']['unlocalized_keys']);
        $pageMapperDef->addArgument($config['page']['localized_keys']);
        $pageMapperDef->addTag('sulu_content.data_mapper', ['priority' => 64]);
        $container->setDefinition('alengo_content_extra.page_data_mapper', $pageMapperDef);

        // Page Admin tab
        $pageAdminDef = new Definition(PageAdditionalAdmin::class);
        $pageAdminDef->addArgument(new Reference('sulu_admin.view_builder_factory'));
        $pageAdminDef->addArgument($config['page']['form_key']);
        $pageAdminDef->addArgument($config['page']['tab_title']);
        $pageAdminDef->addTag('sulu.admin');
        $pageAdminDef->addTag('sulu.context', ['context' => 'admin']);
        $container->setDefinition(PageAdditionalAdmin::class, $pageAdminDef);

        // Article (optional)
        if ($config['article']['enabled']) {
            $articleMapperDef = new Definition(AdditionalDataMapper::class);
            $articleMapperDef->addArgument($config['article']['entity_class']);
            $articleMapperDef->addArgument($config['article']['unlocalized_keys']);
            $articleMapperDef->addArgument($config['article']['localized_keys']);
            $articleMapperDef->addTag('sulu_content.data_mapper', ['priority' => 64]);
            $container->setDefinition('alengo_content_extra.article_data_mapper', $articleMapperDef);

            $articleAdminDef = new Definition(ArticleAdditionalAdmin::class);
            $articleAdminDef->addArgument(new Reference('sulu_admin.view_builder_factory'));
            $articleAdminDef->addArgument(new Reference('sulu_admin.metadata_group_provider'));
            $articleAdminDef->addArgument($config['article']['form_key']);
            $articleAdminDef->addArgument($config['article']['tab_title']);
            $articleAdminDef->addTag('sulu.admin');
            $articleAdminDef->addTag('sulu.context', ['context' => 'admin']);
            $container->setDefinition(ArticleAdditionalAdmin::class, $articleAdminDef);
        }
    }

    public function getAlias(): string
    {
        return 'alengo_content_extra';
    }
}
