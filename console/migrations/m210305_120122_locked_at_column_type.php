<?php

use yii\db\Migration;

/**
 * Class m210305_120122_locked_at_column_type
 */
class m210305_120122_locked_at_column_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('sources_urls', 'locked_at', $this->timestamp());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210305_120122_locked_at_column_type cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210305_120122_locked_at_column_type cannot be reverted.\n";

        return false;
    }
    */
}
