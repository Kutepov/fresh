<?php
return [
    'aliases' => [
        '@frontendBaseUrl' => 'http://127.0.0.1:22080',
        '@backendBaseUrl' => 'http://127.0.0.1:21080'
    ],
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=mysql;dbname=fresh',
            'username' => 'fresh',
            'password' => 'fresh',
            'charset' => 'utf8mb4',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'redis' => [
            'class' => \yii\redis\Connection::class,
            'hostname' => 'redis',
            'port' => 6379,
            'database' => 3,
            'retries' => 2,
        ],
        'session' => [
            'class' => 'yii\redis\Session',
        ],
        'queue' => [
            'class' => \common\components\queue\Queue::class,
            'queueName' => 'main',
            'exchangeName' => 'main',
            'driver' => yii\queue\amqp_interop\Queue::ENQUEUE_AMQP_LIB,
            'dsn' => 'amqp://rabbitmq:rabbitmq@rabbitmq:5672/fresh',
            'as log' => \common\components\queue\LogBehavior::class
        ],
        'elasticsearch' => [
            'class' => yii\elasticsearch\Connection::class,
            'nodes' => [
                ['http_address' => 'elasticsearch:9200']
            ],
            'dslVersion' => 7
        ],
    ],
];
