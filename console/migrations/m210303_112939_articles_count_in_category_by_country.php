<?php

use yii\db\Migration;

/**
 * Class m210303_112939_articles_count_in_category_by_country
 */
class m210303_112939_articles_count_in_category_by_country extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addPrimaryKey('PRIMARY', 'categories_countries', ['country', 'category_id']);
        $this->addColumn('categories_countries', 'articles_exists', $this->boolean()->defaultValue(1));
        $this->createIndex('idx-category_with_articles', 'categories_countries', ['articles_exists']);
        $this->createIndex('idx-category_for_country_with_article', 'categories_countries', ['country', 'articles_exists']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('categories_countries', 'articles_exists');
        $this->dropPrimaryKey('PRIMARY', 'categories_countries');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210303_112939_articles_count_in_category_by_country cannot be reverted.\n";

        return false;
    }
    */
}
