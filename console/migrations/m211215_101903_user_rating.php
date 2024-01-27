<?php

use yii\db\Migration;

/**
 * Class m211215_101903_user_rating
 */
class m211215_101903_user_rating extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('users', 'rating', $this->integer()->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m211215_101903_user_rating cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211215_101903_user_rating cannot be reverted.\n";

        return false;
    }
    */
}
