<?php

use yii\db\Migration;

/**
 * Class m230324_121835_sources_subscribers
 */
class m230324_121835_sources_subscribers extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('sources_urls_subscribers', [
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'source_url_id' => $this->integer()->null(),
            'app_id' => $this->integer()->null()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m230324_121835_sources_subscribers cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230324_121835_sources_subscribers cannot be reverted.\n";

        return false;
    }
    */
}
