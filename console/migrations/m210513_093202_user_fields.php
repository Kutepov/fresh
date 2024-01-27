<?php

use yii\db\Migration;

/**
 * Class m210513_093202_user_fields
 */
class m210513_093202_user_fields extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('users', 'firstname', $this->string(64));
        $this->addColumn('users', 'lastname', $this->string(64));
        $this->addColumn('users', 'photo', $this->string(320)->null());
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210513_093202_user_fields cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210513_093202_user_fields cannot be reverted.\n";

        return false;
    }
    */
}
