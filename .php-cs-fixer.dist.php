<?php

$finder = (new PhpCsFixer\Finder())->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        '@PHP74Migration' => true,
        'fully_qualified_strict_types' => true,
        'align_multiline_comment' => true,
        'array_syntax' => true,
        'clean_namespace' => true,
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'logical_operators' => true,
        'method_chaining_indentation' => true,
        'no_empty_comment' => true,
        'ordered_traits' => true,
        'simplified_if_return' => true,
        'ternary_to_null_coalescing' => true,
        'trim_array_spaces' => true,
        'void_return' => true,
        'use_arrow_functions' => true,
        'array_push' => true,
        'assign_null_coalescing_to_coalesce_equal' => true,
        'method_argument_space' => true,
        'no_useless_else' => true,
        'no_superfluous_elseif' => true,
        'braces' => true,
        'function_declaration' => true,
        'whitespace_after_comma_in_array' => true,
        'no_unneeded_curly_braces' => true,
        'phpdoc_align' => false,
        'binary_operator_spaces' => false,
    ])->setFinder($finder);
