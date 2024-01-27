<?php

use yii\db\Migration;

/**
 * Class m231010_071815_source_external_image_url
 */
class m231010_071815_source_external_image_url extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'external_image_url', $this->string(640)->after('image'));

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231010_071815_source_external_image_url cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231010_071815_source_external_image_url cannot be reverted.\n";

        return false;
    }
    */
}
