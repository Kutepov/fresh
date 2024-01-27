<?php namespace common\config\bootstrap;

use Aws\Sdk;
use common\contracts\Notifier;
use common\contracts\Poster;
use common\services\adapty\AdaptyService;
use common\services\IAP\GoogleValidator;
use common\services\notifier\Telegram;
use common\services\translator\Amazon;
use common\contracts\Translator;
use Google\Cloud\Translate\V2\TranslateClient;
use paragraph1\phpFCM\Client;
use Pushok\AuthProvider\Token;
use yii\base\BootstrapInterface;

class Container implements BootstrapInterface
{
    public function bootstrap($app)
    {
        $container = \Yii::$container;

        $container->setSingleton(\common\components\guzzle\Guzzle::class);
        $container->setSingleton(\common\services\ArticlesIndexer::class);
        $container->setSingleton(\common\components\guzzle\ArticlePageRequestMatcher::class);
        $container->setSingleton(\common\services\MultilingualService::class);

        $container->setSingletons([
            \common\contracts\Logger::class => \common\services\Logger::class,
            \common\services\RestrictedWordsChecker::class => [
                'restrictedWords' => [
                    'covid', 'coronavirus', 'вирус', 'коронавирус', 'вірус', 'коронавірус', 'ковид', 'ковід',
                    'SARS-CoV-2', 'Koronawirus'
                ],
                'bannedWords' => [
                    'фонд Ріната Ахметова'
                ]
            ]
        ]);

        $container->set(AdaptyService::class, [
            'apiKey' => env('ADAPTY_API_KEY')
        ]);

        $container->set(Notifier::class, [
            'class' => Telegram::class,
            'botApiToken' => env('TELEGRAM_BOT_API_TOKEN'),
            'groupId' => env('TELEGRAM_ISSUES_GROUP_ID'),
            'adaptyIosGroupId' => env('TELEGRAM_ADAPTY_EVENTS_IOS_GROUP_ID'),
            'adaptyAndroidGroupId' => env('TELEGRAM_ADAPTY_EVENTS_ANDROID_GROUP_ID'),
        ]);


        $container->set(Poster::class, [
            'class' => \common\services\poster\Telegram::class,
            'botApiToken' => env('TELEGRAM_POSTING_BOT_API_TOKEN')
        ]);

        $container->set(\common\services\ProxyService::class, [
            'checkerUrl' => 'https://api.posylka.net/v1/tools/detect-country',
            'accounts' => [
//                env('PROXY_ACCOUNT_ES'),
                env('PROXY_ACCOUNT_CA'),
                env('PROXY_ACCOUNT_DE'),
                env('PROXY_ACCOUNT_US'),
                env('PROXY_ACCOUNT_FR'),
            ]
        ]);

        $container->set(Sdk::class, function ($container, $params, $config) {
            return new Sdk([
                'credentials' => [
                    'key' => env('AWS_SDK_KEY'),
                    'secret' => env('AWS_SDK_SECRET'),
                ],
                'region' => 'us-east-1',
                'version' => 'latest'
            ]);
        });

        $container->set(Translator::class, Amazon::class);

        $container->set(\common\components\scrapers\common\services\HashImageService::class, [], ['key' => '1175019452957712']);

        /** Google IAP Validator */
        $container->set(\Google\Client::class, function ($container, $params, $config) {
            $client = new \Google\Client();
            $client->setAuthConfig([
                'type' => 'service_account',
                'project_id' => 'posylka-dev',
                'private_key_id' => env('GOOGLE_SERVICE_ACCOUNT_KEY_ID'),
                'private_key' => str_replace("\\n", "\n", env('GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY')),
                'client_email' => 'posylka@posylka-dev.iam.gserviceaccount.com',
                'client_id' => env('GOOGLE_SERVICE_CLIENT_ID'),
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://oauth2.googleapis.com/token',
                'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/posylka%40posylka-dev.iam.gserviceaccount.com'
            ]);

            return $client;
        });

        $container->set(GoogleValidator::class, [
            'subscriptionsIds' => [
                'subscription.monthly',
                'subscription.yearly'
            ],
            'packageName' => 'com.freshnews.fresh'
        ]);

        $container->set(\Pushok\Client::class, function ($container, $params, $config) {
            $authProvider = Token::create([
                'key_id' => env('APPLE_KEY_ID'),
                'team_id' => env('APPLE_TEAM_ID'),
                'app_bundle_id' => env('APPLE_BUNDLE_ID'),
                'private_key_path' => \Yii::getAlias(env('APPLE_KEY_PATH')),
                'private_key_secret' => null
            ]);

            return new \Pushok\Client(
                $authProvider,
                env('APPLE_APNS_ENV_PROD') === 'true'
            );
        });

        $container->set(Client::class, function ($container, $params, $config) {
            $client = new Client();
            $client->setApiKey(env('FCM_SERVER_KEY'));
            $client->injectHttpClient(new \GuzzleHttp\Client());

            return $client;
        });

        \Yii::$container->set(TranslateClient::class, function ($container, $params, $config) {
            return new TranslateClient([
                'key' => env('GOOGLE_TRANSLATE_API_KEY')
            ]);
        });
    }
}