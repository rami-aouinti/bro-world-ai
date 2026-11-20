<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src/Dto')
    ->append([
        __DIR__.'/src/Service/AiBriefGenerator.php',
        __DIR__.'/tests/Unit/AiBriefGeneratorTest.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'concat_space' => ['spacing' => 'none'],
        'phpdoc_separation' => false,
        'single_line_empty_body' => false,
        'yoda_style' => false,
    ])
    ->setFinder($finder);
