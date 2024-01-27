<?php namespace common\models;

use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "{{%proxies}}".
 *
 * @property integer $id
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $address
 * @property string $country
 * @property string $account
 */
class Proxy extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'proxies';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['address', 'trim'],
            [['created_at', 'updated_at'], 'integer'],
            [['address'], 'string', 'max' => 255],
            [['address'], 'unique'],
            ['country', 'string', 'max' => 2],
            ['account', 'string', 'max' => 32]
        ];
    }
}
