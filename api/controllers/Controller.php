<?php namespace api\controllers;

use common\components\helpers\Api;
use common\contracts\Logger;
use common\models\App;
use common\models\Country;
use common\services\AppsService;
use common\services\MultilingualService;
use common\services\SourcesService;
use common\services\SourcesUrlsService;
use Yii;
use yii\filters\ContentNegotiator;
use yii\rest\Serializer;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class Controller extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    public $language;
    public $articlesLanguage;
    public $country;
    public $platform;
    public $appVersion;
    public $deviceId;
    public $articlesPreviewType;

    protected AppsService $appsService;
    protected Logger $logger;
    private MultilingualService $multilingualService;
    private SourcesUrlsService $sourcesUrlsService;
    private SourcesService $sourcesService;

    public $serializer = Serializer::class;

    protected $uaRuFallback = false;

    /** @var App */
    protected $currentApp;

    public function behaviors()
    {
        /** Fallback для старых версий приложения на Flutter */
        $this->language = strtolower(Yii::$app->request->headers->get(Api::API_HEADER_LANGUAGE,
            Yii::$app->request->get('language')
        ));

        $this->country = strtoupper(Yii::$app->request->headers->get(Api::API_HEADER_COUNTRY,
            Yii::$app->request->get('country',
                Yii::$app->request->get(
                    'countryCode'
                )
            )
        ));

        if (Api::version(Api::V_2_01)) {
            $this->articlesLanguage = Yii::$app->request->headers->get(Api::API_HEADER_ARTICLES_LANGUAGE);
        }

        if (!$this->country) {
            $this->country = geoCountryCode();
        }

        if (!in_array($this->country, $this->multilingualService->getAvailableCountriesCodes())) {
            $this->country = 'US';
        }

        if (!in_array($this->language, $this->multilingualService->getAvailableLanguagesCodes())) {
            $this->language = null;
        }

        $this->country = strtoupper($this->country);

        define('API_COUNTRY', strtoupper($this->country));

        if (API_COUNTRY === 'JP' && !defined('JP_CONDITION')) {
            define('JP_CONDITION', true);
        }

        if (!$this->language) {
            $this->language = $this->multilingualService->getLanguageCodeForCountry($this->country);
        }
        Yii::$app->language = $this->language;

        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            'authenticator' => [
                'class' => \yii\filters\auth\HttpBearerAuth::class,
                'header' => 'Authorization',
                'optional' => ['*']
            ]
        ];
    }

    public function __construct($id, $module, $config = [])
    {
        $this->appsService = Yii::$container->get(AppsService::class);
        $this->logger = Yii::$container->get(Logger::class);
        $this->multilingualService = Yii::$container->get(MultilingualService::class);
        $this->sourcesUrlsService = Yii::$container->get(SourcesUrlsService::class);
        $this->sourcesService = Yii::$container->get(SourcesService::class);

        parent::__construct($id, $module, $config);
    }

    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $queryParams = Yii::$app->request->getQueryParams();

            if ($languageHeader = Yii::$app->request->headers->get(Api::API_HEADER_LANGUAGE)) {
                $queryParams['language'] = $languageHeader;
            }

            $countryHeader = Yii::$app->request->headers->get(Api::API_HEADER_COUNTRY);

            if (!$countryHeader && !($this instanceof CountriesController) && Api::version(Api::V_2_01)) {
                $this->logger->warning('Empty country header');
            }

            $queryParams['country'] = $this->country;
            $queryParams['countryCode'] = $this->country;

            $this->platform = Yii::$app->request->headers->get(Api::API_HEADER_PLATFORM,
                Yii::$app->request->get('devicePlatform',
                    Yii::$app->request->get('platform')
                )
            );

            define('API_PLATFORM', $this->platform);

            if ($platformHeader = Yii::$app->request->headers->get(Api::API_HEADER_PLATFORM)) {
                $queryParams['devicePlatform'] = $platformHeader;
                $queryParams['platform'] = $platformHeader;
            }

            $this->deviceId = Yii::$app->request->headers->get(Api::API_HEADER_UID,
                Yii::$app->request->get('deviceId')
            );

            if ($deviceIdHeader = Yii::$app->request->headers->get(Api::API_HEADER_UID)) {
                $queryParams['deviceId'] = $deviceIdHeader;
            }

            $this->appVersion = Yii::$app->request->headers->get(Api::API_HEADER_APP_VERSION);
            define('API_APP_VERSION', preg_replace('#-DEBUG$#iu', '', $this->appVersion));

            if (Api::version(Api::V_2_01)) {
                $this->articlesLanguage = Yii::$app->request->headers->get(Api::API_HEADER_ARTICLES_LANGUAGE);
            }
            /** Fallback для старых версий API */
            if (!$this->articlesLanguage && $this->country) {
                $this->articlesLanguage = $this->multilingualService->getDefaultsArticlesLanguageCodeForCountry($this->country);
            }

            $queryParams['articlesLanguage'] = $this->articlesLanguage;

            $this->articlesPreviewType = Yii::$app->request->headers->get(Api::API_HEADER_ARTICLES_PREVIEW_TYPE, Country::PREVIEW_TYPE_SMALL);

            if ($this->appsService->validateApp($this->platform, $this->deviceId)) {
                $sourcesUrls = null;
                $sources = null;
                $categories = null;

                if (Yii::$app->controller->id === 'articles' && in_array(Yii::$app->controller->action->id, ['index', 'top', 'index-by-category'])) {
                    if (Yii::$app->request->get('feed', false)) {
                        $sourcesUrls = Yii::$app->request->get('sourceUrl');
                        if (!$sourcesUrls) {
                            $sourcesUrls = [];
                        }
                    }

                    $sources = Yii::$app->request->get('source');
                    if (!$sources) {
                        $sources = [];
                    }

                    $categories = Yii::$app->request->get('category');
                    if (!$categories) {
                        $categories = [];
                    }
                }

                $this->currentApp = $this->appsService->findOrCreate(
                    $this->platform,
                    $this->deviceId,
                    $this->appVersion,
                    $this->country,
                    $this->language,
                    $this->articlesLanguage,
                    $sources,
                    $categories,
                    $sourcesUrls,
                    $this->articlesPreviewType
                );
            }

            if (!$this->currentApp && Api::version(Api::V_2_0)) {
                throw new BadRequestHttpException();
            }

            define('API_APP_ID', $this->currentApp->id);

            if (Api::version(Api::V_2_0)) {
                $queryParams['skipBanned'] = 0;
            } else {
                $queryParams['skipBanned'] = 1;
            }

            /** Фоллбек для украинских источников с русским переводом */
            if ($this->country === 'UA') {
                unset($queryParams['articlesLanguage']);
                $this->articlesLanguage = null;

                if (($sources = Yii::$app->request->get('source', [])) &&
                    is_array($sources)
                ) {
                    $queryParams['source'] = $this->sourcesService->convertUaRuSourcesIdsToUkIfNeeded($sources);
                }

                if (
                    ($sourcesUrls = Yii::$app->request->get('sourceUrl', [])) &&
                    is_array($sourcesUrls)
                ) {
                    $queryParams['sourceUrl'] = $this->sourcesUrlsService->convertUaRuSourcesUrlsIdsToUkIfNeeded($sourcesUrls);
                }
            }

            Yii::$app->request->setQueryParams($queryParams);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function afterAction($action, $result)
    {
        $result = parent::afterAction($action, $result);
        return $this->serializeData($result);
    }

    /**
     * Serializes the specified data.
     * The default implementation will create a serializer based on the configuration given by [[serializer]].
     * It then uses the serializer to serialize the given data.
     * @param mixed $data the data to be serialized
     * @return mixed the serialized data.
     */
    protected function serializeData($data)
    {
        return Yii::createObject($this->serializer)->serialize($data);
    }
}