<?php

use yii\db\Migration;

/**
 * Class m201127_230927_avg_news_freq
 */
class m201127_230927_avg_news_freq extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'avg_news_freq', $this->integer()->defaultValue(0));
        $this->addColumn('sources_urls', 'avg_news_freq', $this->integer()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201127_230927_avg_news_freq cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201127_230927_avg_news_freq cannot be reverted.\n";

        return false;
    }
    */
}
