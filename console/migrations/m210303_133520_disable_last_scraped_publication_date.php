<?php

use yii\db\Migration;

/**
 * Class m210303_133520_disable_last_scraped_publication_date
 */
class m210303_133520_disable_last_scraped_publication_date extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources_urls', 'last_scraped_at_disabled', $this->boolean()->defaultValue(0)->after('last_scraped_at'));
        $this->update('sources_urls', [
            'last_scraped_at_disabled' => 1,
            'last_scraped_at' => null
        ], [
            'source_id' => 'c0fc13b1-d834-4738-8ce2-d429bfcafc4a'
        ]);
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
        echo "m210303_133520_disable_last_scraped_publication_date cannot be reverted.\n";

        return false;
    }
    */
}
