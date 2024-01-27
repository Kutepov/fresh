<?php

use yii\db\Migration;

/**
 * Class m210318_111209_scrapers_exceptions_log
 */
class m210318_111209_scrapers_exceptions_log extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('sources_exceptions', [
            'id' => $this->primaryKey(),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'source_id' => $this->char(36)->notNull(),
            'source_url_id' => $this->integer()->notNull()->unsigned(),
            'url' => $this->string(640),
            'message' => $this->string(640),
            'code' => $this->string(16),
            'data' => $this->text(),
            'type' => $this->string(32),
            'count' => $this->integer()->defaultValue(0)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addForeignKey('fk-sources_exceptions-source', 'sources_exceptions', 'source_id', 'sources', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-sources_exceptions-source_url', 'sources_exceptions', 'source_url_id', 'sources_urls', 'id', 'CASCADE', 'CASCADE');

        $this->createIndex('idx-exception-source_id', 'sources_exceptions', 'source_id');
        $this->createIndex('idx-exception-date', 'sources_exceptions', 'created_at');
        $this->createIndex('idx-exception-source_url_id', 'sources_exceptions', 'source_url_id');
        $this->createIndex('idx-unique-exception', 'sources_exceptions', ['url', 'source_id'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('sources_exceptions');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210318_111209_scrapers_exceptions_log cannot be reverted.\n";

        return false;
    }
    */
}
