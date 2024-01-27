<?php

use yii\db\Migration;

/**
 * Class m220316_130720_similar_articles_banners
 */
class m220316_130720_similar_articles_banners extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $newBanners = [
            'ios' => [
                4 => 'ca-app-pub-7635126548465920/9828776020',
                8 => 'ca-app-pub-7635126548465920/6763628986'
            ],
            'android' => [
                4 => 'ca-app-pub-7635126548465920/1950323993',
                8 => 'ca-app-pub-7635126548465920/3478990091'
            ]
        ];

        $countries = \common\models\Country::find()->all();
        foreach ($countries as $country) {
            foreach ($newBanners as $platform => $banners) {
                foreach ($banners as $pos => $id) {
                    $this->insert('ad_banners', [
                        'enabled' => 1,
                        'type' => \common\models\AdBanner::TYPE_SIMILAR_ARTICLES,
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
        echo "m220316_130720_similar_articles_banners cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220316_130720_similar_articles_banners cannot be reverted.\n";

        return false;
    }
    */
}
