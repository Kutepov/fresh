<?php namespace common\models\elasticsearch;

use yii\base\Exception;
use yii\elasticsearch\ActiveQuery;
use yii\elasticsearch\ActiveRecord;

/**
 * @property string $created_at
 * @property string $source_id
 * @property string $country
 * @property string|null $language
 * @property string $category_id
 * @property string $category_name
 * @property string $title
 * @property bool $banned_words
 * @property string $body
 *
 * Class Article
 * @package common\models\elasticsearch
 */
class Article extends ActiveRecord
{
    public static $currentLocale = null;

    public function attributes(): array
    {
        return [
            'created_at',
            'source_id',
            'source_url_id',
            'country',
            'language',
            'category_id',
            'category_name',
            'title',
            'banned_words',
            'body'
        ];
    }

    public static function indexForLocale(string $locale): string
    {
        return 'articlesv2-' . strtolower($locale);
    }

    /**
     * @throws \yii\base\Exception
     */
    public static function index(): string
    {
        if (is_null(self::$currentLocale)) {
            throw new Exception('Index locale not specified.');
        }

        return self::indexForLocale(self::$currentLocale);
    }

    public static function find(): ActiveQuery
    {
        self::$currentLocale = null;
        return parent::find();
    }

    public static function findOne($condition)
    {
        self::$currentLocale = null;
        return parent::findOne($condition);
    }

    public static function deleteAll($condition = [])
    {
        self::$currentLocale = null;
        return parent::deleteAll($condition);
    }

    public static function deleteAllForLocale(string $locale, $condition = [])
    {
        self::$currentLocale = $locale;
        return parent::deleteAll($condition);
    }

    public static function findByLocale(string $locale): ActiveQuery
    {
        self::$currentLocale = $locale;
        return parent::find()->from(self::indexForLocale($locale));
    }

    public static function findOneByLocale($condition, string $locale): ?self
    {
        self::$currentLocale = $locale;
        return parent::findOne($condition);
    }
}