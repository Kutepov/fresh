<?php

use yii\db\Migration;

/**
 * Class m211108_160317_user_name
 */
class m211108_160317_user_name extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropColumn('users', 'firstname');
        $this->dropColumn('users', 'lastname');
        $this->addColumn('users', 'name', $this->string()->after('email'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m211108_160317_user_name cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211108_160317_user_name cannot be reverted.\n";

        return false;
    }
    */
}
