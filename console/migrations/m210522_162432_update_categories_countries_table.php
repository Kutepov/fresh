<?php

use yii\db\Migration;

/**
 * Class m210522_162432_update_categories_countries_table
 */
class m210522_162432_update_categories_countries_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute('SET foreign_key_checks = 0;');
        $this->addForeignKey('fk-categories_countries_country', '{{%categories_countries}}', 'country', '{{%countries}}', 'code', 'cascade', 'cascade');
        $this->createIndex('indx-languages_code', '{{%languages}}', 'code');
        $this->createIndex('indx-categories_lang_language', '{{%categories_lang}}', 'language');
        $this->addForeignKey('fk-categories_lang_language', '{{%categories_lang}}', 'language', '{{%languages}}', 'code', 'cascade', 'cascade');
        $this->execute('SET foreign_key_checks = 1;');
        $this->alterColumn('{{%categories_countries}}', 'articles_exists', $this->tinyInteger(1)->defaultValue(0));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-categories_countries_country', '{{%categories_countries}}');
        $this->dropForeignKey('fk-categories_lang_language', '{{%categories_lang}}');
        $this->dropIndex('indx-languages_code', '{{%languages}}');
        $this->dropIndex('indx-categories_lang_language', '{{%categories_lang}}');
        $this->alterColumn('{{%categories_countries}}', 'articles_exists', $this->tinyInteger(1)->defaultValue(1));
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210522_162432_update_categories_countries_table cannot be reverted.\n";

        return false;
    }
    */
}
