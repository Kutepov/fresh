<?php

use yii\db\Migration;

/**
 * Class m231013_094532_use_publication_date_in_sources
 */
class m231013_094532_use_publication_date_in_sources extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'use_publication_date', $this->boolean()->defaultValue(0));
        $this->update('sources', [
            'use_publication_date' => 1
        ], [
            'default' => 0
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231013_094532_use_publication_date_in_sources cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231013_094532_use_publication_date_in_sources cannot be reverted.\n";

        return false;
    }
    */
}
