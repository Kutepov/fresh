<?php namespace api\models\search;

use Carbon\Carbon;
use common\components\helpers\Api;
use common\components\validators\ArticlesLanguageCodeValidator;
use common\components\validators\CategoryNameToIdConverter;
use common\components\validators\DateTimeValidator;
use common\components\validators\DefaultOnError;
use common\components\validators\SourceIdValidator;
use common\components\validators\CountryCodeValidator;
use common\components\validators\SourceUrlIdValidator;
use common\components\validators\UUIDValidator;
use common\models\Category;
use common\models\Country;
use common\models\Language;

/**
 * @property-read $locale
 * @property-read $countryModel
 * @property-read $languageModel
 */
class ArticlesSearch extends SearchForm
{
    public const SCENARIO_FIND = 'find';
    public const SCENARIO_SEARCH = 'search';

    public const SCENARIO_NEW_ARTICLES_AMOUNT = 'newArticlesAmount';
    public const SCENARIO_FIND_BY_CATEGORY_NAME = 'findByCategoryName';
    public const SCENARIO_DEPRECATED_FIND = 'deprecatedFind';

    public $source = [];
    public $sourceUrl = [];
    public $category = [];
    public $ids = [];
    public $country;
    public $articlesLanguage;
    /** @var Carbon|null */
    public $createdBefore = null;
    /** @var Carbon|null */
    public $createdAfter;
    public $categoryName;

    public $skipBanned = true;

    public $offset;
    public $limit;

    public $widget = false;

    public $language;
    public $query;

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['language', 'country', 'articlesLanguage'],
            self::SCENARIO_FIND => ['source', 'sourceUrl', 'category', 'country', 'articlesLanguage', 'createdBefore', 'limit', 'offset', 'skipBanned', 'ids', 'widget'],
            self::SCENARIO_NEW_ARTICLES_AMOUNT => ['sourceUrl', 'country', 'articlesLanguage', 'createdAfter', 'skipBanned'],
            self::SCENARIO_DEPRECATED_FIND => ['source', 'country', 'articlesLanguage', 'createdBefore', 'limit', 'offset', 'skipBanned'],
            self::SCENARIO_FIND_BY_CATEGORY_NAME => ['categoryName', 'source', 'country', 'articlesLanguage', 'createdBefore', 'limit', 'offset', 'skipBanned'],
            self::SCENARIO_SEARCH => ['source', 'sourceUrl', 'category', 'country', 'articlesLanguage', 'limit', 'offset', 'skipBanned', 'query', 'language']
        ];
    }

    public function rules()
    {
        return [
            ['query', 'required'],
            ['query', 'string', 'min' => 3, 'max' => 300],
            ['language', 'string', 'length' => 2],
            ['country', CountryCodeValidator::class],
            ['articlesLanguage', ArticlesLanguageCodeValidator::class],
            ['offset', 'integer', 'min' => 0],
            ['offset', DefaultOnError::class, 'value' => 0],
            ['limit', 'integer', 'min' => 1, 'max' => 100],
            ['limit', DefaultOnError::class, 'value' => 10],
            ['category', 'each', 'rule' => ['string']],
            ['category', CategoryNameToIdConverter::class],
            ['categoryName', 'string'],
            ['categoryName', CategoryNameToIdConverter::class],
            ['sourceUrl', SourceUrlIdValidator::class, 'skipOnEmpty' => Api::versionLessThan(Api::V_2_20) || !Api::isRequestFromApp()],
            ['source', SourceIdValidator::class, 'skipOnEmpty' => !Api::versionLessThan(Api::V_2_20)],
            ['createdBefore', DateTimeValidator::class],
            [['skipBanned', 'widget'], 'boolean'],
            ['ids', 'each', 'rule' => [UUIDValidator::class]],
            ['ids', DefaultOnError::class, 'value' => []],
            ['createdAfter', DateTimeValidator::class]
        ];
    }

    public function getLocale(): string
    {
        if ($this->articlesLanguage) {
            return strtolower($this->languageModel->locale);
        }

        return strtolower($this->countryModel->locale);
    }

    public function getCountryModel()
    {
        return Country::findByCode($this->country);
    }

    public function getLanguageModel()
    {
        return Language::findByCode($this->articlesLanguage);
    }

    public function getCategoryModel(): ?Category
    {
        if (!$this->categoryName) {
            return null;
        }

        $query = Category::find()->where(['name' => $this->categoryName]);
        if (defined('CURRENT_LANGUAGE')) {
            $query->localized(CURRENT_LANGUAGE);
        }

        return $query->one();
    }
}