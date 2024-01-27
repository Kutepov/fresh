<?php

use yii\db\Migration;

/**
 * Class m230726_104004_source_url_alias
 */
class m230726_104004_source_url_alias extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources_urls', 'alias', $this->string()->after('url'));

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m230726_104004_source_url_alias cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230726_104004_source_url_alias cannot be reverted.\n";

        return false;
    }
    */
}
