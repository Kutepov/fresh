<?php

use yii\db\Migration;

/**
 * Class m221116_104931_push_notifications_historical
 */
class m221116_104931_push_notifications_historical extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('historical_push_notifications', [
            'article_id' => $this->char(36)->notNull(),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'date' => $this->date(),
            'country' => $this->string(2),
            'articles_language' => $this->string(5),
            'platform' => $this->string(16),
            'sent_amount' => $this->integer()->defaultValue(0),
            'viewed_amount' => $this->integer()->defaultValue(0),
            'clicked_amount' => $this->integer()->defaultValue(0)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addPrimaryKey('pk-historical_push_notifications', 'historical_push_notifications',['article_id', 'platform']);
        $this->createIndex('idx-historical_push_article_id', 'historical_push_notifications', 'article_id');
        $this->createIndex('idx-historical_push_date', 'historical_push_notifications', 'date');
        $this->createIndex('idx-historical_push_country', 'historical_push_notifications', 'country');
        $this->createIndex('idx-historical_push_platform', 'historical_push_notifications', 'platform');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
       $this->dropTable('historical_push_notifications');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m221116_104931_push_notifications_historical cannot be reverted.\n";

        return false;
    }
    */
}
