<?php

declare(strict_types=1);

use Ergebnis\PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'node_modules',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ])
    ->notPath([
        'bootstrap/cache/*',
        'storage/*.php',
    ]);

$ruleSet = Config\RuleSet\Php82::create();

$config = Config\Factory::fromRuleSet($ruleSet);

//$config->setCacheFile(__DIR__ . '/.build/php-cs-fixer/.php-cs-fixer.cache');
$config->setFinder($finder);
$config->setRules(
    array_merge($config->getRules(), [
        'final_class' => false,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'concat_space' => [
            'spacing' => 'one'
        ],
    ])
);

return $config;
