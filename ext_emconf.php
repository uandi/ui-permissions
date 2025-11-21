<?php

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
            'typo3' => '12.4.8-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
