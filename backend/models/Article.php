<?php namespace backend\models;

use common\models\pivot\ArticleRating;

class Article extends \common\models\Article
{
    public static function find()
    {
        return (new \backend\queries\Article(get_called_class()));
    }

    public function getRatingPluses()
    {
        return $this
            ->getRatings()
            ->andWhere(['rating' => ArticleRating::PLUS]);
    }

    public function getRatingMinuses()
    {
        return $this
            ->getRatings()
            ->andWhere(['rating' => ArticleRating::MINUS]);
    }
}