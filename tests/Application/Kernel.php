<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\Tests\Application;

use Alengo\SuluContentExtraBundle\AlengoContentExtraBundle;
use Gedmo\Exception;
use Sulu\Article\Infrastructure\Symfony\HttpKernel\SuluArticleBundle;
use Sulu\Bundle\AudienceTargetingBundle\SuluAudienceTargetingBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowRegistryInterface;
use Sulu\Snippet\Infrastructure\Symfony\HttpKernel\SuluSnippetBundle;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * A minimal Sulu application with the bundle installed, booted by the functional tests.
 */
final class Kernel extends SuluTestKernel
{
    public function registerBundles(): iterable
    {
        foreach (parent::registerBundles() as $bundle) {
            // not configured in this application
            if (!$bundle instanceof SuluAudienceTargetingBundle) {
                yield $bundle;
            }
        }

        yield new SuluSnippetBundle();
        yield new SuluArticleBundle();
        yield new AlengoContentExtraBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/config/config_' . $this->getContext() . '.yml');

        // Review workflows exist since Sulu 3.1; 3.0.x rejects their config.
        if (self::CONTEXT_ADMIN === $this->getContext() && \interface_exists(RequestWorkflowRegistryInterface::class)) {
            $loader->load(__DIR__ . '/config/review_workflows.yml');
        }
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->getContext() . '/' . $this->environment;
    }

    /**
     * @return array<mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();
        $parameters['gedmo_directory'] = \dirname((string) (new \ReflectionClass(Exception::class))->getFileName());

        return $parameters;
    }
}
