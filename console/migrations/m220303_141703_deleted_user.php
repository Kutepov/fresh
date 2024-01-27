<?php

use yii\db\Migration;

/**
 * Class m220303_141703_deleted_user
 */
class m220303_141703_deleted_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->insert('users', [
            'id' => 1,
            'email' => 'deleted293423i9k3f@myfresh.app',
            'name' => 'No Name',
            'password_hash' => Yii::$app->security->generatePasswordHash(
                Yii::$app->security->generateRandomString()
            ),
            'password_exists' => 1,
            'status' => 1
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
        echo "m220303_141703_deleted_user cannot be reverted.\n";

        return false;
    }
    */
}
