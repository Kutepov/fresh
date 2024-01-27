<?php

use yii\db\Migration;

/**
 * Class m231110_101702_jp_categories_sort
 */
class m231110_101702_jp_categories_sort extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('categories', 'jp_priority', $this->integer()->after('priority'));
        $this->addColumn('folders', 'jp_priority', $this->integer()->after('priority'));

        $categories = \common\models\Category::find()->all();
        foreach ($categories as $category) {
            $category->updateAttributes([
                'jp_priority' => $category->priority
            ]);

            $category->folder->updateAttributes([
                'jp_priority' => $category->folder->priority
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('categories', 'jp_priority');
        $this->dropColumn('folders', 'jp_priority');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231110_101702_jp_categories_sort cannot be reverted.\n";

        return false;
    }
    */
}
