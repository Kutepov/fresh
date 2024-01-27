<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "articles_shares".
 *
 * @property integer $id
 * @property string $article_id
 * @property string $created_at
 * @property string $country
 * @property integer $app_id
 * @property string $platform
 * @property string $date
 */
class ArticleShare extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'articles_shares';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['article_id'], 'required'],
            [['created_at', 'date'], 'safe'],
            [['app_id'], 'integer'],
            [['article_id'], 'string', 'max' => 36],
            [['country'], 'string', 'max' => 2],
            [['platform'], 'string', 'max' => 8],
            [['app_id', 'article_id'], 'unique', 'targetAttribute' => ['app_id', 'article_id']],
            [['article_id'], 'exist', 'skipOnError' => true, 'targetClass' => Article::class, 'targetAttribute' => ['article_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'article_id' => 'Article ID',
            'created_at' => 'Created At',
            'country' => 'Country',
            'app_id' => 'App ID',
            'platform' => 'Platform',
            'date' => 'Date',
        ];
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            if ($article = Article::find()->where(['id' => $this->article_id])->one(null, false)) {
                $article->updateCounters([
                    'shares_count' => 1
                ]);
            }
        }

        parent::afterSave($insert, $changedAttributes);
    }
}
