<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\RequestWorkflow;

use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use Alengo\SuluContentExtraBundle\RequestWorkflow\AdditionalDataRequiredPreValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\RequestWorkflow\PreValidator\PreValidationContext;
use Sulu\Content\Application\RequestWorkflow\PreValidator\RequestWorkflowPreValidatorInterface;
use Sulu\Content\Application\RequestWorkflow\Validator\ValidationResult;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequestDecisionMessage;

final class AdditionalDataRequiredPreValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\interface_exists(RequestWorkflowPreValidatorInterface::class)) {
            self::markTestSkipped('Review workflows need Sulu 3.1.');
        }
    }

    public function testApprovesWhenAllFieldsAreFilled(): void
    {
        $content = $this->createLocalizedContent(['information' => 'set'], ['subtitle' => 'set']);

        $result = $this->check($content, ['fields' => ['information', 'subtitle']]);

        self::assertTrue($result->approved);
    }

    public function testReadsUnlocalizedKeysFromTheUnlocalizedDimension(): void
    {
        $content = $this->createLocalizedContent(['information' => null], ['subtitle' => 'set']);

        $result = $this->check($content, ['fields' => ['information', 'subtitle']]);

        self::assertFalse($result->approved);
        self::assertSame([
            WorkflowTransitionRequestDecisionMessage::translated(
                AdditionalDataRequiredPreValidator::MISSING_MESSAGE,
                ['fields' => 'information'],
            )->toArray(),
        ], $result->messagesToArray());
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideValues(): iterable
    {
        yield 'null' => [null, false];
        yield 'empty string' => ['', false];
        yield 'whitespace' => ['  ', false];
        yield 'empty array' => [[], false];
        yield 'false' => [false, true];
        yield 'zero' => [0, true];
        yield 'text' => ['x', true];
        yield 'media selection' => [['ids' => [1]], true];
    }

    #[DataProvider('provideValues')]
    public function testTreatsValuesAsFilledOrMissing(mixed $value, bool $approved): void
    {
        $content = $this->createLocalizedContent([], ['subtitle' => $value]);

        self::assertSame($approved, $this->check($content, ['fields' => ['subtitle']])->approved);
    }

    public function testMissingKeyCountsAsMissing(): void
    {
        $content = $this->createLocalizedContent([], []);

        self::assertFalse($this->check($content, ['fields' => ['subtitle']])->approved);
    }

    public function testIgnoresOtherStagesOfTheUnlocalizedDimension(): void
    {
        $content = $this->createLocalizedContent([], ['subtitle' => 'set']);
        $live = new PageDimensionContent($content->getResource());
        $live->setStage(DimensionContentInterface::STAGE_LIVE);
        $live->setAdditionalData(['information' => 'only live']);
        $content->getResource()->addDimensionContent($live);

        self::assertFalse($this->check($content, ['fields' => ['information']])->approved);
    }

    public function testRequiresAFieldList(): void
    {
        $this->expectException(\LogicException::class);

        $this->check($this->createLocalizedContent([], []), ['fields' => 'subtitle']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function check(PageDimensionContent $content, array $config): ValidationResult
    {
        return (new AdditionalDataRequiredPreValidator())->check(new PreValidationContext($content, $config, 'default'));
    }

    /**
     * @param array<string, mixed> $unlocalizedData
     * @param array<string, mixed> $localizedData
     */
    private function createLocalizedContent(array $unlocalizedData, array $localizedData): PageDimensionContent
    {
        $page = new Page();

        $unlocalized = new PageDimensionContent($page);
        $unlocalized->setAdditionalData($unlocalizedData);
        $page->addDimensionContent($unlocalized);

        $localized = new PageDimensionContent($page);
        $localized->setLocale('de');
        $localized->setAdditionalData($localizedData);
        $page->addDimensionContent($localized);

        return $localized;
    }
}
