<?php

use yii\db\Migration;

/**
 * Handles dropping columns from table `{{%users}}`.
 */
class m220121_101940_drop_username_column_from_users_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropColumn('{{%users}}', 'username');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->addColumn('{{%users}}', 'username', $this->string(255));
    }
}
