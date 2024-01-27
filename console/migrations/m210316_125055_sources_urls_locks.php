<?php

use yii\db\Migration;

/**
 * Class m210316_125055_sources_urls_locks
 */
class m210316_125055_sources_urls_locks extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources_urls', 'lock_id', $this->integer()->defaultValue(null)->after('locked_at'));

        $this->createTable('sources_urls_locks', [
            'id' => $this->primaryKey(),
            'source_id' => $this->char(36)->notNull(),
            'source_url_id' => $this->integer()->notNull(),
            'locked_at' => $this->timestamp(),
            'unlocked_at' => $this->timestamp(),
            'lock_time' => $this->time(),
            'unlocked_by_cron' => $this->boolean()->defaultValue(0),
            'errors' => $this->boolean()->defaultValue(0),
            'articles_found' => $this->integer()->defaultValue(0)
        ]);

        $this->createIndex('idx-locks-source_url', 'sources_urls_locks', ['source_url_id']);
        $this->createIndex('idx-locks-source', 'sources_urls_locks', ['source_id']);
        $this->createIndex('idx-locks-cron', 'sources_urls_locks', ['unlocked_by_cron']);
        $this->createIndex('idx-locks-locked_at', 'sources_urls_locks', ['locked_at']);
        $this->createIndex('idx-unique-lock', 'sources_urls_locks', ['locked_at', 'source_url_id'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('sources_urls_locks');
         $this->dropColumn('sources_urls', 'lock_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210316_125055_sources_urls_locks cannot be reverted.\n";

        return false;
    }
    */
}
