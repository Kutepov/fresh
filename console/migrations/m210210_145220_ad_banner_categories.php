<?php

use yii\db\Migration;

/**
 * Class m210210_145220_ad_banner_categories
 */
class m210210_145220_ad_banner_categories extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('ad_banners', 'categories', $this->json());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210210_145220_ad_banner_categories cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210210_145220_ad_banner_categories cannot be reverted.\n";

        return false;
    }
    */
}
