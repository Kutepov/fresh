<?php

use yii\db\Migration;

/**
 * Class m230620_123155_comments_toggle_for_sources
 */
class m230620_123155_comments_toggle_for_sources extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'enable_comments', $this->boolean()->defaultValue(1));
        $this->addColumn('sources_urls', 'enable_comments', $this->boolean()->defaultValue(1));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m230620_123155_comments_toggle_for_sources cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230620_123155_comments_toggle_for_sources cannot be reverted.\n";

        return false;
    }
    */
}
