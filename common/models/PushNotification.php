<?php namespace common\models;

use common\behaviors\ActiveRecordUUIDBehavior;
use common\behaviors\CarbonBehavior;
use Yii;

/**
 * This is the model class for table "push_notifications".
 *
 * @property string $id
 * @property string $created_at
 * @property string $updated_at
 * @property string $article_id
 * @property integer $app_id
 * @property boolean $sent
 * @property boolean $viewed
 * @property boolean $clicked
 * @property string $country
 * @property string $articles_language
 * @property string $platform
 * @property bool $top
 */
class PushNotification extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'push_notifications';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ],
            'uuid' => [
                'class' => ActiveRecordUUIDBehavior::class,
            ]
        ];
    }

    public function createUUID(): void
    {
        $this->getBehavior('uuid')->beforeCreate();
    }

    public function getArticle()
    {
        return $this->hasOne(Article::class, [
            'id' => 'article_id'
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'article_id', 'app_id'], 'required'],
            [['created_at', 'updated_at'], 'safe'],
            [['app_id', 'viewed', 'clicked', 'sent'], 'integer'],
            [['id', 'article_id'], 'string', 'max' => 36],
            [['country'], 'string', 'max' => 2],
            [['articles_language'], 'string', 'max' => 5],
            [['platform'], 'string', 'max' => 12],
            [['id'], 'unique'],
            ['top', 'boolean']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'article_id' => 'Article ID',
            'app_id' => 'App ID',
            'viewed' => 'Viewed',
            'clicked' => 'Clicked',
            'country' => 'Country',
            'articles_language' => 'Articles Language',
            'platform' => 'Platform',
        ];
    }
}
