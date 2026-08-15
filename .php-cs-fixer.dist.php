<?php declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// 対象ディレクトリの設定
$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/examples')
    ->name('*.php')
    ->notPath('vendor')
    // tests/Pipe85 uses the PHP 8.5 pipe operator (|>), which php-cs-fixer cannot parse
    ->exclude('Pipe85')
    ->notPath('.phpunit.cache')
    ->notPath('.php-cs-fixer.cache');

// 設定
$config = new Config();
return $config
    ->setRiskyAllowed(true) // 一部のリスクのあるルールを許可
    ->setRules([
        // PSR-12準拠
        '@PSR12' => true,
        
        // strict_types宣言
        'declare_strict_types' => true,
        
        // PHPDoc関連
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'phpdoc_types' => true,
        'phpdoc_summary' => false, // 日本語コメントではピリオド不要
        'phpdoc_separation' => [
            'groups' => [
                ['param'],
                ['return'],
                ['throws'],
            ],
        ],
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
        'phpdoc_no_empty_return' => false,
        'phpdoc_order' => true,
        'phpdoc_indent' => true,
        
        // インポート関連
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'single_line_after_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        
        // 文字列
        'single_quote' => true,
        'escape_implicit_backslashes' => true,
        
        // 配列
        'array_syntax' => ['syntax' => 'short'],
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_trailing_comma_in_singleline' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        
        // クラス・メソッド関連
        'final_class' => false, // 手動で管理（すべてのクラスをfinalにはしない）
        'final_public_method_for_abstract_class' => true,
        'self_static_accessor' => true,
        'visibility_required' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        
        // スペース・インデント
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'cast_spaces' => ['space' => 'single'],
        'concat_space' => ['spacing' => 'one'],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],
        'no_spaces_around_offset' => true,
        'object_operator_without_whitespace' => true,
        'ternary_operator_spaces' => true,
        'unary_operator_spaces' => true,
        
        // その他の品質向上ルール
        'no_empty_statement' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_braces' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'simplified_if_return' => true,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        
        // 型安全性
        'void_return' => true,
        'nullable_type_declaration' => ['syntax' => 'question_mark'],
        'nullable_type_declaration_for_default_null_value' => true,
        
        // 演算子
        'standardize_not_equals' => true,
        'ternary_to_null_coalescing' => true,
        'logical_operators' => true,
        'new_with_parentheses' => true,
        'no_useless_nullsafe_operator' => true,
        
        // クラス記法
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'no_blank_lines_after_class_opening' => true,
        'no_null_property_initialization' => true,
        
        // PHP8.4対応
        'single_space_around_construct' => true,
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,
        'statement_indentation' => true,
        'no_multiple_statements_per_line' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');