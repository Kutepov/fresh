<?php

use yii\db\Migration;

/**
 * Class m210312_124450_app_articles_language
 */
class m210312_124450_app_articles_language extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('apps', 'articles_language', $this->string(5)->after('language')->defaultValue(null));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('apps', 'articles_language');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210312_124450_app_articles_language cannot be reverted.\n";

        return false;
    }
    */
}
