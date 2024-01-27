<?php

use yii\db\Migration;

/**
 * Class m220314_084111_unathorized_likes
 */
class m220314_084111_unathorized_likes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->truncateTable('articles_rating');
        $this->truncateTable('comments_rating');
        $this->addColumn('articles_rating', 'app_id', $this->integer()->after('user_id')->defaultValue(null));
        $this->addColumn('comments_rating', 'app_id', $this->integer()->after('user_id')->defaultValue(null));
        $this->dropPrimaryKey('PRIMARY', 'articles_rating');
        $this->dropPrimaryKey('PRIMARY', 'comments_rating');
        $this->dropColumn('articles_rating', 'user_id');
        $this->dropColumn('comments_rating', 'user_id');
        $this->addPrimaryKey('PRIMARY', 'articles_rating', ['article_id', 'app_id']);
        $this->addPrimaryKey('PRIMARY', 'comments_rating', ['comment_id', 'app_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('articles_rating', 'app_id');
        $this->dropColumn('comments_rating', 'app_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220314_084111_unathorized_likes cannot be reverted.\n";

        return false;
    }
    */
}
