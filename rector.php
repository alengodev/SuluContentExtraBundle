<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRootFiles()
    ->withPHPStanConfigs([__DIR__ . '/phpstan.dist.neon'])
    ->withImportNames(importShortClasses: false)
    ->withPreparedSets(codeQuality: true)
    // PHP sets follow the "php" constraint of composer.json (^8.2), so nothing newer slips in.
    ->withPhpSets();
