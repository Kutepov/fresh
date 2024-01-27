<?php

use yii\db\Migration;

/**
 * Class m220128_085721_alter_column_for_users_table
 */
class m220128_085721_alter_column_for_users_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%users}}', 'created_at_new', $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')));
        $this->addColumn('{{%users}}', 'updated_at_new', $this->timestamp());

        $this->update('{{%users}}', ['created_at_new' => new \yii\db\Expression('FROM_UNIXTIME(created_at)'), 'updated_at_new' => new \yii\db\Expression('FROM_UNIXTIME(updated_at)')]);

        $this->dropColumn('{{%users}}', 'created_at');
        $this->dropColumn('{{%users}}', 'updated_at');

        $this->renameColumn('{{%users}}', 'created_at_new', 'created_at');
        $this->renameColumn('{{%users}}', 'updated_at_new', 'updated_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->addColumn('{{%users}}', 'created_at_new', $this->integer());
        $this->addColumn('{{%users}}', 'updated_at_new', $this->integer());

        $this->update('{{%users}}', ['created_at_new' => new \yii\db\Expression('UNIX_TIMESTAMP(created_at)'), 'updated_at_new' => new \yii\db\Expression('UNIX_TIMESTAMP(updated_at)')]);

        $this->dropColumn('{{%users}}', 'created_at');
        $this->dropColumn('{{%users}}', 'updated_at');

        $this->renameColumn('{{%users}}', 'created_at_new', 'created_at');
        $this->renameColumn('{{%users}}', 'updated_at_new', 'updated_at');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220128_085721_alter_column_for_users_table cannot be reverted.\n";

        return false;
    }
    */
}
