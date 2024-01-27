<?php

use yii\db\Migration;

/**
 * Class m220622_144006_search_query_index
 */
class m220622_144006_search_query_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createIndex('idx_search_query', 'search_queries', ['country', 'locale']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220622_144006_search_query_index cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220622_144006_search_query_index cannot be reverted.\n";

        return false;
    }
    */
}
