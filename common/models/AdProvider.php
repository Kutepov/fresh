<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "ad_providers".
 *
 * @property string $platform
 * @property string $widget
 * @property string $country
 * @property string $provider
 * @property array $metadata
 */
class AdProvider extends \yii\db\ActiveRecord
{
    public const RECREATIV = 'recreativ';
    public const GOOGLE = 'gad';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ad_providers';
    }

    public function fields()
    {
        return [
            'provider',
            'metadata'
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['metadata'], 'safe'],
            [['platform'], 'string', 'max' => 12],
            [['widget'], 'string', 'max' => 100],
            [['country'], 'string', 'max' => 2],
            [['provider'], 'string', 'max' => 20],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'platform' => 'Platform',
            'widget' => 'Widget',
            'country' => 'Country',
            'provider' => 'Provider',
            'metadata' => 'Metadata',
        ];
    }

    public static function getProviderFor(string $platform, string $country, string $widget): ?self
    {
        return self::findOne([
            'platform' => $platform,
            'country' => $country,
            'widget' => $widget
        ]);
    }
}
