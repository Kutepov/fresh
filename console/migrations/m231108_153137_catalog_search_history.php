<?php

use yii\db\Migration;

/**
 * Class m231108_153137_catalog_search_history
 */
class m231108_153137_catalog_search_history extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createTable('catalog_search_history', [
            'id' => $this->primaryKey(),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'app_id' => $this->integer(),
            'query' => $this->string(),
            'section' => $this->string(),
            'type' => $this->string()
        ]);

        $this->createIndex('idx-catalog_search-app_id', 'catalog_search_history', 'app_id');
        $this->createIndex('idx-catalog_search-query', 'catalog_search_history', 'query');
        $this->createIndex('idx-catalog_search-type', 'catalog_search_history', 'type');
        $this->createIndex('idx-catalog_search-section', 'catalog_search_history', 'section');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('catalog_search_history');
    }
}
