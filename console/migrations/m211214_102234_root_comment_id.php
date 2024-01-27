<?php

use yii\db\Migration;

/**
 * Class m211214_102234_root_comment_id
 */
class m211214_102234_root_comment_id extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('comments', 'root_comment_id', $this->integer()->after('user_id'));
        $this->createIndex('idx-all-answers', 'comments', ['article_id', 'root_comment_id']);
        $this->update('comments', [
            'root_comment_id' => new \yii\db\Expression('parent_comment_id'),
            'parent_comment_id' => null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('comments', 'root_comment_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211214_102234_root_comment_id cannot be reverted.\n";

        return false;
    }
    */
}
