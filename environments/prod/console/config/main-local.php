<?php
return [
    'components' => [
        'legacyDB' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=mysql-old;dbname=myfresh',
            'username' => 'myfresh',
            'password' => 'C8TigYAIhx6UeNcq',
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
