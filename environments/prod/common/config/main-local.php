<?php
return [
    'aliases' => [
        '@frontendBaseUrl' => 'https://api.myfresh.app',
        '@backendBaseUrl' => 'https://admin.myfresh.app'
    ],
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=mysql;dbname=myfresh',
            'username' => 'myfresh',
            'password' => 'C8TigYAIhx6UeNcq',
            'charset' => 'utf8mb4',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            'transport' => [
                'class' => 'Swift_SmtpTransport',
                'host' => 'mprx.rcrtv.net',
                'port' => '25',
                'encryption' => 'tls',
            ],
        ],
        'redis' => [
            'class' => \yii\redis\Connection::class,
            'hostname' => 'redis',
            'port' => 6379,
            'database' => 1,
            'retries' => 2
        ],
        'queue' => [
            'class' => \common\components\queue\Queue::class,
            'queueName' => 'main',
            'exchangeName' => 'main',
            'driver' => yii\queue\amqp_interop\Queue::ENQUEUE_AMQP_LIB,
            'dsn' => 'amqp://rabbitmq:gvU4GgfCvSMJVMy2@rabbitmq:5672/fresh',
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