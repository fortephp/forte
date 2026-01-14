<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withPreparedSets(deadCode: true)
    ->withTypeCoverageLevel(0)
    ->withCodeQualityLevel(0)
    ->withSkip([
        RemoveNonExistingVarAnnotationRector::class => [
            __DIR__.'/src/Ast/*',
            __DIR__.'/src/Rewriting/*',
        ],
    ]);
