<?php

use yii\db\Migration;

/**
 * Class m220303_100937_fullscreen_banner
 */
class m220303_100937_fullscreen_banner extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $newBanners = [
            'ios' => 'ca-app-pub-7635126548465920/7988265103',
            'android' => 'ca-app-pub-7635126548465920/9398244220'
        ];

        $countries = \common\models\Country::find()->all();
        foreach ($countries as $country) {
            foreach ($newBanners as $platform => $bannerId) {
                $this->insert('ad_banners', [
                    'enabled' => 1,
                    'type' => 'fullscreen',
                    'platform' => $platform,
                    'country' => $country->code,
                    'provider' => 'googlead',
                    'banner_id' => $bannerId
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220303_100937_fullscreen_banner cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220303_100937_fullscreen_banner cannot be reverted.\n";

        return false;
    }
    */
}
