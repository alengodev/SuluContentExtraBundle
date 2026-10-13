<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Unit\DependencyInjection;

use Alengo\SuluContentExtraBundle\DependencyInjection\AlengoContentExtraExtension;
use Alengo\SuluContentExtraBundle\RequestWorkflow\AdditionalDataRequiredPreValidator;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\RequestWorkflow\PreValidator\RequestWorkflowPreValidatorInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class AlengoContentExtraExtensionTest extends TestCase
{
    public function testRejectsAnEntityClassWithoutAdditionalData(): void
    {
        $container = $this->createContainer(['page' => ['entity_class' => \stdClass::class]]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('alengo_content_extra.page.entity_class');

        (new AlengoContentExtraExtension())->prepend($container);
    }

    public function testIgnoresTheEntityClassOfAnAbsentSuluBundle(): void
    {
        $container = $this->createContainer(['article' => ['entity_class' => \stdClass::class]]);

        (new AlengoContentExtraExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('sulu_article'));
    }

    public function testRegistersThePreValidatorWhenSuluHasReviewWorkflows(): void
    {
        $container = new ContainerBuilder();
        (new AlengoContentExtraExtension())->load([], $container);

        $id = 'alengo_content_extra.request_workflow_pre_validator.additional_data_required';
        if (!\interface_exists(RequestWorkflowPreValidatorInterface::class)) {
            self::assertFalse($container->hasDefinition($id));

            return;
        }

        self::assertSame(AdditionalDataRequiredPreValidator::class, $container->getDefinition($id)->getClass());
        self::assertTrue($container->getDefinition($id)->hasTag('sulu_content.request_workflow_pre_validator'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach (['sulu_page', 'sulu_snippet', 'alengo_content_extra'] as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }
        $container->loadFromExtension('alengo_content_extra', $config);

        return $container;
    }
}
