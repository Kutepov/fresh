<?php

use yii\db\Migration;

/**
 * Class m230324_100802_source_url_name
 */
class m230324_100802_source_url_name extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources_urls', 'name', $this->string()->after('id'));
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropColumn('sources_urls', 'name');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230324_100802_source_url_name cannot be reverted.\n";

        return false;
    }
    */
}
