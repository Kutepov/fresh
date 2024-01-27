<?php

use yii\db\Migration;

/**
 * Class m210531_121544_set_default_value_priority_on_table_categories
 */
class m210531_121544_set_default_value_priority_on_table_categories extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('{{%categories}}', 'priority', $this->integer()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->alterColumn('{{%categories}}', 'priority', $this->integer());
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210531_121544_set_default_value_priority_on_table_categories cannot be reverted.\n";

        return false;
    }
    */
}
