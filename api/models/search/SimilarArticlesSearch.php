<?php namespace api\models\search;

use common\components\helpers\Api;
use common\components\validators\ArticlesLanguageCodeValidator;
use common\components\validators\CategoryNameToIdConverter;
use common\components\validators\CountryCodeValidator;
use common\components\validators\DefaultOnError;
use common\components\validators\SourceIdValidator;
use common\components\validators\SourceUrlIdValidator;
use common\components\validators\UUIDValidator;
use common\models\Article;

/**
 * @property Article|null $article
 * Class SimilarArticlesSearch
 * @package api\models\search
 */
class SimilarArticlesSearch extends SearchForm
{
    public $source = [];
    public $sourceUrl = [];
    public $category = [];
    public $title;
    public $country;
    public $articlesLanguage;
    public $limit = 3;
    public $articleId;
    public $skipBanned = true;

    private $_article = null;

    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['country', 'articlesLanguage', 'category', 'articleId', 'source', 'sourceUrl', 'limit', 'skipBanned', 'title']
        ];
    }

    public function rules()
    {
        return [
            ['title', 'string'],
            [['country', 'articleId'], 'required'],
            ['limit', 'integer', 'min' => 1, 'max' => 15],
            ['limit', DefaultOnError::class, 'value' => 3],
            ['country', CountryCodeValidator::class],
            ['articlesLanguage', ArticlesLanguageCodeValidator::class],
            ['sourceUrl', SourceUrlIdValidator::class, 'skipOnEmpty' => Api::versionLessThan(Api::V_2_20) || !Api::isRequestFromApp()],
            ['source', SourceIdValidator::class, 'skipOnEmpty' => !Api::versionLessThan(Api::V_2_20)],
            ['articleId', UUIDValidator::class],
            ['articleId', 'required'],
            ['skipBanned', 'boolean'],
            ['category', 'each', 'rule' => ['string']],
            ['category', CategoryNameToIdConverter::class],
        ];
    }

    public function afterValidate()
    {
        if (!$this->article && !$this->title) {
            $this->addError('articleId');
        }

        parent::afterValidate();
    }

    public function getArticle()
    {
        if ($this->articleId && is_null($this->_article)) {
            $this->_article = Article::findById($this->articleId);
        }

        return $this->_article;
    }
}