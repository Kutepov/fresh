<?php namespace backend\models;

use Yii;

class AdProvider extends \common\models\AdProvider
{
    private const ATTRIBUTES = [
        'platform',
        'widget',
        'country',
        'provider',
        'metadata'
    ];
    private const DEFAULT_ROWS_FOR_COUNTRY = [
        [
            'platform' => 'android',
            'widget' => 'categories-2',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'android',
            'widget' => 'categories-20',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'android',
            'widget' => 'delivery-2',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'android',
            'widget' => 'delivery-20',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'android',
            'widget' => 'floating-block-categories',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'android',
            'widget' => 'floating-block-delivery',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],

        [
            'platform' => 'ios',
            'widget' => 'categories-2',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'ios',
            'widget' => 'categories-20',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'ios',
            'widget' => 'delivery-2',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'ios',
            'widget' => 'delivery-20',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{}',
        ],
        [
            'platform' => 'ios',
            'widget' => 'floating-block-categories',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{"position": 15, "showTimes": 5}',
        ],
        [
            'platform' => 'ios',
            'widget' => 'floating-block-delivery',
            'country' => '',
            'provider' => 'gad',
            'metadata' => '{"position": 15, "showTimes": 5}',
        ],
    ];

    public static function createDefaultsForCountry($country_code)
    {
        $defaults = self::DEFAULT_ROWS_FOR_COUNTRY;
        array_walk($defaults, function (&$item) use ($country_code) {
            $item['country'] = $country_code;
        });
        Yii::$app->db->createCommand()->batchInsert(self::tableName(), self::ATTRIBUTES, $defaults)->execute();
    }

}