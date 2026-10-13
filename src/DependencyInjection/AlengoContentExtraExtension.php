<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection;

use Alengo\SuluContentExtraBundle\Admin\ArticleAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Admin\PageAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Admin\SnippetAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Content\DataMapper\AdditionalDataMapper;
use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Alengo\SuluContentExtraBundle\RequestWorkflow\AdditionalDataRequiredPreValidator;
use Sulu\Article\Domain\Model\ArticleDimensionContent;
use Sulu\Content\Application\RequestWorkflow\PreValidator\RequestWorkflowPreValidatorInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Snippet\Domain\Model\SnippetDimensionContent;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        $config = $this->resolveConfig($container->getExtensionConfig($this->getAlias()));
        $page = $config['page'];
        $article = $config['article'];
        $snippet = $config['snippet'];

        foreach (['page' => 'sulu_page', 'article' => 'sulu_article', 'snippet' => 'sulu_snippet'] as $type => $suluExtension) {
            if ($config[$type]->enabled && $container->hasExtension($suluExtension)) {
                $this->assertStoresAdditionalData($type, $config[$type]);
            }
        }

        if ($page->enabled && $container->hasExtension('sulu_page')) {
            $container->prependExtensionConfig('sulu_page', [
                'objects' => [
                    'page' => ['model' => $page->rootClass],
                    'page_content' => ['model' => $page->entityClass],
                ],
            ]);
        }

        if ($article->enabled && $container->hasExtension('sulu_article')) {
            $container->prependExtensionConfig('sulu_article', [
                'objects' => [
                    'article' => ['model' => $article->rootClass],
                    'article_content' => ['model' => $article->entityClass],
                ],
            ]);
        }

        if ($snippet->enabled && $container->hasExtension('sulu_snippet')) {
            $container->prependExtensionConfig('sulu_snippet', [
                'objects' => [
                    'snippet' => ['model' => $snippet->rootClass],
                    'snippet_content' => ['model' => $snippet->entityClass],
                ],
            ]);
        }

        // Doctrine ResolveTargetEntity: replace Sulu's original entity class references in
        // association mappings with the configured concrete classes. This ensures Doctrine never
        // generates proxies for the original Sulu classes, allowing auto_generate_proxy_classes: false.
        if ($container->hasExtension('doctrine')) {
            // Map the bundle entities explicitly instead of relying on the project's
            // `auto_mapping: true` (DoctrineBundle 3 defaults to false). The key is the bundle
            // name, which auto_mapping skips when it is set, so they are never mapped twice.
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'AlengoContentExtraBundle' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => __DIR__ . '/../Entity',
                            'prefix' => 'Alengo\\SuluContentExtraBundle\\Entity',
                            'alias' => 'AlengoContentExtra',
                        ],
                    ],
                ],
            ]);

            $resolveTargetEntities = [];
            if ($page->enabled && $container->hasExtension('sulu_page')) {
                $resolveTargetEntities[PageDimensionContent::class] = $page->entityClass;
            }
            if ($article->enabled && $container->hasExtension('sulu_article')) {
                $resolveTargetEntities[ArticleDimensionContent::class] = $article->entityClass;
            }
            if ($snippet->enabled && $container->hasExtension('sulu_snippet')) {
                $resolveTargetEntities[SnippetDimensionContent::class] = $snippet->entityClass;
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
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Review workflows and their pre-validators exist since Sulu 3.1.
        if (\interface_exists(RequestWorkflowPreValidatorInterface::class)) {
            $container->register('alengo_content_extra.request_workflow_pre_validator.additional_data_required', AdditionalDataRequiredPreValidator::class)
                ->addTag('sulu_content.request_workflow_pre_validator');
        }

        $config = $this->resolveConfig($configs);
        $page = $config['page'];
        $article = $config['article'];
        $snippet = $config['snippet'];

        if ($page->enabled) {
            $container->setDefinition('alengo_content_extra.page_data_mapper', $this->createDataMapperDefinition($page));
            $container->setDefinition(PageAdditionalAdmin::class, $this->createAdminDefinition(
                PageAdditionalAdmin::class,
                $page,
            ));
        }

        if ($article->enabled) {
            $container->setDefinition('alengo_content_extra.article_data_mapper', $this->createDataMapperDefinition($article));
            $container->setDefinition(ArticleAdditionalAdmin::class, $this->createAdminDefinition(
                ArticleAdditionalAdmin::class,
                $article,
                new Reference('sulu_admin.metadata_group_provider'),
                new Reference('sulu_security.security_checker'),
            ));
        }

        if ($snippet->enabled) {
            $container->setDefinition('alengo_content_extra.snippet_data_mapper', $this->createDataMapperDefinition($snippet));
            $container->setDefinition(SnippetAdditionalAdmin::class, $this->createAdminDefinition(
                SnippetAdditionalAdmin::class,
                $snippet,
                new Reference('sulu_admin.metadata_group_provider'),
                new Reference('sulu_security.security_checker'),
            ));
        }
    }

    /**
     * @param array<mixed> $configs
     *
     * @return array{page: ContentTypeConfig, article: ContentTypeConfig, snippet: ContentTypeConfig}
     */
    private function resolveConfig(array $configs): array
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        return [
            'page' => ContentTypeConfig::fromArray($this->section($config, 'page'), 'page_class'),
            'article' => ContentTypeConfig::fromArray($this->section($config, 'article'), 'article_class'),
            'snippet' => ContentTypeConfig::fromArray($this->section($config, 'snippet'), 'snippet_class'),
        ];
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function section(array $config, string $key): array
    {
        $section = $config[$key] ?? [];

        return \is_array($section) ? $section : [];
    }

    /**
     * Without the interface the data mapper, normalizer and merger skip the entity, so every
     * save would silently drop the additional data.
     */
    private function assertStoresAdditionalData(string $type, ContentTypeConfig $config): void
    {
        if (\is_a($config->entityClass, AdditionalDataInterface::class, true)) {
            return;
        }

        throw new InvalidConfigurationException(\sprintf(
            'The "alengo_content_extra.%s.entity_class" "%s" must implement "%s"%s.',
            $type,
            $config->entityClass,
            AdditionalDataInterface::class,
            \class_exists($config->entityClass) ? '' : ', the class does not exist',
        ));
    }

    private function createDataMapperDefinition(ContentTypeConfig $config): Definition
    {
        return (new Definition(AdditionalDataMapper::class))
            ->addArgument($config->entityClass)
            ->addArgument($config->unlocalizedKeys)
            ->addArgument($config->localizedKeys)
            ->addArgument($config->formKey)
            ->addArgument(new Reference('sulu_admin.form_metadata_provider'))
            ->addTag('sulu_content.data_mapper', ['priority' => 64]);
    }

    private function createAdminDefinition(string $class, ContentTypeConfig $config, Reference ...$extraArgs): Definition
    {
        $def = (new Definition($class))
            ->addArgument(new Reference('sulu_admin.view_builder_factory'))
            ->addArgument(new Reference('sulu_content.content_view_builder_factory'));
        foreach ($extraArgs as $ref) {
            $def->addArgument($ref);
        }

        return $def
            ->addArgument($config->formKey)
            ->addArgument($config->tabTitle)
            ->addTag('sulu.admin')
            ->addTag('sulu.context', ['context' => 'admin']);
    }

    public function getAlias(): string
    {
        return 'alengo_content_extra';
    }
}
