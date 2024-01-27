<?php

use yii\db\Migration;

/**
 * Class m220602_130301_user_shadow_ban
 */
class m220602_130301_user_shadow_ban extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('users', 'shadow_ban', $this->boolean()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220602_130301_user_shadow_ban cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220602_130301_user_shadow_ban cannot be reverted.\n";

        return false;
    }
    */
}
