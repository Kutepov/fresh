<?php
return [
    'bootstrap' => ['gii'],
    'modules' => [
        'gii' => 'yii\gii\Module',
    ],
    'components' => [
        'legacyDB' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=host.docker.internal;dbname=fresh',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8mb4',
            'attributes' => [
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
            ],
            'on afterOpen' => function ($event) {
                $event->sender->createCommand("SET time_zone='UTC';")->execute();
            },
        ],
    ]
];
