<?php

use yii\db\Migration;

/**
 * Class m230323_112311_default_sources
 */
class m230323_112311_default_sources extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources_urls', 'default', $this->boolean()->defaultValue(1)->after('id'));
        $this->addColumn('sources', 'default', $this->boolean()->defaultValue(1)->after('id'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('sources_urls', 'default');
        $this->dropColumn('sources', 'default');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230323_112311_default_sources cannot be reverted.\n";

        return false;
    }
    */
}
