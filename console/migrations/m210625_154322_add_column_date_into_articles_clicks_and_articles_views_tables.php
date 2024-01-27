<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Class m210625_154322_add_column_date_into_articles_clicks_and_articles_views_tables
 */
class m210625_154322_add_column_date_into_articles_clicks_and_articles_views_tables extends Migration
{
    private $tableClicks = '{{%articles_clicks}}';
    private $tableViews = '{{%articles_views}}';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $this->addColumn($this->tableClicks, 'date', $this->date());
//        $this->addColumn($this->tableViews, 'date', $this->date());
//        $this->createIndex('articles_clicks_date-indx', $this->tableClicks, 'date');
//        $this->createIndex('articles_views_date-indx', $this->tableViews, 'date');
//
//        $this->execute('update articles_clicks set date = date(created_at)');
//        $this->execute('update articles_views set date = date(created_at)');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropIndex('articles_clicks_date-indx', $this->tableClicks);
//        $this->dropIndex('articles_views_date-indx', $this->tableViews);
//        $this->dropColumn($this->tableClicks, 'date');
//        $this->dropColumn($this->tableViews, 'date');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210625_154322_add_column_date_into_articles_clicks_and_articles_views_tables cannot be reverted.\n";

        return false;
    }
    */
}
