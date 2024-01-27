<?php

use yii\db\Migration;

/**
 * Class m210312_112451_countries_languages
 */
class m210312_112451_countries_languages extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('languages', [
            'id' => $this->primaryKey()->unsigned(),
            'code' => $this->string(5),
            'name' => $this->string(64)
        ]);

        $this->createTable('countries_languages', [
            'country_id' => $this->integer()->unsigned()->notNull(),
            'language_id' => $this->integer()->unsigned()->notNull(),
            'default' => $this->boolean()->defaultValue(0)
        ]);
        $this->addPrimaryKey('PRIMARY', 'countries_languages', ['country_id', 'language_id']);
        $this->addForeignKey('fk-countries_languages-language', 'countries_languages', 'language_id', 'languages', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-countries_languages-country', 'countries_languages', 'country_id', 'countries', 'id', 'CASCADE', 'CASCADE');
        $languages = [
            'ru' => 'Русский',
            'uk' => 'Українська',
            'en' => 'English',
            'de' => 'Deutsch',
            'pl' => 'Polski',
            'pt' => 'Português',
            'fr' => 'Français',
            'es' => 'Espagnol'
        ];

        foreach ($languages as $code => $name) {
            $this->insert('languages', [
                'code' => $code,
                'name' => $name
            ]);
        }

        $this->insert('countries_languages', [
            'country_id' => 9,
            'language_id' => 1
        ]);
        $this->insert('countries_languages', [
            'country_id' => 9,
            'language_id' => 2,
            'default' => 1
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('countries_languages');
        $this->dropTable('languages');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210312_112451_countries_languages cannot be reverted.\n";

        return false;
    }
    */
}
