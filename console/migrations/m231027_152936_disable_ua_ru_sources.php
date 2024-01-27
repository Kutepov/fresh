<?php

use yii\db\Migration;

/**
 * Class m231027_152936_disable_ua_ru_sources
 */
class m231027_152936_disable_ua_ru_sources extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->update('sources', [
            'enabled' => 0
        ], [
            'country' => 'ua',
            'language' => 'ru'
        ]);

        $uaRuSources = \common\models\Source::find()->select('id')
            ->where([
                'country' => 'ua',
                'language' => 'ru'
            ])
            ->column();

        $this->update('sources_urls', ['enabled' => 0], [
            'id' => $uaRuSources
        ]);

        $this->update('sources', [
            'language' => null
        ], [
            'country' => 'ua'
        ]);

        $this->update('countries', [
            'locale' => 'uk_UA'
        ], [
            'code' => 'UA'
        ]);

        $this->delete('countries_languages', [
            'country_id' => 9
        ]);

        $this->update('languages', [
            'locale' => null,
            'short_name' => null
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
        echo "m231027_152936_disable_ua_ru_sources cannot be reverted.\n";

        return false;
    }
    */
}
