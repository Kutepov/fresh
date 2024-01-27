<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "advertising".
 *
 * @property integer $id
 * @property string $provider
 * @property string $platform
 * @property string $country
 * @property integer $enabled
 */
class Advertising extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'advertising';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['enabled'], 'integer'],
            [['provider'], 'string', 'max' => 16],
            [['platform'], 'string', 'max' => 10],
            [['country'], 'string', 'max' => 2],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'platform' => 'Platform',
            'country' => 'Country',
            'enabled' => 'Enabled',
        ];
    }

    public static function getIsEnabledFor(string $provider, string $platform, string $country): bool
    {
        $config = self::find()->where([
            'provider' => $provider,
            'platform' => $platform,
            'country' => $country
        ])->one();

        if (!$config) {
            return false;
        }
        else {
            return (bool)$config->enabled;
        }
    }
}
