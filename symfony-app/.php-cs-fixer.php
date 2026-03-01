<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base rulesets
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Strict types
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,

        // Imports
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'no_unused_imports' => true,
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],

        // Arrays
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'normalize_index_brace' => true,

        // Strings
        'single_quote' => true,
        'explicit_string_variable' => true,

        // Operators & control flow
        'binary_operator_spaces' => ['default' => 'single_space'],
        'unary_operator_spaces' => true,
        'not_operator_with_successor_space' => false,
        'concat_space' => ['spacing' => 'one'],
        'ternary_operator_spaces' => true,
        'ternary_to_null_coalescing' => true,

        // Blank lines & spacing
        'blank_line_before_statement' => ['statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try']],
        'method_chaining_indentation' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],

        // Classes & methods
        'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one', 'const' => 'one']],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'keep_multiple_spaces_after_comma' => false],
        'single_trait_insert_per_statement' => true,
        'visibility_required' => ['elements' => ['property', 'method', 'const']],
        'self_accessor' => true,
        'no_null_property_initialization' => true,
        'final_class' => false,

        // Casting
        'modernize_types_casting' => true,

        // PHPDoc
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'phpdoc_align' => ['align' => 'left'],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => false],

        // Misc
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'return_assignment' => false,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
