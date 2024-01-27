<?php

use yii\db\Migration;

/**
 * Class m210312_131351_language_short_name
 */
class m210312_131351_language_short_name extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('languages', 'short_name', $this->string(16)->defaultValue(null));
        $this->update('languages', ['short_name' => 'Ру'], ['id' => 1]);
        $this->update('languages', ['short_name' => 'Укр'], ['id' => 2]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210312_131351_language_short_name cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210312_131351_language_short_name cannot be reverted.\n";

        return false;
    }
    */
}
