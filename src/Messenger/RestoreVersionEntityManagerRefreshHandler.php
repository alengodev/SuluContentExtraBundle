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
 * We detach only the already-managed content root that is being restored (matched by the
 * entity class and the `uuid` of the message identifier). That drops the stale collection
 * so the inner handler's `getOneBy` hydrates a fresh one with every requested version,
 * while leaving everything else managed — other content roots loaded in the same request
 * keep their pending changes, and crucially the
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
final readonly class RestoreVersionEntityManagerRefreshHandler
{
    /**
     * @param callable(object): object $inner the wrapped Sulu restore handler
     * @param class-string<ContentRichEntityInterface<*>> $entityClass      the content root the message restores
     * @param string $identifierGetter message method returning the identifier, e.g. ['uuid' => ...]
     */
    public function __construct(
        private mixed $inner,
        private EntityManagerInterface $entityManager,
        private string $entityClass,
        private string $identifierGetter,
    ) {
    }

    public function __invoke(object $message): object
    {
        $uuid = $this->resolveUuid($message);

        $staleEntities = [];
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (!$entity instanceof $this->entityClass) {
                    continue;
                }

                // Without a uuid in the identifier we cannot tell which root is restored, so
                // every root of the restored type is detached (still never other content types).
                if (null !== $uuid && $entity->getId() !== $uuid) {
                    continue;
                }

                $staleEntities[] = $entity;
            }
        }

        foreach ($staleEntities as $entity) {
            $this->entityManager->detach($entity);
        }

        return ($this->inner)($message);
    }

    private function resolveUuid(object $message): ?string
    {
        if (!\method_exists($message, $this->identifierGetter)) {
            return null;
        }

        $identifier = $message->{$this->identifierGetter}();
        $uuid = \is_array($identifier) ? ($identifier['uuid'] ?? null) : null;

        return \is_string($uuid) && '' !== $uuid ? $uuid : null;
    }
}
