<?php namespace common\queries;

use Carbon\Carbon;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Class ArticlesStatistics
 * @package common\queries
 *
 * @see \common\models\aggregate\ArticlesStatistics
 */
class ArticlesStatistics extends ActiveQuery
{
    public function byDate(?Carbon $date = null)
    {
        if (is_null($date)) {
            return $this;
        }
        else {
            return $this->andWhere([
                '=', '`articles_statistics`.`created_at`', $date->toDateTimeString()
            ]);
        }
    }

    public function newestFirst()
    {
        return $this->addOrderBy([
            'article_created_at' => SORT_DESC
        ]);
    }

    public function orderByTopPosition()
    {
        return $this->addOrderBy(
            new Expression('-top_position DESC')
        );
    }

    public function orderByTopCTR($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'CTR_top' => $order
        ]);
    }

    public function orderByModifiedTopCTR($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'CTR_top_modified' => $order
        ]);
    }

    public function orderByModifiedCTR($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'CTR_modified' => $order
        ]);
    }

    public function orderByCommonCTR($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'CTR_common_modified' => $order
        ]);
    }

    public function orderByCTR($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'CTR' => $order
        ]);
    }

    public function orderByTopClicks($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'clicked_top' => $order
        ]);
    }

    public function orderByAllClicks($order = SORT_DESC)
    {
        return $this->addOrderBy([
            new Expression('(clicked_top+clicked) ' . ($order === SORT_DESC ? 'DESC' : 'ASC'))
        ]);
    }

    public function orderByClicks($order = SORT_DESC)
    {
        return $this->addOrderBy([
            'clicked' => $order
        ]);
    }

    public function acceleratedFirst()
    {
        return $this->addOrderBy([
            'accelerated_at' => SORT_DESC
        ]);
    }

    public function withoutTop()
    {
        return $this->andWhere([
            'AND',
            ['IS', 'clicked_top', null],
            ['IS', 'CTR_top', null],
            ['IS', 'top_position', null]
        ]);
    }

    public function onlyTop($clicksConstraint = 10)
    {
        return $this->andWhere([
            'OR',
            ['IS NOT', 'top_position', null],
            ['>=', 'clicked_top', $clicksConstraint]
        ]);
    }

    public function mostTopFirst()
    {
        return $this
            ->orderByTopPosition()
            ->orderByCommonCTR()
            ->newestFirst();
    }
}