<?php

use yii\db\Migration;

/**
 * Class m210513_155008_upadte_ad_banners_ad_providers_tables
 */
class m210513_155008_upadte_ad_banners_ad_providers_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute('SET foreign_key_checks = 0;');
        $this->createIndex('indx-ad_banners_country', '{{%ad_banners}}', 'country');
        $this->createIndex('indx-ad_providers_country', '{{%ad_providers}}', 'country');
        $this->createIndex('indx-countries_code', '{{%countries}}', 'code');
        $this->addForeignKey('fk-ad_banners_country', '{{%ad_banners}}', 'country', '{{%countries}}', 'code', 'cascade', 'cascade');
        $this->addForeignKey('fk-ad_providers_country', '{{%ad_providers}}', 'country', '{{%countries}}', 'code', 'cascade', 'cascade');
        $this->execute('SET foreign_key_checks = 1;');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-ad_banners_country', '{{%ad_banners}}');
        $this->dropForeignKey('fk-ad_providers_country', '{{%ad_providers}}');
        $this->dropIndex('indx-ad_banners_country', '{{%ad_banners}}');
        $this->dropIndex('indx-ad_providers_country', '{{%ad_providers}}');
        $this->dropIndex('indx-countries_code', '{{%countries}}');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210513_155008_upadte_ad_banners_ad_providers_tables cannot be reverted.\n";

        return false;
    }
    */
}
