<?php

declare(strict_types=1);

$EM_CONF['ui_permissions'] = [
    'title' => 'u+i | Permissions',
    'description' => 'Deployable TYPO3 permissions via YAML configuration files',
    'category' => 'be',
    'author' => 'Sebastian Swan',
    'author_company' => 'u+i interact GmbH & Co. KG',
    'author_email' => 'mail@uandi.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
