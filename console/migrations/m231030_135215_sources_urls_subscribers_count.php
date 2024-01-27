<?php

use yii\db\Migration;

/**
 * Class m231030_135215_sources_urls_subscribers_count
 */
class m231030_135215_sources_urls_subscribers_count extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'subscribers_count', $this->integer()->defaultValue(0));
        $this->addColumn('sources_urls', 'subscribers_count', $this->integer()->defaultValue(0));
        $this->createIndex('idx-source_url-subscribers_count', 'sources_urls', 'subscribers_count');
        $this->createIndex('idx-source-subscribers_count', 'sources', 'subscribers_count');
        $this->createTable('sources_subscribers', [
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'source_id' => $this->char(36)->notNull(),
            'app_id' => $this->integer()->unsigned()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->alterColumn('sources_urls_subscribers', 'source_url_id', $this->integer()->unsigned()->notNull());
        $this->addForeignKey('fk-sources_subscribers-source_id', 'sources_subscribers', 'source_id', 'sources', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-sources_urls_subscribers-source_id', 'sources_urls_subscribers', 'source_url_id', 'sources_urls', 'id', 'CASCADE', 'CASCADE');
        $this->addPrimaryKey('pk-sources_subscribers',  'sources_subscribers', ['source_id', 'app_id']);
        $this->addPrimaryKey('pk-sources_urls_subscribers',  'sources_urls_subscribers', ['source_url_id', 'app_id']);
        $this->createIndex('idx-sources_subscribers-source_id', 'sources_subscribers', 'source_id');
        $this->createIndex('idx-sources_urls_subscribers-source_id', 'sources_urls_subscribers', 'source_url_id');
        $this->createIndex('idx-sources_subscribers-app_id', 'sources_subscribers', 'app_id');
        $this->createIndex('idx-sources_urls_subscribers-app_id', 'sources_urls_subscribers', 'app_id');
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
        echo "m231030_135215_sources_urls_subscribers_count cannot be reverted.\n";

        return false;
    }
    */
}
