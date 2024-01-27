<?php

use yii\db\Migration;

/**
 * Class m220620_145940_search_queries
 */
class m220620_145940_search_queries extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('search_queries_logs', [
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'country' => $this->string(2)->notNull(),
            'language' => $this->string(2)->notNull(),
            'query' => $this->string(320)->notNull()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('idx-search_query_log', 'search_queries_logs', ['country', 'language', 'created_at']);

        $this->createTable('search_queries', [
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'query' => $this->string()->notNull(),
            'country' => $this->string(2)->notNull(),
            'language' => $this->string(2)->notNull(),
            'amount' => $this->integer()->defaultValue(0)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('idx-search_query', 'search_queries', ['country', 'language']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220620_145940_search_queries cannot be reverted.\n";

        return false;
    }
    */
}
