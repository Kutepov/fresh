<?php namespace backend\models;

use common\models\pivot\CommentRating;
use yii\db\ActiveQuery;

/**
 * Class Comment
 * @package backend\models
 *
 * @property \backend\models\User $user
 * @property-read \common\models\pivot\CommentRating[] $ratingPluses
 * @property-read \common\models\pivot\CommentRating[] $ratingMinuses
 */
class Comment extends \common\models\Comment
{
    public function attributeLabels()
    {
        return [
            'created_at' => 'Создан',
            'updated_at' => 'Обновлен',
            'enabled' => 'Доступен',
            'article_text' => 'Статья',
            'user_id' => 'Пользователь',
            'parent_comment_id' => 'Ответ на комментарий',
            'rating' => 'Рейтинг',
            'answers_count' => 'Ответов',
            'text' => 'Текст',
            'username' => 'Пользователь',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, [
            'id' => 'user_id'
        ]);
    }

    public function getRatingPluses()
    {
        return $this
            ->getRatings()
            ->andWhere(['rating' => CommentRating::PLUS]);
    }

    public function getRatingMinuses()
    {
        return $this
            ->getRatings()
            ->andWhere(['rating' => CommentRating::MINUS]);
    }

    public function getArticle(): \common\queries\Article
    {
        return $this->hasOne(Article::class, [
            'id' => 'article_id'
        ]);
    }

    public function afterDelete()
    {
        $this->article->updateCounters([
            'comments_count' => -1
        ]);
        parent::afterDelete(); // TODO: Change the autogenerated stub
    }
}