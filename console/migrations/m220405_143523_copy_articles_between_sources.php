<?php

use yii\db\Migration;

/**
 * Class m220405_143523_copy_articles_between_sources
 */
class m220405_143523_copy_articles_between_sources extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'copy_from_source_id', $this->string());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('sources', 'copy_from_source_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220405_143523_copy_articles_between_sources cannot be reverted.\n";

        return false;
    }
    */
}
