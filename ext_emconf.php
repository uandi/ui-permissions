<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Deployable Permissions',
    'description' => 'Deployable TYPO3 backend user permissions via YAML configuration files',
    'category' => 'be',
    'author' => 'Sebastian Swan',
    'author_company' => 'u+i interact GmbH & Co. KG',
    'author_email' => 'mail@uandi.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
