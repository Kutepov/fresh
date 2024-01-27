<?php

use yii\db\Migration;

/**
 * Class m210512_135629_comments
 */
class m210512_135629_comments extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('comments', [
            'id' => $this->primaryKey(),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->timestamp(),
            'enabled' => $this->boolean(),
            'article_id' => $this->char(36)->notNull(),
            'user_id' => $this->integer()->notNull(),
            'parent_comment_id' => $this->integer()->null(),
            'rating' => 'MEDIUMINT DEFAULT 0',
            'answers_count' => 'MEDIUMINT UNSIGNED DEFAULT 0',
            'text' => $this->text()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey('fk-article-comments', 'comments', 'article_id', 'articles', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('comments_rating', [
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->timestamp(),
            'comment_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'rating' => $this->tinyInteger(2)
        ]);

        $this->addForeignKey('fk-comment-rating', 'comments_rating', 'comment_id', 'comments', 'id', 'CASCADE', 'CASCADE');
        $this->addPrimaryKey('pk-comment-rating', 'comments_rating', ['comment_id', 'user_id']);
        $this->createIndex('idx-comment-rating', 'comments_rating', 'comment_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('comments_rating');
        $this->dropTable('comments');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210512_135629_comments cannot be reverted.\n";

        return false;
    }
    */
}
