<?php namespace common\queries;

use common\models\User;
use yii\db\ActiveQuery;
use yii;

/**
 * Class Comment
 * @package common\queries
 *
 * @see \common\models\Comment
 */
class Comment extends ActiveQuery
{
    public function byArticleId(?string $articleId): self
    {
        return $this->andFilterWhere([
            'article_id' => $articleId
        ]);
    }

    public function enabled(): self
    {
        return $this->andWhere([
            'comments.enabled' => 1
        ]);
    }

    public function enabledOrForUser(User $user): self
    {
        return $this->andWhere([
            'OR',
            ['=', 'comments.enabled', 1],
            [
                'AND',
                ['=', 'comments.enabled', 0],
                ['=', 'comments.user_id', $user->id]
            ]
        ]);
    }

    public function byUserId($userId): self
    {
        return $this->andWhere([
            'comments.user_id' => $userId
        ]);
    }

    public function byParentCommentId($parentCommentId): self
    {
        return $this->andWhere([
            'parent_comment_id' => $parentCommentId
        ]);
    }

    public function byRootCommentId($rootCommentId): self
    {
        return $this->andWhere([
            'comments.root_comment_id' => $rootCommentId
        ]);
    }

    public function orderByRating($order = SORT_DESC): self
    {
        return $this->orderBy([
            'comments.rating' => $order,
            'comments.created_at' => SORT_ASC
        ]);
    }

    public function orderByDate($order = SORT_ASC): self
    {
        return $this->orderBy([
            'created_at' => $order
        ]);
    }

    public function notDeleted(): self
    {
        return $this->andWhere(['comments.deleted' => 0]);
    }

    public function rootNotDeleted(): self
    {
        return $this->andWhere([
            'OR',
            [
                'AND',
                ['=', 'deleted', 0]
            ],
            [
                'AND',
                ['=', 'deleted', 1],
                ['IS', 'root_comment_id', null],
                ['>', 'answers_count', 0]
            ]
        ]);
    }

    public function all($db = null)
    {
        if (!(Yii::$app instanceof yii\console\Application) && !Yii::$app->user->isGuest) {
            /** Текущая оценка новости авторизованным юзером */
            $this->with('currentUserRating');
        }

        return parent::all($db);
    }
}