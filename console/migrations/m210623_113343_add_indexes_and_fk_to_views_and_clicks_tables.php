<?php

use yii\db\Migration;

/**
 * Class m210623_113343_add_indexes_and_fk_to_views_and_clicks_tables
 */
class m210623_113343_add_indexes_and_fk_to_views_and_clicks_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $this->execute('SET foreign_key_checks = 0;');
//        $this->createIndex('indx-articles_views_widget', '{{%articles_views}}', 'widget');
//        $this->createIndex('indx-articles_clicks_widget', '{{%articles_clicks}}', 'widget');
//        $this->addForeignKey('fk-articles_views_widget_indx', '{{%articles_views}}', 'article_id', '{{%articles}}', 'id');
//        $this->addForeignKey('fk-articles_clicks_widget_indx', '{{%articles_clicks}}', 'article_id', '{{%articles}}', 'id');
//        $this->execute('SET foreign_key_checks = 1;');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropIndex('indx-articles_views_widget', '{{%articles_views}}');
//        $this->dropIndex('indx-articles_clicks_widget', '{{%articles_clicks}}');
//        $this->dropForeignKey('fk-articles_views_widget_indx', '{{%articles_views}}');
//        $this->dropForeignKey('fk-articles_clicks_widget_indx', '{{%articles_clicks}}');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210623_113343_add_indexes_and_fk_to_views_and_clicks_tables cannot be reverted.\n";

        return false;
    }
    */
}
