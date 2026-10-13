<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Generated while the functional tests boot, so absent in a fresh checkout: fnmatch patterns,
    // because Rector rejects skip paths that do not exist when it runs in CI.
    ->withSkip(['*/tests/Application/var/*', '*/tests/Application/config/reference.php'])
    ->withRootFiles()
    ->withPHPStanConfigs([__DIR__ . '/phpstan.dist.neon'])
    ->withImportNames(importShortClasses: false)
    ->withPreparedSets(codeQuality: true)
    // PHP sets follow the "php" constraint of composer.json (^8.2), so nothing newer slips in.
    ->withPhpSets();
