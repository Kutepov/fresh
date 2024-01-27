<?php

use yii\db\Migration;

/**
 * Class m211203_080914_user_oauth_password_flag
 */
class m211203_080914_user_oauth_password_flag extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('users', 'password_exists', $this->boolean()->after('password_hash')->defaultValue(0));
        $this->delete('users', [
            '<>', 'email', 'bydnik.a@gmail.com'
        ]);
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
        echo "m211203_080914_user_oauth_password_flag cannot be reverted.\n";

        return false;
    }
    */
}
