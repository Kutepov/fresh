<?php

use yii\db\Migration;

/**
 * Class m210512_162207_comments_index
 */
class m210512_162207_comments_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createIndex('idx-parent_comments', 'comments', ['parent_comment_id', 'article_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210512_162207_comments_index cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210512_162207_comments_index cannot be reverted.\n";

        return false;
    }
    */
}
