<?php namespace common\models\aggregate;

use common\models\Article;
use Yii;

/**
 * This is the model class for table "historical_push_notifications".
 *
 * @property string $article_id
 * @property string $created_at
 * @property string $date
 * @property string $country
 * @property string $articles_language
 * @property string $platform
 * @property integer $sent_amount
 * @property integer $viewed_amount
 * @property integer $clicked_amount
 */
class HistoricalPushNotifications extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'historical_push_notifications';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at', 'date'], 'safe'],
            [['sent_amount', 'viewed_amount', 'clicked_amount'], 'integer'],
            [['country'], 'string', 'max' => 2],
            [['articles_language'], 'string', 'max' => 5],
            [['platform'], 'string', 'max' => 16],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'article_id' => 'Article ID',
            'created_at' => 'Created At',
            'date' => 'Date',
            'country' => 'Country',
            'articles_language' => 'Articles Language',
            'platform' => 'Platform',
            'sent_amount' => 'Sent Amount',
            'viewed_amount' => 'Viewed Amount',
            'clicked_amount' => 'Clicked Amount',
        ];
    }

    public function getArticle()
    {
        return $this->hasOne(Article::class, [
            'id' => 'article_id'
        ]);
    }
}
