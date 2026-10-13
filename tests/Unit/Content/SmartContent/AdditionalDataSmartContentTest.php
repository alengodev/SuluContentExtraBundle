<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\Content\SmartContent;

use Alengo\SuluContentExtraBundle\Content\Resolver\AdditionalDataResolver;
use Alengo\SuluContentExtraBundle\Content\ResourceLoader\PageAdditionalDataResourceLoader;
use Alengo\SuluContentExtraBundle\Content\SmartContent\AdditionalDataRequestCollector;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderContentViewEnhancementInterface;

/**
 * The resolver records which additionalData fields a smart-content item asks for, the
 * resource-loader enhancement of the same item then attaches exactly those. Sulu's
 * ContentResolver calls the two back to back per item, which these tests replay.
 */
final class AdditionalDataSmartContentTest extends TestCase
{
    private AdditionalDataRequestCollector $collector;

    private AdditionalDataResolver $resolver;

    private PageAdditionalDataResourceLoader $loader;

    protected function setUp(): void
    {
        $inner = $this->createStub(ResourceLoaderContentViewEnhancementInterface::class);
        $inner->method('resolveContentViewEnhancement')->willReturn(ContentView::create(['uuid' => 'from-inner'], ['view' => 1]));

        $this->collector = new AdditionalDataRequestCollector();
        $this->resolver = new AdditionalDataResolver($this->collector);
        $this->loader = new PageAdditionalDataResourceLoader($inner, $this->collector);
    }

    public function testAttachesOnlyTheReferencedFieldsUnderTheirAlias(): void
    {
        $content = $this->createContent(['template_theme' => 'dark', 'location' => 'Graz']);

        $enhancement = $this->resolveItem($content, ['theme' => 'additionalData.template_theme', 'title' => 'title']);

        self::assertSame(['uuid' => 'from-inner', 'theme' => 'dark'], $enhancement->getContent());
        self::assertSame(['view' => 1], $enhancement->getView());
    }

    public function testSameResourceInTwoSmartContentsGetsTheFieldsOfEach(): void
    {
        $content = $this->createContent(['template_theme' => 'dark', 'location' => 'Graz']);

        $first = $this->resolveItem($content, ['theme' => 'additionalData.template_theme']);
        $second = $this->resolveItem($content, ['theme' => 'additionalData.location']);

        self::assertSame(['uuid' => 'from-inner', 'theme' => 'dark'], $first->getContent());
        self::assertSame(['uuid' => 'from-inner', 'theme' => 'Graz'], $second->getContent());
    }

    public function testItemWithoutAdditionalDataPropertiesGetsNothing(): void
    {
        $content = $this->createContent(['template_theme' => 'dark']);

        // An earlier item asked for a field but was never enhanced (e.g. filtered out) ...
        $this->resolver->resolve($content, ['theme' => 'additionalData.template_theme']);
        // ... a later item of the same resource asks for none.
        $enhancement = $this->resolveItem($content, ['title' => 'title']);

        self::assertSame(['uuid' => 'from-inner'], $enhancement->getContent());
    }

    public function testResolverWithoutPropertiesReturnsAllData(): void
    {
        $content = $this->createContent(['template_theme' => 'dark']);

        $view = $this->resolver->resolve($content);

        self::assertNotNull($view);
        self::assertSame(['template_theme' => 'dark'], $view->getContent());
        self::assertSame([], $this->collector->take($content->getResource()->getUuid()));
    }

    public function testCollectorForgetsAMappingOnceTaken(): void
    {
        $this->collector->request('uuid', ['alias' => 'field']);

        self::assertSame(['alias' => 'field'], $this->collector->take('uuid'));
        self::assertSame([], $this->collector->take('uuid'));
    }

    public function testCollectorReset(): void
    {
        $this->collector->request('uuid', ['alias' => 'field']);
        $this->collector->reset();

        self::assertSame([], $this->collector->take('uuid'));
    }

    /**
     * @param array<string, string> $properties
     */
    private function resolveItem(PageDimensionContent $content, array $properties): ContentView
    {
        $this->resolver->resolve($content, $properties);

        return $this->loader->resolveContentViewEnhancement($content);
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function createContent(array $additionalData): PageDimensionContent
    {
        $content = new PageDimensionContent(new Page('0190c1a6-0000-7000-8000-000000000001'));
        $content->setLocale('de');
        $content->setAdditionalData($additionalData);

        return $content;
    }
}
