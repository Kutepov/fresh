<?php namespace common\models\aggregate;

use Carbon\Carbon;
use common\models\Article;
use yii2mod\behaviors\CarbonBehavior;

/**
 * This is the model class for table "articles_statistics".
 *
 * @property string $article_id
 * @property Carbon $created_at
 * @property Carbon $article_created_at
 * @property integer $clicked
 * @property integer $showed
 * @property double $CTR
 * @property integer $clicked_top
 * @property integer $showed_top
 * @property double $CTR_top
 * @property double $CTR_common
 * @property integer $hours_diff
 * @property integer $CTR_percent_to_decrease
 * @property double $CTR_modified
 * @property double $CTR_common_modified
 * @property double $CTR_top_modified
 * @property string $article_category_id
 * @property integer $top_position
 * @property Carbon $accelerated_at
 * @property integer $ratings_count
 * @property integer $comments_count
 * @property integer $clicks_common
 * @property integer $clicks_feed
 * @property integer $clicks_top
 * @property integer $clicks_similar_articles
 * @property integer $views_common
 * @property integer $views_feed
 * @property integer $views_top
 * @property integer $views_similar_articles
 * @property boolean $posted_to_telegram
 * @property integer|null $pushed_at
 *
 * @property-read Article $article
 */
class ArticlesStatistics extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'articles_statistics';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'article_created_at', 'accelerated_at']
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['article_id', 'created_at', 'article_created_at'], 'required'],
            [['clicked', 'showed', 'clicked_top', 'showed_top', 'hours_diff', 'CTR_percent_to_decrease', 'pushed_at'], 'integer'],
            [['CTR', 'CTR_top', 'CTR_modified', 'CTR_top_modified'], 'number'],
            [['article_id', 'article_category_id'], 'string', 'max' => 36],
            [['article_id'], 'unique'],
            ['posted_to_telegram', 'boolean']
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
            'article_created_at' => 'Article Created At',
            'clicked' => 'Clicked',
            'showed' => 'Showed',
            'CTR' => 'Ctr',
            'clicked_top' => 'Clicked Top',
            'showed_top' => 'Showed Top',
            'CTR_top' => 'Ctr Top',
            'hours_diff' => 'Hours Diff',
            'CTR_percent_to_decrease' => 'Ctr Percent To Decrease',
            'CTR_modified' => 'Ctr Modified',
            'CTR_top_modified' => 'Ctr Top Modified',
            'article_category_id' => 'Article Category ID',
        ];
    }

    public function getArticle()
    {
        return $this->hasOne(Article::class, [
            'id' => 'article_id'
        ]);
    }

    public static function find()
    {
        return new \common\queries\ArticlesStatistics(get_called_class());
    }
}
