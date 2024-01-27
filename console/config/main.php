<?php

use common\config\bootstrap\Symfony;
use yii\helpers\UnsetArrayValue;

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log', Symfony::class, 'queue', 'subscriptionsQueue',
        \buzz\config\bootstrap\Container::class
    ],
    'controllerNamespace' => 'console\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'controllerMap' => [
        'fixture' => [
            'class' => 'yii\console\controllers\FixtureController',
            'namespace' => 'common\fixtures',
        ],
        'message' => [
            'class' => 'console\controllers\MessageController'
        ],
        'schedule' => omnilight\scheduling\ScheduleController::class,
        'shell' => yii\shell\ShellController::class,
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationPath' => [
                '@app/migrations',
                '@yii/rbac/migrations',
            ],
        ],
    ],
    'components' => [
        'urlManager' => \yii\helpers\ArrayHelper::merge(require_once __DIR__ . '/../../buzz/config/urlManager.php', [
            'baseUrl' => '/',
            'hostInfo' => 'https://fresh.buzz'
        ])
    ],
    'params' => $params,
];
