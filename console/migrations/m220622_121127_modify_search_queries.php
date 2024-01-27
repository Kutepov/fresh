<?php

use yii\db\Migration;

/**
 * Class m220622_121127_modify_search_queries
 */
class m220622_121127_modify_search_queries extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropColumn('search_queries_logs', 'language');
        $this->addColumn('search_queries_logs', 'locale', $this->string(5)->after('query'));

        $this->dropColumn('search_queries', 'language');
        $this->addColumn('search_queries', 'locale', $this->string(5)->after('query'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220622_121127_modify_search_queries cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220622_121127_modify_search_queries cannot be reverted.\n";

        return false;
    }
    */
}
