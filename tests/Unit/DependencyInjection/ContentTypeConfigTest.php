<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\DependencyInjection;

use Alengo\SuluContentExtraBundle\DependencyInjection\Configuration;
use Alengo\SuluContentExtraBundle\DependencyInjection\ContentTypeConfig;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ContentTypeConfigTest extends TestCase
{
    public function testReadsTheProcessedDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['page' => ['localized_keys' => ['subtitle']]],
        ]);
        self::assertIsArray($config['page']);

        $page = ContentTypeConfig::fromArray($config['page'], 'page_class');

        self::assertTrue($page->enabled);
        self::assertSame(Page::class, $page->rootClass);
        self::assertSame(PageDimensionContent::class, $page->entityClass);
        self::assertSame('page_additional_data', $page->formKey);
        self::assertSame('sulu_admin.app.additional_data', $page->tabTitle);
        self::assertSame([], $page->unlocalizedKeys);
        self::assertSame(['subtitle'], $page->localizedKeys);
    }

    public function testReadsADisabledType(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['snippet' => false],
        ]);
        self::assertIsArray($config['snippet']);

        self::assertFalse(ContentTypeConfig::fromArray($config['snippet'], 'snippet_class')->enabled);
    }

    public function testRejectsAKeyListWithNonStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ContentTypeConfig::fromArray([
            'enabled' => true,
            'page_class' => Page::class,
            'entity_class' => PageDimensionContent::class,
            'form_key' => 'page_additional_data',
            'tab_title' => 'title',
            'unlocalized_keys' => [['nested']],
            'localized_keys' => [],
        ], 'page_class');
    }

    public function testRejectsAKeyThatIsBothLocalizedAndUnlocalized(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"subtitle"');

        (new Processor())->processConfiguration(new Configuration(), [
            ['page' => ['unlocalized_keys' => ['information', 'subtitle'], 'localized_keys' => ['subtitle']]],
        ]);
    }
}
