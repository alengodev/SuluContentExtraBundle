<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle;

use Alengo\SuluContentExtraBundle\DependencyInjection\Compiler\AdditionalDataResourceLoaderPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlengoContentExtraBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Priority > 0 so this runs before Sulu's ResourceLoaderCacheCompilerPass,
        // which wraps every tagged resource loader in a CachedResourceLoader.
        $container->addCompilerPass(
            new AdditionalDataResourceLoaderPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            10,
        );
    }
}
