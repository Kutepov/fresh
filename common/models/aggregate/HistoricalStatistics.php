<?php namespace common\models\aggregate;

use Yii;

/**
 * This is the model class for table "historical_statistics".
 *
 * @property string $date
 * @property string $category_id
 * @property string $country
 * @property string $articles_language
 * @property string $platform
 * @property int $users_count
 * @property integer $clicks
 * @property integer $views
 */
class HistoricalStatistics extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'historical_statistics';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['date', 'country', 'articles_language', 'platform'], 'required'],
            [['date'], 'safe'],
            [['clicks', 'views'], 'integer'],
            [['category_id'], 'string', 'max' => 36],
            [['country', 'articles_language'], 'string', 'max' => 2],
            [['platform'], 'string', 'max' => 8],
            [['date', 'country', 'articles_language', 'platform'], 'unique', 'targetAttribute' => ['date', 'country', 'articles_language', 'platform']],
            ['users_count', 'int']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'date' => 'Date',
            'category_id' => 'Category ID',
            'country' => 'Country',
            'articles_language' => 'Articles Language',
            'platform' => 'Platform',
            'clicks' => 'Clicks',
            'views' => 'Views',
        ];
    }
}
