<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude(array('.git', '.github', 'vendor'))
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
$config->setRules([
        '@PSR12' => true,
        //'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
;
return $config->setUsingCache(false);
