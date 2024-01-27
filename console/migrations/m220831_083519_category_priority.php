<?php

use yii\db\Migration;

/**
 * Class m220831_083519_category_priority
 */
class m220831_083519_category_priority extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $categories = \common\models\Category::find()->all();
        foreach ($categories as $k => $category) {
            $category->updateAttributes([
                'priority' => $k + 1
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220831_083519_category_priority cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220831_083519_category_priority cannot be reverted.\n";

        return false;
    }
    */
}
