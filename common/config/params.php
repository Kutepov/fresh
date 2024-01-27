<?php
return [
    'languages' => [
        'ru' => 'Русский',
        'uk' => 'Украниский',
        'en' => 'English',
        'de' => 'Deutsch',
        'pl' => 'Polski',
        'pt' => 'Português',
        'fr' => 'Français',
        'es' => 'Espagnol'
    ],
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'senderEmail' => 'noreply@example.com',
    'noreplyEmail' => 'noreply@myfresh.app',
    'infoEmail' => 'info@myfresh.app',
    'senderName' => 'Example.com mailer',
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,
    'androidUrl' => 'https://play.google.com/store/apps/details?id=com.freshnews.fresh',
    'iosUrl' => 'https://apps.apple.com/app/apple-store/id1503209593?pt=119070971&ct=freshbuzz&mt=8',
     'reCAPTCHA.siteKey' => env('RECAPTCHA_SITEKEY'),
    'reCAPTCHA.secretKey' => env('RECAPTCHA_SECRETKEY'),
];
