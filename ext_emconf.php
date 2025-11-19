<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'u+i | Permissions',
    'description' => 'Deployable permissions via YAML configuration files',
    'category' => 'be',
    'author' => 'Sebastian Swan',
    'author_email' => 'seswan@uandi.com',
    'state' => 'stable',
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
