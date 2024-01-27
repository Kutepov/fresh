<?php

use yii\db\Migration;

/**
 * Class m231019_103732_folders_fk
 */
class m231019_103732_folders_fk extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addForeignKey('fk-folder-category', 'folders', 'category_id', 'categories', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231019_103732_folders_fk cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231019_103732_folders_fk cannot be reverted.\n";

        return false;
    }
    */
}
