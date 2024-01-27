<?php

use yii\db\Migration;

/**
 * Class m221115_124810_historical_stat_users
 */
class m221115_124810_historical_stat_users extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('historical_statistics_users', [
            'date' => $this->date(),
            'platform' => $this->string(8),
            'country' => $this->string(2),
            'users_amount' => $this->integer()->defaultValue(0)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addPrimaryKey('pk-historical_users_statistics', 'historical_statistics_users', ['date', 'country', 'platform']);
        $this->createIndex('idx-historical_statistics_users-country', 'historical_statistics_users', 'country');
        $this->createIndex('idx-historical_statistics_users-platform', 'historical_statistics_users', 'platform');
        $this->createIndex('idx-historical_statistics_users-date', 'historical_statistics_users', 'date');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('historical_statistics_users');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m221115_124810_historical_stat_users cannot be reverted.\n";

        return false;
    }
    */
}
