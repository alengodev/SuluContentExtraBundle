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

        if ($config['page']['enabled'] && $container->hasExtension('sulu_page')) {
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
            $resolveTargetEntities = [];
            if ($config['page']['enabled']) {
                $resolveTargetEntities[\Sulu\Page\Domain\Model\PageDimensionContent::class] = $config['page']['entity_class'];
            }
            if ($config['article']['enabled'] && $container->hasExtension('sulu_article')) {
                $resolveTargetEntities[\Sulu\Article\Domain\Model\ArticleDimensionContent::class] = $config['article']['entity_class'];
            }
            if ([] !== $resolveTargetEntities) {
                $container->prependExtensionConfig('doctrine', [
                    'orm' => ['resolve_target_entities' => $resolveTargetEntities],
                ]);
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        if ($config['page']['enabled']) {
            $container->setDefinition('alengo_content_extra.page_data_mapper', $this->createDataMapperDefinition(
                $config['page']['entity_class'],
                $config['page']['unlocalized_keys'],
                $config['page']['localized_keys'],
            ));
            $container->setDefinition(PageAdditionalAdmin::class, $this->createAdminDefinition(
                PageAdditionalAdmin::class,
                $config['page']['form_key'],
                $config['page']['tab_title'],
            ));
        }

        if ($config['article']['enabled']) {
            $container->setDefinition('alengo_content_extra.article_data_mapper', $this->createDataMapperDefinition(
                $config['article']['entity_class'],
                $config['article']['unlocalized_keys'],
                $config['article']['localized_keys'],
            ));
            $container->setDefinition(ArticleAdditionalAdmin::class, $this->createAdminDefinition(
                ArticleAdditionalAdmin::class,
                $config['article']['form_key'],
                $config['article']['tab_title'],
                new Reference('sulu_admin.metadata_group_provider'),
            ));
        }
    }

    /**
     * @param array<int, string> $unlocalizedKeys
     * @param array<int, string> $localizedKeys
     */
    private function createDataMapperDefinition(string $entityClass, array $unlocalizedKeys, array $localizedKeys): Definition
    {
        return (new Definition(AdditionalDataMapper::class))
            ->addArgument($entityClass)
            ->addArgument($unlocalizedKeys)
            ->addArgument($localizedKeys)
            ->addTag('sulu_content.data_mapper', ['priority' => 64]);
    }

    private function createAdminDefinition(string $class, string $formKey, string $tabTitle, Reference ...$extraArgs): Definition
    {
        $def = (new Definition($class))
            ->addArgument(new Reference('sulu_admin.view_builder_factory'));
        foreach ($extraArgs as $ref) {
            $def->addArgument($ref);
        }

        return $def
            ->addArgument($formKey)
            ->addArgument($tabTitle)
            ->addTag('sulu.admin')
            ->addTag('sulu.context', ['context' => 'admin']);
    }

    public function getAlias(): string
    {
        return 'alengo_content_extra';
    }
}
