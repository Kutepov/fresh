<?php namespace common\queries;

use common\contracts\RateableEntityQuery;
use yii\db\ActiveQuery;

/**
 * Class Comment
 * @package common\queries
 *
 * @see \common\models\pivot\ArticleRating
 */
class ArticleRating extends ActiveQuery implements RateableEntityQuery
{
    public function byArticleId($articleId): self
    {
        return $this->andWhere([
            'article_id' => $articleId
        ]);
    }

    public function byAppId($appId): self
    {
        return $this->andWhere([
            'app_id' => $appId
        ]);
    }
}