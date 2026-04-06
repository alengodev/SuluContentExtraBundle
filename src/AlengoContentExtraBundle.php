<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle;

use Alengo\SuluContentExtraBundle\DependencyInjection\Compiler\OverrideTreeListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlengoContentExtraBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new OverrideTreeListenerPass());
    }
}
