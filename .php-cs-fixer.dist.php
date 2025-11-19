<?php

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();

$config->getFinder()
    ->in(__DIR__);

$config
    ->setRiskyAllowed(true)
    ->addRules([
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        'modernize_strpos' => true, // needs PHP 8+ or polyfill
    ]);

return $config;
