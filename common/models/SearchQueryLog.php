<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "search_queries_logs".
 *
 * @property string $created_at
 * @property string $country
 * @property string $locale
 * @property string $query
 */
class SearchQueryLog extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'search_queries_logs';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['locale', 'query', 'country'], 'required'],
            [['locale'], 'string', 'max' => 5],
            [['country'], 'string', 'length' => 2],
            [['query'], 'string', 'max' => 320],
        ];
    }
}
