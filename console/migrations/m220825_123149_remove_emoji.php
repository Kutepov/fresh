<?php

use yii\db\Migration;

/**
 * Class m220825_123149_remove_emoji
 */
class m220825_123149_remove_emoji extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $articles = \common\models\Article::find()->where(['source_id' => 'a61b106c-23c3-11ed-b59b-0242ac12000e'])->all();
        foreach ($articles as $article) {
            $article->updateAttributes([
                'title' => trim(removeEmoji($article->title))
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220825_123149_remove_emoji cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220825_123149_remove_emoji cannot be reverted.\n";

        return false;
    }
    */
}
