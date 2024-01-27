<?php

use yii\db\Migration;

/**
 * Class m210315_121243_sources_groups
 */
class m210315_121243_sources_groups extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'group_id', $this->char(36)->after('id')->defaultValue(null));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210315_121243_sources_groups cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210315_121243_sources_groups cannot be reverted.\n";

        return false;
    }
    */
}
