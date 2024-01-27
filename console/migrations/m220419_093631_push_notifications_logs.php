<?php

use yii\db\Migration;

/**
 * Class m220419_093631_push_notifications_logs
 */
class m220419_093631_push_notifications_logs extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('push_notifications', [
            'id' =>  $this->char(36)->notNull(),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->timestamp()->defaultValue(null),
            'article_id' => $this->char(36)->notNull(),
            'app_id' => $this->integer()->notNull(),
            'sent' => $this->boolean()->defaultValue(1),
            'viewed' => $this->boolean()->defaultValue(0),
            'clicked' => $this->boolean()->defaultValue(0),
            'country' => $this->string(2),
            'articles_language' => $this->string(5),
            'platform' => $this->string(12)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addPrimaryKey('pk-push_notification', 'push_notifications', 'id');
        $this->createIndex('idx-push_notification_app', 'push_notifications', ['app_id', 'article_id']);
        $this->createIndex('idx-push_notification_country', 'push_notifications', ['country', 'articles_language']);
        $this->createIndex('idx-push_notification_platform', 'push_notifications', 'platform');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('push_notifications');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220419_093631_push_notifications_logs cannot be reverted.\n";

        return false;
    }
    */
}
