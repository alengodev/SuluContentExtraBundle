<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\Content\DataMapper;

use Alengo\SuluContentExtraBundle\Content\DataMapper\AdditionalDataMapper;
use Alengo\SuluContentExtraBundle\Entity\ArticleDimensionContent;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Exception\MetadataNotFoundException;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

final class AdditionalDataMapperTest extends TestCase
{
    public function testIgnoresOtherEntityClasses(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();

        $this->createMapper(entityClass: ArticleDimensionContent::class)
            ->map($unlocalized, $localized, ['information' => 'a']);

        self::assertSame([], $unlocalized->getAdditionalData());
        self::assertSame([], $localized->getAdditionalData());
    }

    public function testFirstWriteInitialisesTheVisibleKeysWithNull(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();

        $this->createMapper()->map($unlocalized, $localized, ['title' => 'New page']);

        self::assertSame(['information' => null], $unlocalized->getAdditionalData());
        self::assertSame(['subtitle' => null], $localized->getAdditionalData());
    }

    public function testSplitsKeysBetweenUnlocalizedAndLocalizedContent(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();

        $this->createMapper()->map($unlocalized, $localized, ['information' => 'a', 'subtitle' => 'b']);

        self::assertSame(['information' => 'a'], $unlocalized->getAdditionalData());
        self::assertSame(['subtitle' => 'b'], $localized->getAdditionalData());
    }

    public function testPreviewWritesAllKeysInOneCall(): void
    {
        [, $localized] = $this->createDimensionContents();

        // The preview passes the same merged object as both dimension contents.
        $this->createMapper()->map($localized, $localized, ['information' => 'a', 'subtitle' => 'b']);

        self::assertSame(['information' => 'a', 'subtitle' => 'b'], $localized->getAdditionalData());
    }

    public function testKeepsStoredDataWhenTheSubmissionCarriesNone(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();
        $unlocalized->setAdditionalData(['information' => 'stored']);
        $localized->setAdditionalData(['subtitle' => 'stored']);

        // e.g. a write by another tab or a workflow transition without the form data
        $this->createMapper()->map($unlocalized, $localized, ['title' => 'Changed']);

        self::assertSame(['information' => 'stored'], $unlocalized->getAdditionalData());
        self::assertSame(['subtitle' => 'stored'], $localized->getAdditionalData());
    }

    public function testRestoringAnEmptySnapshotClearsStoredData(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();
        $unlocalized->setAdditionalData(['information' => 'stored']);

        $this->createMapper()->map($unlocalized, $localized, ['additionalData' => []]);

        self::assertSame(['information' => null], $unlocalized->getAdditionalData());
    }

    public function testRestoresValuesFromTheSnapshot(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();
        $unlocalized->setAdditionalData(['information' => 'stored']);

        $this->createMapper()->map($unlocalized, $localized, ['additionalData' => ['information' => 'restored']]);

        self::assertSame(['information' => 'restored'], $unlocalized->getAdditionalData());
    }

    public function testFlatValueWinsOverTheSnapshot(): void
    {
        [$unlocalized, $localized] = $this->createDimensionContents();

        $this->createMapper()->map($unlocalized, $localized, [
            'information' => 'edited',
            'additionalData' => ['information' => 'loaded'],
        ]);

        self::assertSame(['information' => 'edited'], $unlocalized->getAdditionalData());
    }

    public function testOmitsKeysHiddenByTheirVisibleCondition(): void
    {
        $subtitle = new FieldMetadata('subtitle');
        $subtitle->setVisibleCondition('template == "special"');
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($subtitle));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['template' => 'default', 'subtitle' => 'x']);
        self::assertSame([], $localized->getAdditionalData());

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['template' => 'special', 'subtitle' => 'x']);
        self::assertSame(['subtitle' => 'x'], $localized->getAdditionalData());
    }

    public function testSectionConditionAppliesToItsFields(): void
    {
        $section = new SectionMetadata('details');
        $section->setVisibleCondition('template == "special"');
        $section->addItem(new FieldMetadata('information'));
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($section));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['template' => 'default', 'information' => 'x']);

        self::assertSame([], $unlocalized->getAdditionalData());
    }

    public function testConditionItCannotEvaluateKeepsTheFieldVisible(): void
    {
        // jexl syntax, which Symfony's ExpressionLanguage cannot parse
        $subtitle = new FieldMetadata('subtitle');
        $subtitle->setVisibleCondition('tags|length > 1');
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($subtitle));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['subtitle' => 'x']);

        self::assertSame(['subtitle' => 'x'], $localized->getAdditionalData());
    }

    public function testUnderstandsTheJexlLengthTransform(): void
    {
        $subtitle = new FieldMetadata('subtitle');
        $subtitle->setVisibleCondition('blocks|length > 1');
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($subtitle));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['blocks' => [['type' => 'text']], 'subtitle' => 'x']);
        self::assertSame([], $localized->getAdditionalData());

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['blocks' => [['type' => 'text'], ['type' => 'image']], 'subtitle' => 'x']);
        self::assertSame(['subtitle' => 'x'], $localized->getAdditionalData());
    }

    public function testResolvesDottedPathsIntoNestedData(): void
    {
        $subtitle = new FieldMetadata('subtitle');
        $subtitle->setVisibleCondition('ext.excerpt.title == "Shown" && ext.excerpt.categories|length == 0');
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($subtitle));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, [
            'ext' => ['excerpt' => ['title' => 'Hidden', 'categories' => []]],
            'subtitle' => 'x',
        ]);
        self::assertSame([], $localized->getAdditionalData());

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, [
            'ext' => ['excerpt' => ['title' => 'Shown', 'categories' => []]],
            'subtitle' => 'x',
        ]);
        self::assertSame(['subtitle' => 'x'], $localized->getAdditionalData());
    }

    public function testListsKeepWorkingWithIn(): void
    {
        $subtitle = new FieldMetadata('subtitle');
        $subtitle->setVisibleCondition('"news" in tags');
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($subtitle));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['tags' => ['events'], 'subtitle' => 'x']);

        self::assertSame([], $localized->getAdditionalData());
    }

    public function testAdminOnlyVariablesKeepTheFieldVisible(): void
    {
        $subtitle = new FieldMetadata('subtitle');
        $subtitle->setVisibleCondition('__webspace.key == "other"');
        $mapper = $this->createMapper(formMetadata: $this->createFormMetadata($subtitle));

        [$unlocalized, $localized] = $this->createDimensionContents();
        $mapper->map($unlocalized, $localized, ['subtitle' => 'x']);

        self::assertSame(['subtitle' => 'x'], $localized->getAdditionalData());
    }

    /**
     * @param class-string $entityClass
     */
    private function createMapper(
        string $entityClass = PageDimensionContent::class,
        ?FormMetadata $formMetadata = null,
    ): AdditionalDataMapper {
        $metadataProvider = $this->createStub(MetadataProviderInterface::class);
        if (!$formMetadata instanceof FormMetadata) {
            $metadataProvider->method('getMetadata')->willThrowException(new MetadataNotFoundException('form', 'page_additional_data'));
        } else {
            $metadataProvider->method('getMetadata')->willReturn($formMetadata);
        }

        return new AdditionalDataMapper($entityClass, ['information'], ['subtitle'], 'page_additional_data', $metadataProvider);
    }

    private function createFormMetadata(FieldMetadata|SectionMetadata ...$items): FormMetadata
    {
        $formMetadata = new FormMetadata();
        foreach ($items as $item) {
            $formMetadata->addItem($item);
        }

        return $formMetadata;
    }

    /**
     * @return array{PageDimensionContent, PageDimensionContent}
     */
    private function createDimensionContents(): array
    {
        $page = new Page();

        $unlocalized = new PageDimensionContent($page);
        $localized = new PageDimensionContent($page);
        $localized->setLocale('de');

        return [$unlocalized, $localized];
    }
}
