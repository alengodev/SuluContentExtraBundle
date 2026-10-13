<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Functional;

use Alengo\SuluContentExtraBundle\Admin\ArticleAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Admin\PageAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Admin\SnippetAdditionalAdmin;
use Alengo\SuluContentExtraBundle\Content\Enhancer\NavigationLinkEnhancer;
use Alengo\SuluContentExtraBundle\Content\ResourceLoader\PageAdditionalDataResourceLoader;
use Alengo\SuluContentExtraBundle\Entity\Page;
use Alengo\SuluContentExtraBundle\Entity\PageDimensionContent;
use Alengo\SuluContentExtraBundle\Messenger\RestoreVersionEntityManagerRefreshHandler;
use Alengo\SuluContentExtraBundle\RequestWorkflow\AdditionalDataRequiredPreValidator;
use Alengo\SuluContentExtraBundle\Tests\Application\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Component\HttpKernel\SuluKernel;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowRegistryInterface;
use Sulu\Content\Application\ResourceLoader\Loader\CachedResourceLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Boots a real Sulu application with the bundle installed, in both contexts, and checks
 * that every integration point is wired. Runs against whatever Sulu and Symfony versions
 * composer resolved, so the CI matrix covers Sulu 3.0/3.1 on Symfony 6.4, 7.x and 8.x.
 */
final class KernelBootTest extends TestCase
{
    /** @var array<string, Kernel> */
    private static array $kernels = [];

    public static function tearDownAfterClass(): void
    {
        foreach (self::$kernels as $kernel) {
            $kernel->shutdown();
        }
        self::$kernels = [];
    }

    public function testAdminRegistersTheAdditionalDataTabs(): void
    {
        $container = $this->container(SuluKernel::CONTEXT_ADMIN);

        self::assertInstanceOf(PageAdditionalAdmin::class, $container->get(PageAdditionalAdmin::class));
        self::assertInstanceOf(ArticleAdditionalAdmin::class, $container->get(ArticleAdditionalAdmin::class));
        self::assertInstanceOf(SnippetAdditionalAdmin::class, $container->get(SnippetAdditionalAdmin::class));
    }

    public function testAdditionalDataTabsAppearInTheAdmin(): void
    {
        $viewRegistry = $this->container(SuluKernel::CONTEXT_ADMIN)->get('sulu_admin.view_registry');
        self::assertInstanceOf(ViewRegistry::class, $viewRegistry);

        $names = \array_map(static fn ($view): string => $view->getName(), $viewRegistry->getViews());

        self::assertContains('sulu_page.page_edit_form.additional', $names);
        self::assertNotEmpty(\preg_grep('/^sulu_article\.article\.edit_tabs_\w+\.additional$/', $names));
        self::assertNotEmpty(\preg_grep('/^sulu_snippet\.snippet\.edit_tabs(_\w+)?\.additional$/', $names));
    }

    public function testEntitiesCarryTheAdditionalDataColumn(): void
    {
        $entityManager = $this->container(SuluKernel::CONTEXT_ADMIN)->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $metadata = $entityManager->getClassMetadata(PageDimensionContent::class);
        self::assertTrue($metadata->hasField('additionalData'));

        // the bundle's content root points at the bundle's dimension content
        $pageMetadata = $entityManager->getClassMetadata(Page::class);
        self::assertSame(PageDimensionContent::class, $pageMetadata->getAssociationTargetClass('dimensionContents'));
    }

    public function testWrapsTheVersionRestoreHandlers(): void
    {
        $container = $this->container(SuluKernel::CONTEXT_ADMIN);

        foreach (['snippet', 'article', 'page'] as $type) {
            $id = \sprintf('sulu_%1$s.restore_%1$s_version_handler.em_refresh', $type);
            self::assertTrue($container->has($id), $id);
            self::assertInstanceOf(RestoreVersionEntityManagerRefreshHandler::class, $container->get($id));
        }
    }

    public function testShipsTheAdminTranslations(): void
    {
        $translator = $this->container(SuluKernel::CONTEXT_ADMIN)->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertSame('Zusätzliche Daten', $translator->trans('sulu_admin.app.additional_data', [], 'admin', 'de'));
        self::assertSame('Additional data', $translator->trans('sulu_admin.app.additional_data', [], 'admin', 'en'));
    }

    public function testRegistersThePreValidatorForReviewWorkflows(): void
    {
        if (!\interface_exists(RequestWorkflowRegistryInterface::class)) {
            self::markTestSkipped('Review workflows need Sulu 3.1.');
        }

        $registry = $this->container(SuluKernel::CONTEXT_ADMIN)->get('sulu_content.request_workflow_registry');
        self::assertInstanceOf(RequestWorkflowRegistryInterface::class, $registry);

        $preValidators = $registry->get('default')->preValidators;
        self::assertArrayHasKey(AdditionalDataRequiredPreValidator::KEY, $preValidators);
        self::assertInstanceOf(AdditionalDataRequiredPreValidator::class, $preValidators[AdditionalDataRequiredPreValidator::KEY]['pre_validator']);
    }

    public function testWebsiteWrapsTheResourceLoadersAndTheLinkEnhancer(): void
    {
        $container = $this->container(SuluKernel::CONTEXT_WEBSITE);

        // Sulu wraps every tagged resource loader in a CachedResourceLoader.
        $loader = $container->get('alengo_content_extra.page_resource_loader');
        self::assertInstanceOf(CachedResourceLoader::class, $loader);
        self::assertInstanceOf(PageAdditionalDataResourceLoader::class, (new \ReflectionProperty($loader, 'decoratedResourceLoader'))->getValue($loader));
        self::assertInstanceOf(NavigationLinkEnhancer::class, $container->get('sulu_page.page_link_dimension_content_enhancer'));
    }

    private function container(string $context): ContainerInterface
    {
        if (!isset(self::$kernels[$context])) {
            $handlerBefore = $this->currentExceptionHandler();
            $kernel = new Kernel('test', false, $context);
            $kernel->boot();

            // Older Symfony versions register a global exception handler while booting,
            // which PHPUnit reports as risky; drop it again.
            if ($this->currentExceptionHandler() !== $handlerBefore) {
                \restore_exception_handler();
            }
            self::$kernels[$context] = $kernel;
        }

        $container = self::$kernels[$context]->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function currentExceptionHandler(): ?callable
    {
        $handler = \set_exception_handler(null);
        \restore_exception_handler();

        return $handler;
    }
}
