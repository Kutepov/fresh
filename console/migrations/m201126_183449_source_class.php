<?php

use yii\db\Migration;

/**
 * Class m201126_183449_source_class
 */
class m201126_183449_source_class extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'class', $this->string(320)->after('id')->defaultValue(null));
        $this->addColumn('sources_urls', 'class', $this->string(320)->after('id')->defaultValue(null));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201126_183449_source_class cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201126_183449_source_class cannot be reverted.\n";

        return false;
    }
    */
}
