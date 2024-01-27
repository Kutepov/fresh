<?php namespace api\models\search;

use common\components\helpers\Api;
use common\components\validators\ArticlesLanguageCodeValidator;
use common\components\validators\CategoryNameToIdConverter;
use common\components\validators\CountryCodeValidator;
use common\components\validators\DefaultOnError;
use common\components\validators\SourceIdValidator;
use common\components\validators\SourceUrlIdValidator;

class TopArticlesSearch extends SearchForm
{
    /** TODO: Symfony legacy */
    public const SCENARIO_DEPRECATED = 'deprecated';

    public $source = [];
    public $sourceUrl = [];
    public $category = [];
    public $country;
    public $articlesLanguage;
    public $limit;
    public $skipBanned = true;
    public $widget = false;

    public function scenarios()
    {
        return [
            self::SCENARIO_DEPRECATED => ['country', 'articlesLanguage', 'source', 'sourceUrl', 'limit', 'skipBanned'],
            self::SCENARIO_DEFAULT => ['country', 'articlesLanguage', 'category', 'source', 'sourceUrl', 'limit', 'skipBanned', 'widget']
        ];
    }

    public function rules()
    {
        return [
            ['country', CountryCodeValidator::class],
            ['articlesLanguage', ArticlesLanguageCodeValidator::class],
            ['limit', 'integer', 'min' => 1, 'max' => 5],
            ['limit', DefaultOnError::class, 'value' => 3],
            ['sourceUrl', SourceUrlIdValidator::class, 'skipOnEmpty' => Api::versionLessThan(Api::V_2_20) || !Api::isRequestFromApp()],
            ['source', SourceIdValidator::class, 'skipOnEmpty' => !Api::versionLessThan(Api::V_2_20)],
            ['category', 'each', 'rule' => ['string']],
            ['category', CategoryNameToIdConverter::class],
            [['skipBanned', 'widget'], 'boolean']
        ];
    }
}