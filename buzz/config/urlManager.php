<?php

use buzz\components\urlManager\rules\CategoriesUrlRule;
use buzz\components\urlManager\urlManager;
use yii\web\UrlNormalizer;

return [
    'class' => urlManager::class,
    'hostInfo' => 'https://fresh.buzz',
    'enableStrictParsing' => false,
    'enablePrettyUrl' => true,
    'showScriptName' => false,
    'keepUppercaseLanguageCode' => false,
    'geoIpServerVar' => 'HTTP_CF_IPCOUNTRY',
    'enableLanguageDetection' => false,
    'enableLanguagePersistence' => false,
    'enableDefaultLanguageUrlCode' => false,
    'enableLocaleUrls' => true,
    'normalizer' => [
        'class' => UrlNormalizer::class,
        'normalizeTrailingSlash' => true,
        'action' => UrlNormalizer::ACTION_REDIRECT_TEMPORARY
    ],
    'ignoreLanguageUrlPatterns' => [
        '#^users/avatar#' => '#^users/photo/[\d]+#',
        '#^articles/rating-*#' => '#^articles/rating-*#',
        '#^comments/rating-*#' => '#^comments/rating-*#'
    ],
    'languages' => [],
    'rules' => [
        'feedback' => 'site/feedback',
        'privacy-policy' => 'site/privacy-policy',
        'articles/rating-up' => 'articles/rating-up',
        'articles/rating-down' => 'articles/rating-down',
        'comments/rating-up' => 'comments/rating-up',
        'comments/rating-down' => 'comments/rating-down',
        [
            'pattern' => 'users/photo/<id:[\d]+>',
            'route' => 'users/avatar'
        ],
        '<categorySlug:[a-z0-9\-_]+>/<id:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}>' => 'articles/view',
        '<categorySlug:[a-z0-9\-_]+>/<slug:[a-z0-9\-_]+>' => 'articles/view',
        [
            'class' => CategoriesUrlRule::class
        ],
        '/' => 'articles/index',
    ],
];