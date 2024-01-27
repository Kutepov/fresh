<?php declare(strict_types=1);

namespace api\models\search;

use common\components\validators\ArticlesLanguageCodeValidator;
use common\components\validators\CountryCodeValidator;
use common\components\validators\DefaultOnError;
use yii\base\UserException;

class CatalogSourceUrlSearch extends SearchForm
{
    public $query;
    public $type;

    public $country;
    public $articlesLanguage;

    public $offset;
    public $limit;

    public function rules()
    {
        return [
            ['query', 'required'],
            ['query', 'string', 'min' => 2, 'max' => 100],
            ['articlesLanguage', ArticlesLanguageCodeValidator::class],
            ['country', CountryCodeValidator::class],
            ['offset', 'integer', 'min' => 0],
            ['offset', DefaultOnError::class, 'value' => 0],
            ['limit', 'integer', 'min' => 5, 'max' => 30],
            ['limit', DefaultOnError::class, 'value' => 30],
            ['type', 'safe']
        ];
    }

    public function afterValidate()
    {
        parent::afterValidate();
        if ($this->hasErrors('query')) {
            throw new UserException('Query is empty');
        }
        if ($this->hasErrors('country')) {
            throw new UserException('Wrong Country');
        }
        if ($this->hasErrors('articlesLanguage')) {
            throw new UserException('Wrong articles language');
        }
    }
}