<?php

use yii\db\Migration;

/**
 * Class m231020_122625_fix_folders_sort
 */
class m231020_122625_fix_folders_sort extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $categories = \common\models\Category::find()->all();
        foreach ($categories as $category) {
            if ($category->folder) {
                $category->folder->updateAttributes(['priority' => $category->priority]);
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231020_122625_fix_folders_sort cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231020_122625_fix_folders_sort cannot be reverted.\n";

        return false;
    }
    */
}
