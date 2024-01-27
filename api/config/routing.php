<?php
return [
    'GET /ping' => 'ping/index',

    /** Список и определение стран */
    'GET /country/of-ip' => 'countries/of-ip',
    'GET /country/find' => 'countries/index',

    'GET /check_force_update_needed/<platform:(ios|android)>' => 'apps/check-force-update-needed',
    'POST /subscription/validate' => 'apps/validate-android-subscription',
    'POST /subscription/validate-ios' => 'apps/validate-ios-subscription',

    /** Параметры рекламы */
    'GET /recreativ/ad/enabled/<platform:(ios|android)>' => 'advertising/recreativ-ad-is-enabled',
    'GET /ad/get-provider/<platform:(ios|android)>' => 'advertising/get-ad-provider-deprecated',
    'GET /ad/v2/get-provider/<platform:(ios|android)>' => 'advertising/get-ad-provider',
    'GET /ad/config' => 'advertising/config',

    /** Источники новостей */
    'GET /source/find' => 'sources/find',

    /** Категории */
    'GET /category/find' => 'categories/index-deprecated',
    'GET /category/find/v3' => 'categories/index',

    /** Статистика */
    'POST /statistic/article/clicked/<articleId:[\w\-]+>' => 'statistics/articles/clicked-deprecated',
    'POST /statistic/article/showed/<articleId:[\w\-]+>' => 'statistics/articles/viewed-deprecated',
    'POST /statistic/article/v2/clicked/<articleId:[\w\-]+>' => 'statistics/articles/clicked',
    'POST /statistic/article/v2/showed/<articleId:[\w\-]+>' => 'statistics/articles/viewed',
    'POST /statistic/article/v2/showed_bunch' => 'statistics/articles/bulk-viewed',
    'POST /statistic/article/shared' => 'statistics/articles/shared',

    'POST /statistics/push-notifications/viewed/<id:[\w\-]+>' => '/statistics/push-notifications/viewed',
    'POST /statistics/push-notifications/clicked/<id:[\w\-]+>' => '/statistics/push-notifications/clicked',

    /** Статьи */
    'GET /article/body/<articleId:[\w\-]+>' => 'articles/body',
    'GET /article/bodies' => 'articles/bodies',
    'GET /article/find' => 'articles/index-deprecated',
    'GET /article/find/v2' => 'articles/index',
    'GET /article/find/ids' => 'articles/by-ids',
    'GET /article/find/slug' => 'articles/by-slug',
    'GET /article/find/category' => 'articles/index-by-category',
    'GET /article/find/top' => 'articles/top-deprecated',
    'GET /article/find/top/v2' => 'articles/top',
    'GET /article/find/newest' => 'articles/newest',
    'GET /article/find/similar' => 'articles/similar',
    'GET /article/check-country' => 'articles/check-country',
    'GET /article/same-amount' => 'articles/same-amount',
    'GET /same-article/get' => 'articles/same',
    'POST /articles/new-amount' => 'articles/new-amount',
    'POST /articles/rating-up' => 'articles/rating-up',
    'POST /articles/rating-down' => 'articles/rating-down',

    /** Комментарии */
    'GET /comments/list' => 'comments/list',
    [
        'pattern' => 'comments/list/top',
        'route' => 'comments/list',
        'defaults' => [
            'top' => 1
        ]
    ],
    'GET /comments/answers-list' => 'comments/answers-list',
    'POST /comments/add' => 'comments/add',
    'POST /comments/edit' => 'comments/edit',
    'POST /comments/delete' => 'comments/delete',
    'POST /comments/rating-up' => 'comments/rating-up',
    'POST /comments/rating-down' => 'comments/rating-down',

    /** Профиль */
    'POST,DELETE /users/profile-photo' => 'user/profile/photo',
    'POST,DELETE /users/profile/photo' => 'user/profile/photo',
    'GET /users/profile' => 'user/profile/get',
    'POST /users/profile' => 'user/profile/index',
    'DELETE /users/profile' => 'user/profile/delete',
    'POST /users/profile/<action:[\w\-]+>' => 'user/profile/<action>',
    'GET /users/comments' => 'user/comments/list',
    [
        'pattern' => '/users/comments/top',
        'route' => 'user/comments/list',
        'defaults' => [
            'top' => 1
        ]
    ],

    /** Учетная запись */
    'POST /users/auth/detach/<clientId:[\w\-\d]+>' => 'user/credentials/auth-detach',
    'POST /users/auth/<clientId:[\w\-\d]+>' => 'user/credentials/auth',
    'POST /users/<action:[\w\-]+>' => 'user/credentials/<action>',

    'GET /articles/webview-filters' => 'webview/index',

    /** Опрос */
    'POST /survey/good' => 'survey/good',
    'POST /survey/bad' => 'survey/bad',
    'POST /survey/feedback' => 'survey/feedback',

    /** Папки и каталог */
    'GET /folders' => 'v3/folders/index',
    'GET /catalog/categories' => 'v3/catalog/categories',
    'GET /catalog/sources/recommended' => 'v3/catalog/recommended',
    'GET /catalog/sources/recommended/<type:(twitter|telegram|youtube|reddit)>' => 'v3/catalog/recommended',
    'GET /catalog/sources/search' => 'v3/sources/search',
    'GET /catalog/sources/search/<type:(twitter|telegram|youtube|reddit)>' => 'v3/sources/search',
    'GET /catalog/sources/preview' => 'v3/sources/preview',
    'POST /catalog/sources/subscribe' => 'v3/sources/subscribe',
    'POST /catalog/sources/unsubscribe' => 'v3/sources/unsubscribe',
    'POST /catalog/sources/subscribe-bulk' => 'v3/sources/subscribe-bulk',
    'POST /catalog/sources/unsubscribe-bulk' => 'v3/sources/unsubscribe-bulk',
];