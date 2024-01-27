<?php

use yii\db\Migration;

/**
 * Class m230112_114021_ad_config
 */
class m230112_114021_ad_config extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $newBanners = [
            'ios' => [
                2 => 'ca-app-pub-7635126548465920/9480066909',
                6 => 'ca-app-pub-7635126548465920/2734872767'
            ],
            'android' => [
                2 => 'ca-app-pub-7635126548465920/9204734734',
                6 => 'ca-app-pub-7635126548465920/4227740229'
            ]
        ];

        $countires = \common\models\Country::find()->where(['NOT IN', 'code', ['RU', 'UA', 'BY', 'KZ']])->all();
        foreach ($countires as $country) {
            foreach ($newBanners as $platform => $banners) {
                foreach ($banners as $pos => $id) {
                    $this->insert('ad_banners', [
                        'enabled' => 1,
                        'type' => 'article-body',
                        'platform' => $platform,
                        'country' => $country->code,
                        'provider' => 'googlead',
                        'position' => $pos,
                        'banner_id' => $id
                    ]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m230112_114021_ad_config cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230112_114021_ad_config cannot be reverted.\n";

        return false;
    }
    */
}
