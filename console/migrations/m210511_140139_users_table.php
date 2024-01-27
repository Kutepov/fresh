<?php

use yii\db\Migration;

/**
 * Class m210511_140139_users_table
 */
class m210511_140139_users_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('users', 'useragent', $this->string(640));
        $this->addColumn('users', 'geo', $this->string(2));
        $this->addColumn('users', 'country_code', $this->string(2));
        $this->addColumn('users', 'language_code', $this->string(2));
        $this->addColumn('users', 'ip', $this->string(39));
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
        echo "m210511_140139_users_table cannot be reverted.\n";

        return false;
    }
    */
}
