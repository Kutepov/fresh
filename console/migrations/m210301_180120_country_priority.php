<?php

use yii\db\Migration;

/**
 * Class m210301_180120_country_priority
 */
class m210301_180120_country_priority extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('countries', 'priority', $this->integer()->defaultValue(0));
        $this->update('countries', ['priority' => 10], ['id' => [9, 8, 2, 5]]);
        $this->update('countries', ['priority' => 9], ['id' => [1, 3, 7]]);
        $this->update('countries', ['priority' => 5], ['id' => [10, 6]]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210301_180120_country_priority cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210301_180120_country_priority cannot be reverted.\n";

        return false;
    }
    */
}
