<?php namespace common\models;

use Carbon\Carbon;
use common\components\validators\TimestampValidator;
use yii2mod\behaviors\CarbonBehavior;

/**
 * This is the model class for table "apps_subscriptions".
 *
 * @property integer $id
 * @property integer $enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property integer $expire_at
 * @property integer $app_id
 * @property string $token
 * @property bool $isValid
 */
class AppSubscription extends \yii\db\ActiveRecord
{
    public function getIsValid(): bool
    {
        return $this->expire_at > time();
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'apps_subscriptions';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at', 'updated_at'], TimestampValidator::class],
            [['enabled', 'expire_at', 'app_id'], 'integer'],
            [['token'], 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'enabled' => 'Enabled',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'expire_at' => 'Expire At',
            'app_id' => 'App ID',
            'token' => 'Token',
        ];
    }
}
