<?php namespace common\models\statistics;

use Carbon\Carbon;
use common\components\validators\TimestampValidator;
use common\models\Country;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use yii\redis\ActiveRecord;
use yii2mod\behaviors\CarbonBehavior;

/**
 * Class ArticleClickTemporary
 * @package common\models\statistics
 *
 * @property $id
 * @property $article_id
 * @property Carbon $created_at
 * @property $country
 * @property $app_id
 * @property $platform
 * @property $widget
 * @property string $preview_type
 */
class ArticleViewTemporary extends ActiveRecord
{
    public function attributes()
    {
        return [
            'id',
            'article_id',
            'created_at',
            'country',
            'app_id',
            'platform',
            'widget',
            'preview_type'
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
                'value' => Carbon::now()->format('Y-m-d H:i:s')
            ],
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at']
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['created_at', TimestampValidator::class],
            [['article_id', 'app_id', 'platform', 'country', 'widget'], 'required'],
            [['app_id'], 'integer'],
            [['article_id'], 'string', 'max' => 36],
            [['country'], 'string', 'max' => 2],
            [['platform'], 'string', 'max' => 8],
            [['widget'], 'string', 'max' => 40],
            ['preview_type', 'in', 'range' => Country::PREVIEW_TYPES]
        ];
    }
}