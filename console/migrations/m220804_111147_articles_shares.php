<?php

use yii\db\Migration;

/**
 * Class m220804_111147_articles_shares
 */
class m220804_111147_articles_shares extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createTable('articles_shares', [
            'id' => $this->primaryKey(),
            'article_id' => $this->char(36)->notNull(),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'country' => $this->char(2),
            'app_id' => $this->integer(),
            'platform' => $this->string(8),
            'date' => $this->date()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey('fk_share-article', 'articles_shares', 'article_id', 'articles', 'id', 'CASCADE', 'CASCADE');
        $this->createIndex('idx-unique_share', 'articles_shares',['app_id', 'article_id'], true);
        $this->createIndex('idx-article-share', 'articles_shares',['article_id'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('articles_shares');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220804_111147_articles_shares cannot be reverted.\n";

        return false;
    }
    */
}
