<?php
require_once dirname(__DIR__) . '/components/helpers/helpers.php';

return [
    'name' => 'Fresh',
    'language' => 'ru',
    'sourceLanguage' => 'ru',
    'timeZone' => 'UTC',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'bootstrap' => [
        \common\config\bootstrap\Container::class,
        'queue',
        'subscriptionsQueue',
        'countersQueue',
        'usersSourcesQueue'
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'i18n' => [
            'translations' => [
                'yii2mod.settings' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@yii2mod/settings/messages',
                ],
                // ...
            ],
        ],
        'settings' => [
            'class' => 'yii2mod\settings\components\Settings',
        ],
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],
        'db' => [
            'commandClass' => \common\components\db\Command::class,
            'on afterOpen' => static function ($event) {
                $event->sender->createCommand("SET time_zone='UTC';")->execute();
                $event->sender->createCommand("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));")->execute();
            },
        ],
        'mutex' => [
            'class' => \yii\redis\Mutex::class,
            'expire' => 900
        ],
        'cache' => [
            'class' => \yii\redis\Cache::class,
//            'serializer' => ['cacheSerialize', 'cacheUnserialize']
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'except' => [\yii\db\IntegrityException::class]
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logFile' => '@app/runtime/logs/db-integrity.log',
                    'categories' => [\yii\db\IntegrityException::class]
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'categories' => [\yii\queue\Queue::class],
                    'levels' => ['error', 'warning'],
                    'logFile' => '@app/runtime/logs/queue.log',
                ],
                [
                    'class' => \common\components\logs\JsonFileTarget::class,
//                    'categories' => ['critical', 'warning', 'info', 'debug', 'scrapers', 'scrapers-critical', 'application'],
                    'levels' => ['error', 'warning'],
                    'logFile' => '@app/runtime/logs/app.json.log',
                    'except' => ['yii\web\HttpException:404', 'yii\i18n\PhpMessageSource::loadMessages']
                ],
                [
                    'class' => \common\components\logs\JsonFileTarget::class,
                    'categories' => [\yii\queue\Queue::class],
                    'logFile' => '@app/runtime/logs/queue.json.log',
                ]
            ],
        ],
        'authClientCollection' => require __DIR__ . '/oauth.php',
        'user' => [
            'class' => \yii\web\User::class,
            'identityClass' => \common\models\User::class,
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity', 'httpOnly' => true],
        ],
    ]
];
