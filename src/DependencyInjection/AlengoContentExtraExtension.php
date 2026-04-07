<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

use Alengo\SuluContentExtraBundle\Admin\ArticleAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Admin\PageAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Content\DataMapper\AdditionalDataMapper;
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
        $config = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig($this->getAlias()),
        );

        if ($container->hasExtension('sulu_page')) {
            $container->prependExtensionConfig('sulu_page', [
                'objects' => [
                    'page' => ['model' => $config['page']['page_class']],
                    'page_content' => ['model' => $config['page']['entity_class']],
                ],
            ]);
        }

        if ($config['article']['enabled'] && $container->hasExtension('sulu_article')) {
            $container->prependExtensionConfig('sulu_article', [
                'objects' => [
                    'article' => ['model' => $config['article']['article_class']],
                    'article_content' => ['model' => $config['article']['entity_class']],
                ],
            ]);
        }

        // Doctrine ResolveTargetEntity: replace Sulu's original entity class references in
        // association mappings with the configured concrete classes. This ensures Doctrine never
        // generates proxies for the original Sulu classes, allowing auto_generate_proxy_classes: false.
        if ($container->hasExtension('doctrine')) {
            $resolveTargetEntities = [
                \Sulu\Page\Domain\Model\PageDimensionContent::class => $config['page']['entity_class'],
            ];
            if ($config['article']['enabled'] && $container->hasExtension('sulu_article')) {
                $resolveTargetEntities[\Sulu\Article\Domain\Model\ArticleDimensionContent::class] = $config['article']['entity_class'];
            }
            $container->prependExtensionConfig('doctrine', [
                'orm' => ['resolve_target_entities' => $resolveTargetEntities],
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
