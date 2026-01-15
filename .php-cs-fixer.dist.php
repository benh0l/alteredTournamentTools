<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->exclude('docker')
    ->notPath('src/Kernel.php')
    ->notPath('public/index.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // PSR-12 base ruleset
        '@PSR12' => true,

        // Symfony ruleset (includes PSR-12)
        '@Symfony' => true,

        // PHP 8.2 specific rules
        '@PHP82Migration' => true,

        // Strict types declaration
        'declare_strict_types' => true,

        // Array syntax
        'array_syntax' => ['syntax' => 'short'],

        // No unused imports
        'no_unused_imports' => true,

        // Order imports alphabetically
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],

        // Trailing commas in multiline
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // No blank lines after class opening
        'no_blank_lines_after_class_opening' => true,

        // Single blank line before namespace
        'single_blank_line_before_namespace' => true,

        // Visibility required for all class members
        'visibility_required' => [
            'elements' => ['property', 'method', 'const'],
        ],

        // Final classes (unless inheritance intended)
        'final_class' => false, // Enable manually where appropriate

        // Yoda style disabled (prefer natural reading)
        'yoda_style' => false,

        // Concatenation spacing
        'concat_space' => ['spacing' => 'one'],

        // Binary operators spacing
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],

        // Method chaining indentation
        'method_chaining_indentation' => true,

        // PHPDoc alignment
        'phpdoc_align' => ['align' => 'left'],

        // No empty PHPDoc
        'no_empty_phpdoc' => true,

        // PHPDoc to comment for simple getters
        'phpdoc_to_comment' => false,

        // Global namespace import
        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => true,
            'import_constants' => true,
        ],

        // Nullable type declaration
        'nullable_type_declaration_for_default_null_value' => true,

        // Void return type
        'void_return' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache');
