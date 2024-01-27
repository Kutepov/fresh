<?php

use yii\db\Migration;

/**
 * Class m230324_122045_source_rss
 */
class m230324_122045_source_rss extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'rss', $this->boolean()->after('type')->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m230324_122045_source_rss cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230324_122045_source_rss cannot be reverted.\n";

        return false;
    }
    */
}
