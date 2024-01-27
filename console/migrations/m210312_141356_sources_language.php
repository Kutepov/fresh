<?php

use yii\db\Migration;

/**
 * Class m210312_141356_sources_language
 */
class m210312_141356_sources_language extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'language', $this->string(5)->after('country')->defaultValue(null));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210312_141356_sources_language cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210312_141356_sources_language cannot be reverted.\n";

        return false;
    }
    */
}
