<?php

use yii\db\Migration;

/**
 * Class m240112_121534_category_icon
 */
class m240112_121534_category_icon extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('categories', 'icon', $this->string()->after('image'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m240112_121534_category_icon cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240112_121534_category_icon cannot be reverted.\n";

        return false;
    }
    */
}
