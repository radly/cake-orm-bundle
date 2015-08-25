<?php

return [
    'cake_orm' => [
        'datasources' => [
            'default' => [
                'className' => 'Cake\Database\Connection',
                'driver' => 'Cake\Database\Driver\Postgres',
                'persistent' => false,
                'host' => 'localhost',
                'username' => 'db_user',
                'password' => 'db_pass',
                'database' => 'db_name',
                'encoding' => 'utf8',
                'timezone' => 'UTC',
                'cacheMetadata' => true
            ]
        ],
        'cache' => [
            'default' => [
                'className' => 'File',
                'path' => CACHE_DIR . DS . 'cake',
            ],
            '_cake_core_' => [
                'className' => 'File',
                'prefix' => 'radphp_cake_core_',
                'path' => CACHE_DIR . DS . 'cake' . DS . 'persistent',
                'serialize' => true,
                'duration' => '+2 minutes',
            ],
            '_cake_model_' => [
                'className' => 'File',
                'prefix' => 'radphp_cake_model_',
                'path' => CACHE_DIR . DS . 'cake' . DS . 'models',
                'serialize' => true,
                'duration' => '+2 minutes',
            ],
        ]
    ]
];
