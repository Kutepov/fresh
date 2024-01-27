<?php namespace common\models\statistics;

use Carbon\Carbon;
use common\components\validators\TimestampValidator;
use common\components\validators\UUIDValidator;
use common\models\Country;
use yii\validators\DateValidator;
use yii2mod\behaviors\CarbonBehavior;

/**
 * This is the model class for table "articles_clicks".
 *
 * @property integer $id
 * @property string $article_id
 * @property string $category_id
 * @property Carbon $created_at
 * @property string $country
 * @property integer $app_id
 * @property string $platform
 * @property string $widget
 * @property string $date
 * @property string $preview_type
 */
class ArticleClick extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'articles_clicks';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'date']
            ]
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->date = (new \DateTime($this->created_at))->format('Y-m-d');
        }
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['created_at', TimestampValidator::class],
            ['date', DateValidator::class],
            [['app_id'], 'required'],
            [['app_id'], 'integer'],
            [['article_id'], 'string', 'max' => 36],
            [['country'], 'string', 'max' => 2],
            [['platform'], 'string', 'max' => 8],
            [['widget'], 'string', 'max' => 40],
            ['category_id', UUIDValidator::class],
            ['preview_type', 'in', 'range' => Country::PREVIEW_TYPES]
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
            'country' => 'Country',
            'app_id' => 'App ID',
            'platform' => 'Platform',
            'widget' => 'Widget',
        ];
    }
}
