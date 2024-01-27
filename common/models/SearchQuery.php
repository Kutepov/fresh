<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "search_queries".
 *
 * @property string $created_at
 * @property string $query
 * @property string $country
 * @property string $locale
 * @property integer $amount
 */
class SearchQuery extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'search_queries';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['query', 'locale', 'country'], 'required'],
            [['amount'], 'integer'],
            [['query'], 'string', 'max' => 255],
            [['locale'], 'string', 'max' => 5],
            [['country'], 'string', 'length' => 2],
        ];
    }
}
