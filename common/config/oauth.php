<?php

return [
    'class' => \yii\authclient\Collection::class,
    'clients' => [
        'vkontakte' => [
            'class' => \yii\authclient\clients\VKontakte::class,
            'clientId' => env('OAUTH_VK_CLIENT_ID'),
            'clientSecret' => env('OAUTH_VK_CLIENT_SECRET'),
            'viewOptions' => [
                'popupWidth' => 654,
                'popupHeight' => 346,
            ]
        ],
        'facebook' => [
            'class' => \yii\authclient\clients\Facebook::class,
            'clientId' => env('OAUTH_FB_CLIENT_ID'),
            'clientSecret' => env('OAUTH_FB_CLIENT_SECRET'),
        ],
        'google' => [
            'class' => \yii\authclient\clients\Google::class,
            'clientId' => env('OAUTH_GOOGLE_CLIENT_ID'),
            'clientSecret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
        ],
        'apple' => [
            'class' => \common\components\authclient\clients\Apple::class,
            'clientId' => 'com.freshnews.fresh.service',
            'teamId' => '8529S2466P',
            'keyFileId' => '6TJ5GTU5PS',
            'keyFilePath' => '@root/common/components/authclient/apple_keys/AuthKey_6TJ5GTU5PS.p8'
        ],
    ],
];