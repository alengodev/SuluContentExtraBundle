<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\DependencyInjection\Compiler;

use Alengo\SuluContentExtraBundle\Messenger\RestoreVersionEntityManagerRefreshHandler;
use Sulu\Article\Application\Message\RestoreArticleVersionMessage;
use Sulu\Page\Application\Message\RestorePageVersionMessage;
use Sulu\Snippet\Application\Message\RestoreSnippetVersionMessage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wraps Sulu's snippet/article/page version-restore message handlers with
 * {@see RestoreVersionEntityManagerRefreshHandler} to fix a Doctrine identity-map
 * staleness bug (see that class for the full explanation).
 *
 * Messenger collects handlers by the `messenger.message_handler` tag, so — like the sibling
 * {@see AdditionalDataResourceLoaderPass} does for resource loaders — we cannot simply
 * decorate the services. Instead we move the tag from each original handler onto our wrapper
 * (passing the message class explicitly via the tag's `handles` attribute, since the
 * wrapper's __invoke is not message-type-hinted).
 *
 * Each handler is optional and guarded by hasDefinition(); the `::class` constants resolve to
 * strings without autoloading, so a consumer without the ArticleBundle/PageBundle is fine.
 *
 * Must run before Symfony's MessengerPass collects the handlers, hence the high priority in
 * {@see \Alengo\SuluContentExtraBundle\AlengoContentExtraBundle::build()}.
 */
final class RestoreVersionHandlerRefreshPass implements CompilerPassInterface
{
    private const TAG = 'messenger.message_handler';

    /**
     * Sulu restore-handler service id => restore message class it handles.
     */
    private const HANDLERS = [
        'sulu_snippet.restore_snippet_version_handler' => RestoreSnippetVersionMessage::class,
        'sulu_article.restore_article_version_handler' => RestoreArticleVersionMessage::class,
        'sulu_page.restore_page_version_handler' => RestorePageVersionMessage::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::HANDLERS as $innerId => $messageClass) {
            if (!$container->hasDefinition($innerId)) {
                continue;
            }

            $inner = $container->getDefinition($innerId);

            // Take over the handler tag so our wrapper — not the original — is the handler.
            $inner->clearTag(self::TAG);

            $container->register($innerId . '.em_refresh', RestoreVersionEntityManagerRefreshHandler::class)
                ->setArguments([
                    new Reference($innerId),
                    new Reference('doctrine.orm.entity_manager'),
                ])
                ->addTag(self::TAG, ['handles' => $messageClass])
                ->setPublic(false);
        }
    }
}
