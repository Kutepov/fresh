<?php

use yii\db\Migration;

/**
 * Class m220126_125909_hunspell_locales
 */
class m220126_125909_hunspell_locales extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('countries', 'locale', $this->string(5)->defaultValue(null));
        $this->addColumn('languages', 'locale', $this->string(5)->defaultValue(null));

        $countries = [
            'AU' => 'en_AU',
            'BR' => 'pt_BR',
            'CA' => 'en_CA',
            'DE' => 'de_DE',
            'ES' => 'es_ES',
            'FR' => 'fr',
            'ID' => 'id_ID',
            'IT' => 'it_IT',
            'NG' => 'en_ZA',
            'PL' => 'pl',
            'ZA' => 'en_ZA',
            'GB' => 'en_GB',
            'US' => 'en_US',
            'VN' => 'vi',
            'BY' => 'ru',
            'KZ' => 'ru',
            'RU' => 'ru'
        ];

        $languages = [
            'ru' => 'ru',
            'uk' => 'uk',
        ];

        foreach ($countries as $countryCode => $locale) {
            $this->update('countries', [
                'locale' => $locale
            ], [
                'code' => $countryCode
            ]);
        }

        foreach ($languages as $languageCode => $locale) {
            $this->update('languages', [
                'locale' => $locale
            ], [
                'code' => $languageCode
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220126_125909_hunspell_locales cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220126_125909_hunspell_locales cannot be reverted.\n";

        return false;
    }
    */
}
