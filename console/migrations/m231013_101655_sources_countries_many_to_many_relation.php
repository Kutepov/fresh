<?php

use yii\db\Migration;

/**
 * Class m231013_101655_sources_countries_many_to_many_relation
 */
class m231013_101655_sources_countries_many_to_many_relation extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createTable('sources_countries', [
            'source_id' => $this->char(36)->notNull(),
            'country_id' => $this->integer()->unsigned()->notNull()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey('fk-sources_countries-source', 'sources_countries', 'source_id', 'sources', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-sources_countries-country', 'sources_countries', 'country_id', 'countries', 'id', 'CASCADE', 'CASCADE');

        foreach (\common\models\Source::find()->all() as $source) {
            if ($source->country) {
                $this->insert('sources_countries', [
                    'source_id' => $source->id,
                    'country_id' => $source->countryModel->id
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('sources_countries');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m231013_101655_sources_countries_many_to_many_relation cannot be reverted.\n";

        return false;
    }
    */
}
