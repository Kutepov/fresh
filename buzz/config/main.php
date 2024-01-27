<?php

use yii\i18n\PhpMessageSource;
use yii\log\FileTarget;
use common\models\User;

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php'
);

return [
    'id' => 'app-buzz',
    'language' => 'en',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        \buzz\config\bootstrap\Container::class,
        'log'
    ],

    'controllerNamespace' => 'buzz\controllers',
    'defaultRoute' => 'articles/index',
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-buzz',
        ],
        'user' => [
            'identityClass' => User::class,
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-buzz', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the frontend
            'name' => 'advanced-buzz',
        ],
        'view' => [
            'class' => \buzz\components\View::class
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'assetManager' => [
            'class' => \yii\web\AssetManager::class,
            'appendTimestamp' => true
        ],
        'urlManager' => require __DIR__ . '/urlManager.php',
        'i18n' => [
            'translations' => [
                'app*' => [
                    'class' => PhpMessageSource::class,
                    'sourceLanguage' => 'en',
                    'basePath' => '@buzz/messages',
                    'fileMap' => [
                        'app' => 'app.php',
                        'app/meta' => 'meta.php',
                    ],
                    'on missingTranslation' => [\buzz\components\TranslationEventHandler::class, 'handleMissingTranslation']
                ],
            ],

        ],
    ],
    'params' => $params
];
