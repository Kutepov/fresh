<?php namespace api\models\search;

use common\components\helpers\Api;
use common\components\validators\ArticlesLanguageCodeValidator;
use common\components\validators\CountryCodeValidator;
use common\components\validators\DefaultOnError;
use common\components\validators\SourceIdValidator;
use common\components\validators\SourceUrlIdValidator;

/**
 * Class ArticlesGroupedByCategorySearch
 * @package api\models\search
 */
class ArticlesGroupedByCategorySearch extends SearchForm
{
    public $source = [];
    public $sourceUrl = [];
    public $country;
    public $articlesLanguage;
    public $limit;
    public $skipBanned = true;

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['sourceUrl', 'country', 'articlesLanguage', 'source', 'limit', 'skipBanned']
        ];
    }

    public function rules()
    {
        return [
            [['country', 'limit'], 'required'],
            ['country', CountryCodeValidator::class],
            ['articlesLanguage', ArticlesLanguageCodeValidator::class],
            ['limit', 'integer', 'min' => 1, 'max' => 50],
            ['limit', DefaultOnError::class, 'value' => 10],
            ['sourceUrl', SourceUrlIdValidator::class, 'skipOnEmpty' => Api::versionLessThan(Api::V_2_20) || !Api::isRequestFromApp()],
            ['source', SourceIdValidator::class, 'skipOnEmpty' => !Api::versionLessThan(Api::V_2_20)],
            ['skipBanned', 'boolean']
        ];
    }
}