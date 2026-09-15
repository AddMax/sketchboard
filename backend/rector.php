<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;

// Автоматический рефакторинг: make rector-check (что бы изменилось) и
// make rector (применить). Наборы подобраны под уровень PHP 8.5 и
// уже принятые в проекте атрибуты вместо аннотаций.
return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withCache(cacheDirectory: __DIR__.'/var/cache/rector')
    ->withRootFiles()
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withComposerBased(symfony: true, doctrine: true, phpunit: true)
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // Сортируют именованные аргументы по алфавиту и схлопывают многострочные
        // атрибуты OpenAPI в одну строку — читаемость важнее
        SortAttributeNamedArgsRector::class,
        SortCallLikeNamedArgsRector::class,
        // В тестах принято self::assert…, это же правило держит php-cs-fixer
        PreferPHPUnitThisCallRector::class,
        // «null !== $x» яснее, чем «$x instanceof Y», когда речь о наличии значения
        FlipTypeControlToUseExclusiveTypeRector::class,
    ]);
