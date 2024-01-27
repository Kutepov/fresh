<?php

use yii\db\Migration;

/**
 * Class m221115_100631_aggregate_statistics
 */
class m221115_100631_aggregate_statistics extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createTable('historical_statistics', [
            'date' => $this->date(),
            'category_id' => $this->char(36),
            'country' => $this->string(2),
            'articles_language' => $this->string(2),
            'platform' => $this->string(8),

//            'type' => $this->string(16),
//            'widget' => $this->string(32),
//            'preview_type' => $this->string(16),

            'clicks' => $this->integer(),
            'views' => $this->integer()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addPrimaryKey('pk-historical_statistics', 'historical_statistics', ['date', 'country', 'articles_language', 'platform', 'category_id', /*'type', 'widget', 'preview_type'*/]);
        $this->createIndex('idx-historical_statistics-category_id', 'historical_statistics', 'category_id');
        $this->createIndex('idx-historical_statistics-country', 'historical_statistics', 'country');
        $this->createIndex('idx-historical_statistics-articles_language', 'historical_statistics', 'articles_language');
        $this->createIndex('idx-historical_statistics-platform', 'historical_statistics', 'platform');
        $this->createIndex('idx-historical_statistics-date', 'historical_statistics', 'date');

//        $this->createIndex('idx-historical_statistics-type', 'historical_statistics', 'type');
//        $this->createIndex('idx-historical_statistics-widget', 'historical_statistics', 'widget');
//        $this->createIndex('idx-historical_statistics-preview_type', 'historical_statistics', 'preview_type');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('historical_statistics');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m221115_100631_aggregate_statistics cannot be reverted.\n";

        return false;
    }
    */
}
