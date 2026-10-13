<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\Content\Enhancer;

use Alengo\SuluContentExtraBundle\Content\Enhancer\NavigationLinkEnhancer;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentEnhancer\DimensionContentEnhancerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;

final class NavigationLinkEnhancerTest extends TestCase
{
    private const SOURCE_UUID = '0190c1a6-0000-7000-8000-000000000001';

    public function testLeavesPagesWithoutLinkUntouched(): void
    {
        $content = $this->createContent(null);

        $result = $this->enhance($content, static fn (DimensionContentInterface $content): DimensionContentInterface => $content);

        self::assertInstanceOf(PageDimensionContent::class, $result);
        self::assertSame(['url' => '/link-page'], $result->getTemplateData());
    }

    public function testMarksAResolvedInternalLink(): void
    {
        $content = $this->createContent(['provider' => 'page', 'href' => 'target']);
        $target = new PageDimensionContent(new Page());

        $result = $this->enhance($content, static fn (): DimensionContentInterface => $target);

        self::assertSame($target, $result);
        self::assertSame([
            'sourceLink' => true,
            'sourceLinkResolved' => true,
            'sourceUuid' => self::SOURCE_UUID,
        ], $target->getTemplateData());
    }

    public function testMarksAResolvedExternalLink(): void
    {
        $content = $this->createContent(['provider' => 'external', 'href' => 'https://example.org']);

        $result = $this->enhance($content, static function (PageDimensionContent $content): DimensionContentInterface {
            $content->setTemplateData(['url' => 'https://example.org']);

            return $content;
        });

        self::assertInstanceOf(PageDimensionContent::class, $result);
        self::assertTrue($result->getTemplateData()['sourceLinkResolved']);
    }

    public function testMarksALinkWhoseTargetIsGone(): void
    {
        $content = $this->createContent(['provider' => 'page', 'href' => 'deleted']);

        $result = $this->enhance($content, static fn (DimensionContentInterface $content): DimensionContentInterface => $content);

        self::assertInstanceOf(PageDimensionContent::class, $result);
        self::assertSame([
            'url' => '/link-page',
            'sourceLink' => true,
            'sourceLinkResolved' => false,
            'sourceUuid' => self::SOURCE_UUID,
        ], $result->getTemplateData());
    }

    /**
     * @param \Closure(PageDimensionContent): DimensionContentInterface<PageInterface> $inner
     *
     * @return DimensionContentInterface<PageInterface>
     */
    private function enhance(PageDimensionContent $content, \Closure $inner): DimensionContentInterface
    {
        $innerEnhancer = $this->createStub(DimensionContentEnhancerInterface::class);
        $innerEnhancer->method('enhance')->willReturnCallback($inner);

        return (new NavigationLinkEnhancer($innerEnhancer))->enhance($content);
    }

    /**
     * @param array<string, mixed>|null $linkData
     */
    private function createContent(?array $linkData): PageDimensionContent
    {
        $content = new PageDimensionContent(new Page(self::SOURCE_UUID));
        $content->setLocale('de');
        $content->setLinkData($linkData);
        $content->setTemplateData(['url' => '/link-page']);

        return $content;
    }
}
