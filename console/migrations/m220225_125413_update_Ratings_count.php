<?php

use yii\db\Migration;

/**
 * Class m220225_125413_update_Ratings_count
 */
class m220225_125413_update_Ratings_count extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $articles = \common\models\Article::find()
            ->andWhere([
                '>=', 'articles.created_at', '2022-02-24 00:00:00'
            ])
            ->innerJoinWith('ratings')
            ->all();
        foreach ($articles as $article) {
            if ($ratingsCount = $article->getRatings()->count()) {
                $article->updateAttributes([
                    'rating' => array_sum(\yii\helpers\ArrayHelper::getColumn($article->ratings, 'rating')),
                    'ratings_count' => $ratingsCount
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220225_125413_update_Ratings_count cannot be reverted.\n";

        return false;
    }
    */
}
