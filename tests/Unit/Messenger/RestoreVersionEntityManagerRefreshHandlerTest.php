<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\Messenger;

use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\Snippet;
use Alengo\SuluContentExtraBundle\Messenger\RestoreVersionEntityManagerRefreshHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Sulu\Snippet\Application\Message\RestoreSnippetVersionMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;

final class RestoreVersionEntityManagerRefreshHandlerTest extends TestCase
{
    public function testDetachesOnlyTheRestoredContentRoot(): void
    {
        $restored = new Snippet('0190c1a6-0000-7000-8000-000000000001');
        $otherSnippet = new Snippet('0190c1a6-0000-7000-8000-000000000002');
        $page = new Page('0190c1a6-0000-7000-8000-000000000001');

        $detached = $this->invoke(
            [$restored, $otherSnippet, $page],
            new RestoreSnippetVersionMessage(['uuid' => $restored->getId()], 3, 'de'),
        );

        self::assertSame([$restored], $detached);
    }

    public function testDetachesEveryRootOfTheTypeWithoutUuid(): void
    {
        $snippet = new Snippet();
        $otherSnippet = new Snippet();
        $page = new Page();

        $detached = $this->invoke(
            [$snippet, $otherSnippet, $page],
            new RestoreSnippetVersionMessage(['id' => 5], 3, 'de'),
        );

        self::assertSame([$snippet, $otherSnippet], $detached);
    }

    public function testCallsTheWrappedHandler(): void
    {
        $message = new RestoreSnippetVersionMessage(['uuid' => 'x'], 3, 'de');
        $result = new \stdClass();
        $detached = [];

        $handler = new RestoreVersionEntityManagerRefreshHandler(
            static fn (object $passed): object => $passed === $message ? $result : new \stdClass(),
            $this->createEntityManager([], $detached),
            SnippetInterface::class,
            'getSnippetIdentifier',
        );

        self::assertSame($result, $handler($message));
    }

    /**
     * @param list<object> $managedEntities
     *
     * @return list<object> the detached entities
     */
    private function invoke(array $managedEntities, object $message): array
    {
        $detached = [];
        $handler = new RestoreVersionEntityManagerRefreshHandler(
            static fn (object $message): object => $message,
            $this->createEntityManager($managedEntities, $detached),
            SnippetInterface::class,
            'getSnippetIdentifier',
        );

        $handler($message);

        return $detached;
    }

    /**
     * @param list<object> $managedEntities
     * @param list<object> $detached collects the entities passed to detach()
     */
    private function createEntityManager(array $managedEntities, array &$detached): EntityManagerInterface
    {
        $identityMap = [];
        foreach ($managedEntities as $entity) {
            $identityMap[$entity::class][\spl_object_id($entity)] = $entity;
        }

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getIdentityMap')->willReturn($identityMap);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('detach')->willReturnCallback(static function (object $entity) use (&$detached): void {
            $detached[] = $entity;
        });

        return $entityManager;
    }
}
