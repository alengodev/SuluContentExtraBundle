<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Messenger;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Wraps Sulu's Restore{Snippet,Article,Page}VersionMessageHandler to work around a
 * Doctrine identity-map staleness bug in the version-restore flow.
 *
 * The restore handlers call `getOneBy(..., version: [requestedVersion, CURRENT_VERSION])`
 * expecting the eager-loaded `dimensionContents` collection to contain BOTH the requested
 * historic version and the current draft. But if the content entity was already loaded in
 * the same request with the default (`version = 0`) view — e.g. by a preview, list or form
 * render — Doctrine returns the already-managed entity and does NOT overwrite its already
 * initialized `dimensionContents` collection. The collection then holds only `version = 0`,
 * so ContentAggregator::aggregate() throws:
 *
 *   ContentNotFoundException: No content found for attributes [..., version=<historic>]
 *   ... Available attributes: [..., version=0]
 *
 * Detaching all managed entities before delegating forces the inner handler's `getOneBy`
 * to hydrate a fresh collection containing every requested version. Restore is the terminal
 * action of its request (the *Controller::handleAction runs it before anything else that
 * needs the managed graph), so clearing here has no side effects; the messenger
 * DoctrineFlushMiddleware still flushes the copy the inner handler persists afterwards.
 *
 * Wired by {@see \Alengo\SuluContentExtraBundle\DependencyInjection\Compiler\RestoreVersionHandlerRefreshPass},
 * which moves the `messenger.message_handler` tag from each Sulu handler onto an instance of
 * this class so the wrapper — not the original — is registered as the handler.
 *
 * @internal
 */
final class RestoreVersionEntityManagerRefreshHandler
{
    /**
     * @param callable(object): object $inner the wrapped Sulu restore handler
     */
    public function __construct(
        private readonly mixed $inner,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(object $message): object
    {
        $this->entityManager->clear();

        return ($this->inner)($message);
    }
}
