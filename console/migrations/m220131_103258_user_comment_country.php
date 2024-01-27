<?php

use yii\db\Migration;

/**
 * Class m220131_103258_user_comment_country
 */
class m220131_103258_user_comment_country extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->update('users', [
            'country_code' => new \yii\db\Expression('geo')
        ]);

        $this->addColumn('comments', 'country', $this->string(2)->after('article_id'));
        $this->createIndex('idx-comment-country', 'comments', 'country');
        $this->createIndex('idx-user-country', 'users', 'country_code');

        $comments = \common\models\Comment::find()->all();

        foreach ($comments as $comment) {
            $comment->updateAttributes([
                'country' => $comment->user->country_code
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('comments', 'country');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220131_103258_user_comment_country cannot be reverted.\n";

        return false;
    }
    */
}
