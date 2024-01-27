<?php

use yii\db\Migration;

/**
 * Class m210303_101422_id_categories
 */
class m210303_101422_id_categories extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $categories = \common\models\Category::find()->select('id')->column();

        foreach ($categories as $category) {
            $this->insert('categories_countries', ['category_id' => $category, 'country' => 'ID']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210303_101422_id_categories cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210303_101422_id_categories cannot be reverted.\n";

        return false;
    }
    */
}
