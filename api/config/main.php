<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'api',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'api\controllers',
    'components' => [
        'urlManager' => \yii\helpers\ArrayHelper::merge(require_once __DIR__ . '/../../common/config/routing.php', [
            'rules' => require_once __DIR__ . '/routing.php'
        ]),
        'buzzUrlManager' => require_once __DIR__ . '/../../buzz/config/urlManager.php',
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
            'formatters' => [
                \yii\web\Response::FORMAT_JSON => [
                    'class' => \common\components\web\JsonResponseFormatter::class
                ]
            ]
        ],
        'session' => [
            'useCookies' => false,
        ],
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfCookie' => false,
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'i18n' => [
            'translations' => [
                '*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@api/messages',
                    'fileMap' => [
                        'app' => 'app.php',
                    ],
                ],
            ],
        ],
    ],
    'params' => $params
];