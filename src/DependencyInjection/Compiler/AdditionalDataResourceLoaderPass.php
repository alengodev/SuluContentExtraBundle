<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection\Compiler;

use Alengo\SuluContentExtraBundle\Content\ResourceLoader\ArticleAdditionalDataResourceLoader;
use Alengo\SuluContentExtraBundle\Content\ResourceLoader\PageAdditionalDataResourceLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces the "article" and "page" resource loaders with wrappers that attach
 * additionalData to each smart-content item (see AbstractAdditionalDataResourceLoader).
 *
 * We cannot simply decorate the loaders: Sulu collects resource loaders by the
 * `sulu_content.resource_loader` tag and wraps each in a CachedResourceLoader
 * (ResourceLoaderCacheCompilerPass). So we move the tag from each original service onto
 * our wrapper instead. This pass must run BEFORE that cache pass (higher priority).
 *
 * @see \Alengo\SuluContentExtraBundle\AlengoContentExtraBundle::build()
 */
final class AdditionalDataResourceLoaderPass implements CompilerPassInterface
{
    private const TAG = 'sulu_content.resource_loader';

    /**
     * Original loader service id => wrapper class. Both wrapped DimensionContent types
     * implement AdditionalDataInterface (PageDimensionContent, ArticleDimensionContent).
     */
    private const WRAPPERS = [
        'sulu_article.article_resource_loader' => ArticleAdditionalDataResourceLoader::class,
        'sulu_page.page_resource_loader' => PageAdditionalDataResourceLoader::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::WRAPPERS as $originalId => $wrapperClass) {
            if (!$container->hasDefinition($originalId)) {
                continue;
            }

            $original = $container->getDefinition($originalId);
            $tags = $original->getTag(self::TAG);

            // Take over the loader tag so our wrapper becomes the collected loader
            // (which Sulu then wraps in a CachedResourceLoader).
            $original->clearTag(self::TAG);

            $wrapperId = 'alengo_content_extra.' . $wrapperClass::getKey() . '_resource_loader';
            $wrapper = $container->register($wrapperId, $wrapperClass)
                ->setArguments([new Reference($originalId)])
                ->setPublic(false);

            foreach ($tags as $attributes) {
                $wrapper->addTag(self::TAG, $attributes);
            }
        }
    }
}
