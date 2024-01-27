<?php

use yii\db\Migration;

/**
 * Class m220117_101609_custom_css
 */
class m220117_101609_custom_css extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('sources', 'injectable_css', $this->text());
        $this->addColumn('sources', 'injectable_js', $this->text());
        $this->addColumn('sources', 'adblock_css_selectors', $this->text());

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220117_101609_custom_css cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220117_101609_custom_css cannot be reverted.\n";

        return false;
    }
    */
}
