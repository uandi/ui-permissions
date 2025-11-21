<?php

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();

$config->getFinder()
    ->notName(['ext_emconf.php'])
    ->in(__DIR__);

$config
    ->setRiskyAllowed(true)
    ->addRules([
        '@PHP7x4Migration' => true,
        '@PHP7x4Migration:risky' => true,
        '@PHP8x0Migration' => true,
        '@PHP8x0Migration:risky' => true,
        'modernize_strpos' => true,
    ]);

return $config;
