<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

// Стиль кода: наборы Symfony плюс строгие правила, которые проект и так
// соблюдает руками (strict_types, финальные классы, порядок импортов).
// Проверка: make cs-check, исправление: make cs.

$finder = new Finder()
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config'])
    // reference.php генерирует Symfony при прогреве кэша
    ->notName('reference.php')
    ->append([__FILE__, __DIR__.'/rector.php', __DIR__.'/bin/console', __DIR__.'/public/index.php']);

return new Config()
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__.'/var/cache/.php-cs-fixer.cache')
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x4Migration' => true,
        '@PHP8x0Migration:risky' => true,
        '@PHPUnit10x0Migration:risky' => true,

        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        // Без префикса \ у встроенных функций и констант: пустой include плюс
        // strict снимает слэш везде, где он стоит (переопределяет @Symfony:risky)
        'native_function_invocation' => ['include' => [], 'scope' => 'namespaced', 'strict' => true],
        // fix_built_in иначе добавит слэш всем встроенным константам поверх include
        'native_constant_invocation' => ['include' => [], 'fix_built_in' => false, 'scope' => 'namespaced', 'strict' => true],
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true],
        'phpdoc_to_comment' => false,
        'concat_space' => ['spacing' => 'none'],
        'yoda_style' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters']],
        'nullable_type_declaration_for_default_null_value' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        // Идентификаторы (в том числе имена тестов) только латиницей: тексты
        // сообщений и комментарии по-русски, но no_homoglyph_names при кириллице
        // в именах подменял бы буквы латинскими двойниками — поэтому и правило
        'php_unit_method_casing' => ['case' => 'camel_case'],
        // Symfony схлопывает throw в одну строку; с длинными русскими сообщениями
        // многострочный вызов читается лучше
        'single_line_throw' => false,
    ])
    ->setFinder($finder);
