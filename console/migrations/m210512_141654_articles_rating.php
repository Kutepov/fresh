<?php

use yii\db\Migration;

/**
 * Class m210512_141654_articles_rating
 */
class m210512_141654_articles_rating extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $this->addColumn('articles', 'rating', 'MEDIUMINT');

        $this->createTable('articles_rating', [
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->timestamp(),
            'article_id' => $this->char(36)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'rating' => $this->tinyInteger(2)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey('fk-article-rating', 'articles_rating', 'article_id', 'articles', 'id', 'CASCADE', 'CASCADE');
        $this->addPrimaryKey('pk-article-rating', 'articles_rating', ['article_id', 'user_id']);
        $this->createIndex('idx-article-rating', 'articles_rating', 'article_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
//        $this->dropColumn('articles', 'rating');
        $this->dropTable('articles_rating');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210512_141654_articles_rating cannot be reverted.\n";

        return false;
    }
    */
}
