<?php

use yii\db\Migration;

/**
 * Class m220218_120646_users_source
 */
class m220218_120646_users_source extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('users', 'platform', $this->string());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220218_120646_users_source cannot be reverted.\n";

        return false;
    }
    */
}
