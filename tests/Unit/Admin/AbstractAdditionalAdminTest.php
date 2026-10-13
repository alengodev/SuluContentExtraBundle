<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\Admin;

use Alengo\SuluContentExtraBundle\Admin\AbstractAdditionalAdmin;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowResolverInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Page\Domain\Model\PageInterface;

final class AbstractAdditionalAdminTest extends TestCase
{
    public function testTakesTheReviewToolbarFromTheContentViewBuilderFactory(): void
    {
        $this->requireReviewWorkflows();

        $save = new DropdownToolbarAction('sulu_admin.save', 'su-save', []);
        $approval = new ToolbarAction('sulu_content.review_workflow_transition_request');

        $factory = $this->createMock(ContentViewBuilderFactoryInterface::class);
        $factory->expects(self::once())
            ->method('getWorkflowTransitionRequestToolbarActions')
            ->with(PageInterface::class, 'save-condition', 'publish-condition', 'review-condition')
            ->willReturn(['save' => $save, 'approval' => $approval]);

        $toolbarActions = TestAdditionalAdmin::toolbarActions(
            $factory,
            PageInterface::class,
            'save-condition',
            'publish-condition',
            'review-condition',
        );

        self::assertCount(3, $toolbarActions);
        self::assertSame($save, $toolbarActions[0]);
        self::assertSame($approval, $toolbarActions[1]);
        self::assertInstanceOf(DropdownToolbarAction::class, $toolbarActions[2]);
        self::assertSame('sulu_admin.edit', $toolbarActions[2]->getOptions()['label'] ?? null);
    }

    public function testOmittedConditionsKeepTheFactoryDefaults(): void
    {
        $this->requireReviewWorkflows();

        $factory = $this->createMock(ContentViewBuilderFactoryInterface::class);
        $factory->expects(self::once())
            ->method('getWorkflowTransitionRequestToolbarActions')
            ->with(PageInterface::class)
            ->willReturn([
                'save' => new DropdownToolbarAction('sulu_admin.save', 'su-save', []),
                'approval' => new ToolbarAction('sulu_content.review_workflow_transition_request'),
            ]);

        TestAdditionalAdmin::toolbarActions($factory, PageInterface::class);
    }

    public function testFallsBackToThePlainToolbarWithoutReviewWorkflows(): void
    {
        if (\interface_exists(RequestWorkflowResolverInterface::class)) {
            self::markTestSkipped('Sulu 3.0.x only.');
        }

        $toolbarActions = TestAdditionalAdmin::toolbarActions(
            $this->createStub(ContentViewBuilderFactoryInterface::class),
            PageInterface::class,
        );

        self::assertCount(2, $toolbarActions);
        self::assertInstanceOf(DropdownToolbarAction::class, $toolbarActions[0]);
        self::assertSame('sulu_admin.save', $toolbarActions[0]->getOptions()['label'] ?? null);
    }

    private function requireReviewWorkflows(): void
    {
        if (!\interface_exists(RequestWorkflowResolverInterface::class)) {
            self::markTestSkipped('Review workflows need Sulu 3.1.');
        }
    }
}

/**
 * @internal exposes the protected toolbar factory method to the test
 */
final class TestAdditionalAdmin extends AbstractAdditionalAdmin
{
    /**
     * @param class-string<PageInterface> $contentRichEntityClass
     *
     * @return list<ToolbarAction>
     */
    public static function toolbarActions(
        ContentViewBuilderFactoryInterface $factory,
        string $contentRichEntityClass,
        ?string $saveVisibleCondition = null,
        ?string $publishVisibleCondition = null,
        ?string $reviewVisibleCondition = null,
    ): array {
        return self::createToolbarActions(
            $factory,
            $contentRichEntityClass,
            $saveVisibleCondition,
            $publishVisibleCondition,
            $reviewVisibleCondition,
        );
    }
}
