<?php namespace common\queries;

use common\contracts\RateableEntityQuery;
use yii\db\ActiveQuery;

/**
 * Class Comment
 * @package common\queries
 *
 * @see \common\models\pivot\CommentRating
 */
class CommentRating extends ActiveQuery implements RateableEntityQuery
{
    public function byCommentId($commentId): self
    {
        return $this->andWhere([
            'comment_id' => $commentId
        ]);
    }

    public function byAppId($appId): self
    {
        return $this->andFilterWhere([
            'app_id' => $appId
        ]);
    }
}