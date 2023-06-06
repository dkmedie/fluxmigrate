<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Flux Migrate',
    'description' => 'Tool to help migrate from the TYPO3 extension Flux to a solution closer to core',
    'category' => 'cli',
    'state' => 'alpha',
    'clearCacheOnLoad' => 1,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-11.5.99',
            'php' => '7.4.0-7.4.99'
        ]
    ]

];

