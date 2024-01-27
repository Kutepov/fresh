<?php

use yii\db\Migration;

/**
 * Class m220131_105759_rating_country
 */
class m220131_105759_rating_country extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('articles_rating', 'country', $this->string(2)->after('article_id'));
        $this->createIndex('idx-article-rating-country', 'articles_rating', 'country');
        $this->addColumn('comments_rating', 'country', $this->string(2)->after('comment_id'));
        $this->createIndex('idx-comment-rating-country', 'comments_rating', 'country');

        $articlesRatings = \common\models\pivot\ArticleRating::find()->all();
        foreach ($articlesRatings as $rating) {
            $rating->updateAttributes([
                'country' => $rating->article->source->country
            ]);
        }

        $commentsRatings = \common\models\pivot\CommentRating::find()->all();
        foreach ($commentsRatings as $rating) {
            $rating->updateAttributes([
                'country' => $rating->comment->article->source->country
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220131_105759_rating_country cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220131_105759_rating_country cannot be reverted.\n";

        return false;
    }
    */
}
