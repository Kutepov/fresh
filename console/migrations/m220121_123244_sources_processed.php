<?php

use yii\db\Migration;

/**
 * Class m220121_123244_sources_processed
 */
class m220121_123244_sources_processed extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'processed', $this->boolean()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220121_123244_sources_processed cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220121_123244_sources_processed cannot be reverted.\n";

        return false;
    }
    */
}
