<?php

use yii\db\Migration;

/**
 * Class m220331_095540_top_calculated_at
 */
class m220331_095540_top_calculated_at extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('countries', 'top_calculated_at',  $this->timestamp()->defaultValue(null));
        $this->addColumn('countries', 'top_locked',  $this->boolean()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220331_095540_top_calculated_at cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220331_095540_top_calculated_at cannot be reverted.\n";

        return false;
    }
    */
}
