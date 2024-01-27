<?php

use yii\db\Migration;

/**
 * Class m231031_132920_sources_urls_countries
 */
class m231031_132920_sources_urls_countries extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('sources_urls_countries', [
            'source_url_id' => $this->integer()->unsigned()->null(),
            'country_id' => $this->integer()->unsigned()->null()
        ]);

        $this->addForeignKey('fk-source_url_countries-country', 'sources_urls_countries', 'source_url_id', 'sources_urls', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-source_url_countries-source_url', 'sources_urls_countries', 'country_id', 'countries', 'id', 'CASCADE', 'CASCADE');

        $sources = \common\models\Source::find()->all();

        foreach ($sources as $source) {
            foreach ($source->countries as $country) {
                foreach ($source->urls as $url) {
                    $this->insert('sources_urls_countries', [
                        'source_url_id' => $url->id,
                        'country_id' => $country->id
                    ]);
                }
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m231031_132920_sources_urls_countries cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231031_132920_sources_urls_countries cannot be reverted.\n";

        return false;
    }
    */
}
