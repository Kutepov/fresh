<?php namespace common\models\pivot;

use Carbon\Carbon;
use common\contracts\RatingObject;
use common\models\Article;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "articles_rating".
 *
 * @property string $created_at
 * @property string $updated_at
 * @property string $article_id
 * @property integer $app_id
 * @property integer $rating
 * @property string $country
 *
 * @property Article $article
 */
class ArticleRating extends \yii\db\ActiveRecord implements RatingObject
{
    public const PLUS = 1;
    public const MINUS = -1;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'articles_rating';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => Carbon::now('UTC')
            ]
        ];
    }
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at', 'updated_at'], 'safe'],
            [['article_id', 'app_id'], 'required'],
            [['rating', 'app_id'], 'integer'],
            [['article_id'], 'string', 'max' => 36],
            [['article_id', 'app_id'], 'unique', 'targetAttribute' => ['article_id', 'app_id']],
            [['article_id'], 'exist', 'skipOnError' => true, 'targetClass' => Article::class, 'targetAttribute' => ['article_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'article_id' => 'Article ID',
            'rating' => 'Rating',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getArticle()
    {
        return $this->hasOne(Article::class, ['id' => 'article_id']);
    }

    public static function find()
    {
        return (new \common\queries\ArticleRating(get_called_class()));
    }

    public function getRatingValue(): int
    {
        return $this->rating;
    }
}
