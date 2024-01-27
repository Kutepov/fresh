<?php

use yii\db\Migration;

/**
 * Class m220420_120217_enable_push_notifications
 */
class m220420_120217_enable_push_notifications extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('sources', 'push_notifications', $this->boolean()->defaultValue(0));

        $this->update('sources', [
            'push_notifications' => 1,
        ], [
            'country' => 'UA',
            'language' => 'uk',
            'telegram' => 1
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220420_120217_enable_push_notifications cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220420_120217_enable_push_notifications cannot be reverted.\n";

        return false;
    }
    */
}
