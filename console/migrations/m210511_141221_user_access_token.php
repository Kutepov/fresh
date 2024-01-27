<?php

use yii\db\Migration;

/**
 * Class m210511_141221_user_access_token
 */
class m210511_141221_user_access_token extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('users', 'access_token', $this->string(32)->after('username'));
        $this->createIndex('idx-user-access_token', 'users', 'access_token');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210511_141221_user_access_token cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210511_141221_user_access_token cannot be reverted.\n";

        return false;
    }
    */
}
