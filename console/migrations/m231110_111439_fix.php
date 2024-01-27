<?php

use yii\db\Migration;

/**
 * Class m231110_111439_fix
 */
class m231110_111439_fix extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $categories = \common\models\Category::find()->all();
        foreach ($categories as $category) {
            $category->folder->updateAttributes([
                'jp_priority' => $category->jp_priority
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231110_111439_fix cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231110_111439_fix cannot be reverted.\n";

        return false;
    }
    */
}
