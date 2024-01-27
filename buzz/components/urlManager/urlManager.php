<?php namespace buzz\components\urlManager;

use common\models\Article;
use common\models\Country;
use common\services\MultilingualService;
use Yii;

class urlManager extends \codemix\localeurls\UrlManager
{
    protected $_defaultCountry = 'us';
    protected $_countryUrlParamValue = null;

    protected const DEFAULT_LANGUAGE = 'en';

    public $hrefLangs = [];

    public function init()
    {
        parent::init();
        $this->_defaultLanguage = $this->_defaultCountry;

        $service = Yii::$container->get(MultilingualService::class);
        $availableLanguages = $service->getAvailableWebsiteLanguagesForUrlManager();
        $this->languages = array_combine($availableLanguages, $availableLanguages);

        $this->hrefLangs = $service->getHrefLangs();
    }

    protected function processLocaleUrl($normalized)
    {
        parent::processLocaleUrl($normalized);

        if (Yii::$app->language === self::DEFAULT_LANGUAGE) {
            Yii::$app->language = $this->_defaultCountry;
        }

        [$languageCode, $countryCode] = explode('-', Yii::$app->language);

        if (!$countryCode) {
            $countryCode = $languageCode;
            $languageCode = null;
        }

        $this->_countryUrlParamValue = Yii::$app->language;

        $country = Country::findByCode($countryCode);

        if (!$languageCode) {
            $languageCode = $country->localeLanguage;
            Yii::$app->language = $country->locale;
        }

        define('CURRENT_LANGUAGE', $languageCode);
        define('CURRENT_COUNTRY', strtoupper($countryCode));

        if (CURRENT_COUNTRY === 'JP' && !defined('JP_CONDITION')) {
            define('JP_CONDITION', true);
        }

        $articlesLanguage = null;
        if ($country->articlesLanguages) {
            foreach ($country->articlesLanguages as $articlesLanguage) {
                if ($articlesLanguage->code === CURRENT_LANGUAGE) {
                    $articlesLanguage = $articlesLanguage->code;
                    break;
                }
            }

            if (is_null($articlesLanguage)) {
                $articlesLanguage = $country->defaultArticlesLanguage->code;
            }
        }

        define('CURRENT_TIMEZONE', $country->timezone);
        define('CURRENT_ARTICLES_LANGUAGE', $articlesLanguage);

        $pathInfo = $this->_request->getPathInfo();
        /** Редирект новостей со старых языковых урлов */
        if (
            preg_match('#^([a-z]{2}-[a-z]{2})/([a-z-0-9]*)/([a-z-0-9]*)(?:\?|$)#', $pathInfo, $m) &&
            !in_array($m[1], $this->languages, true) &&
            $article = Article::find()->where(['slug' => $m[3]])->one(null, false)
        ) {
            Yii::$app->response->redirect($article->sharingUrl, 301);
            Yii::$app->end();
        }
    }

    public function createUrl($params)
    {
        if (!isset($params[$this->languageParam]) && $this->_countryUrlParamValue) {
            $params[$this->languageParam] = $this->_countryUrlParamValue;
        }
        return parent::createUrl($params);
    }

    public function getDefaultCountry()
    {
        return $this->_defaultCountry;
    }
}