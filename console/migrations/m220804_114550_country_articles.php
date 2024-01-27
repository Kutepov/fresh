<?php

use yii\db\Migration;

/**
 * Class m220804_114550_country_articles
 */
class m220804_114550_country_articles extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('countries', 'articles_preview_type', $this->string(16));
        $this->addColumn('countries', 'articles_preview_type_switcher', $this->boolean()->defaultValue(0));

        $this->update('countries', [
            'articles_preview_type' => 'small',
            'articles_preview_type_switcher' => 0
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220804_114550_country_articles cannot be reverted.\n";

        return false;
    }
    */
}
