<?php namespace api\models\search;

use common\components\helpers\Api;
use common\components\validators\ArticlesLanguageCodeValidator;
use common\components\validators\CountryCodeValidator;
use common\components\validators\SourceIdValidator;
use common\components\validators\SourceUrlIdValidator;
use common\components\validators\UUIDValidator;

class SameArticlesSearch extends SearchForm
{
    public const SCENARIO_BULK = 'bulk';

    public $source = [];
    public $sourceUrl = [];
    public $country;
    public $articlesLanguage;
    public $skipBanned = true;
    public $parentArticleId;
    public $parentArticlesIds = [];

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['country', 'articlesLanguage', 'source', 'sourceUrl', 'parentArticleId', 'skipBanned'],
            self::SCENARIO_BULK => ['country', 'articlesLanguage', 'source', 'sourceUrl', 'parentArticlesIds', 'skipBanned']
        ];
    }

    public function rules()
    {
        return [
            [['country', 'parentArticleId'], 'required'],
            ['country', CountryCodeValidator::class],
            ['articlesLanguage', ArticlesLanguageCodeValidator::class],
            ['sourceUrl', SourceUrlIdValidator::class, 'skipOnEmpty' => Api::versionLessThan(Api::V_2_20) || !Api::isRequestFromApp()],
            ['source', SourceIdValidator::class, 'skipOnEmpty' => !Api::versionLessThan(Api::V_2_20)],
            ['parentArticleId', UUIDValidator::class],
            ['parentArticlesIds', 'each', 'rule' => [UUIDValidator::class]],
            ['parentArticlesIds', 'required', 'isEmpty' => function ($value) {
                return !is_array($value) || count($value) < 1;
            }],
            ['skipBanned', 'boolean']
        ];
    }
}