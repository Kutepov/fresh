<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%ad_banners}}`.
 */
class m220222_101135_add_banner_id_column_to_ad_banners_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('ad_banners', 'banner_id', $this->string(320));

        $newBanners = [
            'ios' => [
                2 => 'ca-app-pub-7635126548465920/9480066909',
                4 => 'ca-app-pub-7635126548465920/2734872767'
            ],
            'android' => [
                2 => 'ca-app-pub-7635126548465920/9204734734',
                4 => 'ca-app-pub-7635126548465920/4227740229'
            ]
        ];
        foreach (['RU', 'UA', 'BY', 'KZ'] as $country) {
            foreach ($newBanners as $platform => $banners) {
                foreach ($banners as $pos => $id) {
                    $this->insert('ad_banners', [
                        'enabled' => 1,
                        'type' => 'article-body',
                        'platform' => $platform,
                        'country' => $country,
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
        $this->dropColumn('ad_banners', 'banner_id');
    }
}
