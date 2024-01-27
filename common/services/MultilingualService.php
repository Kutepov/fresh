<?php namespace common\services;

use common\components\caching\Cache;
use common\exceptions\CountryNotFoundException;
use common\models\Country;
use common\models\Language;
use yii\caching\TagDependency;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * @property-read string[] $availableLanguagesCodes
 * @property-read string[] $CISCountriesCodes
 */
class MultilingualService
{
    /** @var array */
    private $availableLanguages = [];

    /** Дефолтный язык для стран СНГ */
    private const DEFAULT_CIS_LANGUAGE = 'ru';
    /** Дефолтный язык для стран НЕ СНГ */
    private const DEFAULT_LANGUAGE = 'en';

    public function getDefaultsArticlesLanguageCodeForCountry($countryCode): ?string
    {
        try {
            $country = Country::findByCode($countryCode);
        } catch (CountryNotFoundException $e) {
            return null;
        }

        return $country->defaultArticlesLanguage->code;
    }

    public function getAvailableLocalizationsForUrlManager(): array
    {
        $countries = Country::find()
            ->andWhere(['NOT IN', 'code', ['RU', 'BY']])
            ->cache(
                Cache::DURATION_COUNTRIES_LIST,
                new TagDependency(['tags' => [Cache::TAG_LANGUAGES_LIST]])
            )->all();

        $result = [];
        foreach ($countries as $country) {
            $languages = [];
            if ($country->defaultArticlesLanguage) {
                $languages = [$country->defaultArticlesLanguage->code];
            }
            if (!$languages) {
                $languages = [$country->localeLanguage];
            } else {
                $languages = array_map(function ($lang) use ($country) {
                    return $lang . '-' . $country->code;
                }, $languages);
            }

            foreach ($languages as $language) {
                if (isset($result[$language])) {
                    $result[$language][] = $country->code;
                } else {
                    $result[$language] = [$country->code];
                }
            }
        }

        return $result;
    }

    public function getAvailableWebsiteLanguagesForUrlManager(): array
    {
        $countries = Country::find()
            ->andWhere(['NOT IN', 'code', ['RU', 'BY']])
            ->cache(
                Cache::DURATION_COUNTRIES_LIST,
                new TagDependency(['tags' => [Cache::TAG_LANGUAGES_LIST]])
            )->all();

        $result = [];
        foreach ($countries as $country) {
            $result = ArrayHelper::merge($result, $country->urlLanguages);
        }

        return $result;
    }

    public function getHrefLangs(): array
    {
        $countries = Country::find()
            ->andWhere(['NOT IN', 'code', ['RU', 'BY']])
            ->cache(
                Cache::DURATION_COUNTRIES_LIST,
                new TagDependency(['tags' => [Cache::TAG_LANGUAGES_LIST]])
            )->all();

        $result = [];
        foreach ($countries as $country) {
            if (!$country->articlesLanguages) {
                $result[strtolower($country->code)] = str_replace('_', '-', $country->locale);
            } else {
                foreach ($country->articlesLanguages as $articlesLanguage) {
                    $result[$articlesLanguage->code . '-' . strtolower($country->code)] = $articlesLanguage->code . '-' . $country->code;
                }
            }
        }

        return $result;
    }

    /**
     * Подбор наиболее подходящего языка для заданной страны
     * @param $code
     * @return string
     */
    public function getLanguageCodeForCountry($code): string
    {
        $code = strtoupper($code);
        $availableLanguagesInCountry = self::COUNTRIES_LANGUAGES[$code] ?? [];

        $language = null;

        foreach ($availableLanguagesInCountry as $aLang) {
            if (in_array($aLang, $this->getAvailableLanguagesCodes())) {
                $language = $aLang;
                break;
            }
        }

        if (is_null($language)) {
            if ($this->isCISCountryCode($code)) {
                $language = self::DEFAULT_CIS_LANGUAGE;
            } else {
                $language = self::DEFAULT_LANGUAGE;
            }
        }

        return $language;
    }

    public function isSupportedCountry($code): bool
    {
        return in_array(strtoupper($code), $this->getAvailableCountriesCodes());
    }

    public function isSupportedLanguage($code): bool
    {
        return in_array($code, $this->getAvailableLanguagesCodes());
    }

    public function getAvailableCountriesForDropDownList()
    {
        return Country::find()->indexBy('code')->select('name')->orderBy('name')->column();
    }

    public function getAvailableCountriesCodes()
    {
        $countries = $this->getAvailableCountries();

        return ArrayHelper::getColumn($countries, 'code');
    }

    public function getAvailableCountries()
    {
        return Country::find()
            ->cache(
                Cache::DURATION_COUNTRIES_LIST,
                new TagDependency(['tags' => [Cache::TAG_LANGUAGES_LIST]])
            )
            ->all();
    }

    /**
     * Список кодов доступных на сайте языков
     * @return string[]
     */
    public function getAvailableLanguagesCodes(): array
    {
        return array_keys($this->getAvailableLanguages());
    }

    /**
     * Массив со списком всех доступных языков ['code' => 'language]
     * @return array
     */
    public function getAvailableLanguages(string $indexBy = 'code', string $field = 'name'): array
    {
        if (empty($this->availableLanguages)) {
            $this->availableLanguages = Language::find()
                ->indexBy($indexBy)
                ->select($field)
                ->cache(
                    Cache::DURATION_LANGUAGES_LIST,
                    new TagDependency(['tags' => [Cache::TAG_LANGUAGES_LIST, $indexBy, $field]])
                )
                ->column();
        }

        return $this->availableLanguages;
    }

    /**
     * @param $tags
     */
    public function invalidateCache($tags)
    {
        TagDependency::invalidate(Yii::$app->cache, $tags);
    }

    /**
     * Проверка на то, относится ли страна к СНГ
     * @param $code
     * @return bool
     */
    public function isCISCountryCode($code): bool
    {
        $code = strtoupper($code);
        return in_array(strtoupper($code), $this->getCISCountriesCodes());
    }

    /**
     * Коды стран СНГ
     * @return string[]
     */
    private function getCISCountriesCodes()
    {
        return [
            'AZ', 'AM', 'BY', 'KZ', 'KG', 'MD',
            'RU', 'TJ', 'TM', 'UZ', 'GE', 'UA',
            'MN', 'AF', 'LV', 'LT'
        ];
    }

    private const COUNTRIES_LANGUAGES = [
        'AF' => [
            'ps',
            'uz',
            'tk'
        ],
        'AL' => [
            'sq'
        ],
        'DZ' => [
            'ar',
            'fr'
        ],
        'AD' => [
            'ca'
        ],
        'AO' => [
            'pt'
        ],
        'AG' => [
            'en'
        ],
        'AR' => [
            'es',
            'gn'
        ],
        'AM' => [
            'hy',
            'ru'
        ],
        'AU' => [
            'en'
        ],
        'AT' => [
            'de',
            'hr',
            'sl',
            'cs',
            'hu',
            'sk',
            'ro'
        ],
        'AZ' => [
            'az',
            'hy'
        ],
        'BS' => [
            'en'
        ],
        'BH' => [
            'ar'
        ],
        'BD' => [
            'bn'
        ],
        'BB' => [
            'en'
        ],
        'BY' => [
            'be',
            'ru'
        ],
        'BE' => [
            'nl',
            'fr',
            'de'
        ],
        'BZ' => [
            'en',
            'es'
        ],
        'BJ' => [
            'fr'
        ],
        'BT' => [
            'dz'
        ],
        'BO' => [
            'es',
            'ay',
            'qu'
        ],
        'BA' => [
            'bs',
            'hr',
            'sr'
        ],
        'BW' => [
            'en',
            'tn'
        ],
        'BR' => [
            'pt',
            'de'
        ],
        'BN' => [
            'ml'
        ],
        'BG' => [
            'bg'
        ],
        'BF' => [
            'fr',
            'ff'
        ],
        'BI' => [
            'fr',
            'rn'
        ],
        'KH' => [
            'km'
        ],
        'CM' => [
            'en',
            'fr'
        ],
        'CA' => [
            'en',
            'fr',
            'cr',
            'iu'
        ],
        'CV' => [
            'pt'
        ],
        'CF' => [
            'fr',
            'sg'
        ],
        'TD' => [
            'ar',
            'fr'
        ],
        'CL' => [
            'es'
        ],
        'CN' => [
            'zh'
        ],
        'CO' => [
            'es'
        ],
        'KM' => [
            'ar',
            'fr'
        ],
        'CD' => [
            'fr',
            'ln'
        ],
        'CR' => [
            'es'
        ],
        'CI' => [
            'fr'
        ],
        'HR' => [
            'hr',
            'it'
        ],
        'CU' => [
            'es'
        ],
        'CY' => [
            'el',
            'tr',
            'hy'
        ],
        'CZ' => [
            'cs',
            'sk'
        ],
        'DK' => [
            'da',
            'fo',
            'de',
            'kl'
        ],
        'DJ' => [
            'ar',
            'fr'
        ],
        'DM' => [
            'en'
        ],
        'DO' => [
            'es'
        ],
        'TL' => [
            'pt'
        ],
        'EC' => [
            'es'
        ],
        'EG' => [
            'ar'
        ],
        'SV' => [
            'es'
        ],
        'GQ' => [
            'es',
            'fr'
        ],
        'ER' => [
            'ar',
            'ti'
        ],
        'EE' => [
            'et',
            'ru'
        ],
        'ET' => [
            'am',
            'en'
        ],
        'FJ' => [
            'en',
            'fj'
        ],
        'FI' => [
            'fi',
            'sv',
            'se'
        ],
        'FR' => [
            'fr',
            'co',
            'br'
        ],
        'GA' => [
            'fr'
        ],
        'GM' => [
            'en'
        ],
        'GE' => [
            'ab',
            'ka',
            'os',
            'ru'
        ],
        'DE' => [
            'de',
            'da',
            'ro'
        ],
        'GH' => [
            'en',
            'ee',
            'tw'
        ],
        'GR' => [
            'el'
        ],
        'GD' => [
            'en'
        ],
        'GT' => [
            'es'
        ],
        'GN' => [
            'pt'
        ],
        'GY' => [
            'en'
        ],
        'HT' => [
            'fr',
            'ht'
        ],
        'HN' => [
            'es',
            'en'
        ],
        'HU' => [
            'hu'
        ],
        'IS' => [
            'is'
        ],
        'IN' => [
            'en',
            'as',
            'bn',
            'fr',
            'gu',
            'hi',
            'kn',
            'ks',
            'ml',
            'mr',
            'ne',
            'or',
            'pa',
            'sa',
            'sd',
            'ta',
            'te',
            'ur'
        ],
        'ID' => [
            'id',
            'jv',
            'ml',
            'su'
        ],
        'IR' => [
            'fa',
            'ku',
            'ar'
        ],
        'IQ' => [
            'ar',
            'ku'
        ],
        'IE' => [
            'ga',
            'en'
        ],
        'IL' => [
            'he',
            'ar'
        ],
        'IT' => [
            'it',
            'sq',
            'ca',
            'hr',
            'fr',
            'de',
            'el',
            'sc',
            'sl',
            'en'
        ],
        'JM' => [
            'en'
        ],
        'JP' => [
            'ja'
        ],
        'JO' => [
            'ar',
            'en'
        ],
        'KZ' => [
            'kk',
            'ru'
        ],
        'KE' => [
            'en',
            'sw'
        ],
        'KI' => [
            'en'
        ],
        'KP' => [
            'ko'
        ],
        'KR' => [
            'ko'
        ],
        'KW' => [
            'ar'
        ],
        'KG' => [
            'ky',
            'ru'
        ],
        'LA' => [
            'lo'
        ],
        'LV' => [
            'lv',
            'ru'
        ],
        'LB' => [
            'ar',
            'fr',
            'hy'
        ],
        'LS' => [
            'en',
            'st'
        ],
        'LR' => [
            'en'
        ],
        'LY' => [
            'ar'
        ],
        'LI' => [
            'de'
        ],
        'LT' => [
            'lt'
        ],
        'LU' => [
            'fr',
            'de',
            'lb'
        ],
        'MK' => [
            'mk',
            'sq',
            'tr'
        ],
        'MG' => [
            'fr',
            'en',
            'mg'
        ],
        'MW' => [
            'ny',
            'en'
        ],
        'MY' => [
            'ml',
            'en'
        ],
        'MV' => [
            'dv'
        ],
        'ML' => [
            'fr'
        ],
        'MT' => [
            'mt',
            'en',
            'it'
        ],
        'MH' => [
            'en',
            'mh'
        ],
        'MR' => [
            'ar',
            'fr',
            'ff',
            'wo'
        ],
        'MU' => [
            'en'
        ],
        'MX' => [
            'es'
        ],
        'FM' => [
            'en'
        ],
        'MD' => [
            'ro',
            'ru',
            'uk'
        ],
        'MC' => [
            'fr'
        ],
        'MN' => [
            'mn'
        ],
        'ME' => [
            'sq',
            'bs',
            'hr',
            'sr'
        ],
        'MA' => [
            'ar'
        ],
        'MZ' => [
            'pt'
        ],
        'MM' => [
            'my'
        ],
        'NA' => [
            'en',
            'af',
            'de'
        ],
        'NR' => [
            'en'
        ],
        'NP' => [
            'ne'
        ],
        'NL' => [
            'nl',
            'li',
            'en'
        ],
        'NZ' => [
            'en'
        ],
        'NI' => [
            'es'
        ],
        'NE' => [
            'fr',
            'ha',
            'kr'
        ],
        'NG' => [
            'en',
            'ha',
            'yo',
            'ig'
        ],
        'NO' => [
            'no',
            'se',
            'ro'
        ],
        'OM' => [
            'ar'
        ],
        'PK' => [
            'ur',
            'en',
            'pa',
            'ps',
            'sd'
        ],
        'PW' => [
            'en',
            'ja'
        ],
        'PS' => [
            'ar'
        ],
        'PA' => [
            'es'
        ],
        'PG' => [
            'en',
            'ho'
        ],
        'PY' => [
            'es',
            'gn'
        ],
        'PE' => [
            'es',
            'ay',
            'qu'
        ],
        'PH' => [
            'ar',
            'en',
            'es',
            'tl'
        ],
        'PL' => [
            'pl',
            'de',
            'lt'
        ],
        'PT' => [
            'pt'
        ],
        'QA' => [
            'ar'
        ],
        'RO' => [
            'ro',
            'hy'
        ],
        'RU' => [
            'ru',
            'hy',
            'av',
            'az',
            'ba',
            'ce',
            'cv',
            'kv',
            'os',
            'tt'
        ],
        'RW' => [
            'en',
            'fr',
            'rw'
        ],
        'KN' => [
            'en'
        ],
        'LC' => [
            'en'
        ],
        'VC' => [
            'en'
        ],
        'WS' => [
            'en',
            'sm'
        ],
        'SM' => [
            'it'
        ],
        'ST' => [
            'pt'
        ],
        'SA' => [
            'ar'
        ],
        'SN' => [
            'fr',
            'ff',
            'wo'
        ],
        'RS' => [
            'sr',
            'sq',
            'hr',
            'hu',
            'ro',
            'sk'
        ],
        'SC' => [
            'en',
            'fr'
        ],
        'SL' => [
            'en'
        ],
        'SG' => [
            'en',
            'ml',
            'zh',
            'ta'
        ],
        'SK' => [
            'sk'
        ],
        'SI' => [
            'sl',
            'hu',
            'it'
        ],
        'SB' => [
            'en'
        ],
        'SO' => [
            'so',
            'ar'
        ],
        'ZA' => [
            'af',
            'en',
            'st',
            'ts',
            'tn',
            've',
            'xh'
        ],
        'ES' => [
            'es',
            'ca',
            'gl',
            'eu',
            'oc'
        ],
        'LK' => [
            'si',
            'ta'
        ],
        'SD' => [
            'ar',
            'en'
        ],
        'SR' => [
            'nl'
        ],
        'SZ' => [
            'en'
        ],
        'SE' => [
            'sv',
            'fi',
            'ro',
            'se',
            'yi'
        ],
        'CH' => [
            'de',
            'fr',
            'it',
            'rm'
        ],
        'SY' => [
            'ar'
        ],
        'TJ' => [
            'tg',
            'ru'
        ],
        'TZ' => [
            'sw',
            'en'
        ],
        'TH' => [
            'th'
        ],
        'TG' => [
            'fr'
        ],
        'TO' => [
            'en'
        ],
        'TT' => [
            'en'
        ],
        'TN' => [
            'ar',
            'fr'
        ],
        'TR' => [
            'tr'
        ],
        'TM' => [
            'tk',
            'ru'
        ],
        'TV' => [
            'en'
        ],
        'UG' => [
            'en',
            'sw'
        ],
        'UA' => [
            'uk',
            'ru'
        ],
        'AE' => [
            'ar'
        ],
        'GB' => [
            'en'
        ],
        'US' => [
            'en',
            'es',
            'nv',
            'ch',
            'fr',
            'sm'
        ],
        'UY' => [
            'es'
        ],
        'UZ' => [
            'uz',
            'ru'
        ],
        'VU' => [
            'bi',
            'en',
            'fr'
        ],
        'VA' => [
            'it'
        ],
        'VE' => [
            'es'
        ],
        'VN' => [
            'vi'
        ],
        'YE' => [
            'ar'
        ],
        'ZM' => [
            'en'
        ]
    ];
}