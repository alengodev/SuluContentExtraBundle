<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;

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
 * We detach only the already-managed content-root entities (snippet/article/page). That
 * drops the stale collection so the inner handler's `getOneBy` hydrates a fresh one with
 * every requested version, while leaving everything else managed — crucially the
 * authenticated User, which UserBlameSubscriber::onFlush writes as `changer`/`creator` on
 * the restored dimension content. A blanket EntityManager::clear() would also detach that
 * User, so the subsequent flush would fail with "Unable to find
 * 'Sulu\Bundle\SecurityBundle\Entity\User' entity identifier associated with the UnitOfWork".
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
        $unitOfWork = $this->entityManager->getUnitOfWork();

        $contentRichEntities = [];
        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof ContentRichEntityInterface) {
                    $contentRichEntities[] = $entity;
                }
            }
        }

        foreach ($contentRichEntities as $entity) {
            $this->entityManager->detach($entity);
        }

        return ($this->inner)($message);
    }
}
