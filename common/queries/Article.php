<?php namespace common\queries;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use common\models\Source;
use yii\db\ActiveQuery;
use yii;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\Article
 */
class Article extends ActiveQuery
{
    /**
     * @param string|string[] $ids
     * @return $this
     */
    public function byCategory($ids): self
    {
        return $this->andFilterWhere([
            'articles.category_id' => (array)$ids
        ]);
    }

    public function byIds($ids): self
    {
        return $this->andFilterWhere([
            'articles.id' => (array)$ids
        ]);
    }

    /**
     * @param string|string[]|false $ids
     * @param string|null $forCountry
     * @param string|null $articlesLanguage
     * @return $this
     */
    public function bySource($ids, ?string $forCountry = null, ?string $articlesLanguage = null): self
    {
        $sources = Source::find()
            ->select('sources.id')
            ->enabled()
            ->andFilterWhere([
                'sources.id' => $ids === false ? -1 : $ids
            ])
            ->byCountry($forCountry)
            ->byLanguage($articlesLanguage);

        if (!$ids) {
            $sources->defaultOnly();
        }
        $sources = $sources->column();

        return $this->andWhere([
            'articles.source_id' => $sources
        ]);
    }

    public function defaultOnly()
    {
        $sources = Source::find()
            ->select('sources.id')
            ->enabled()
            ->defaultOnly()
            ->column();

        return $this->andWhere([
            'articles.source_id' => $sources
        ]);
    }

    public function bySourceUrl($ids)
    {
        if (count((array)$ids)) {
            return $this->andFilterWhere(['source_url_id' => (array)$ids]);
        }

        return $this;
    }

    //TODO: artticles language condition needed
    public function byCountry($countryCode): self
    {
        $sources = \common\models\SourceUrl::find()
            ->select('sources_urls.id')
            ->enabled()
            ->byCountry($countryCode)
            ->defaultOnly()
            ->column();

        return $this->andWhere([
            'articles.source_url_id' => $sources
        ]);
    }

    /**
     * Выборка по ID "такой же" новости
     * @param string|null $id
     * @return $this
     */
    public function byParentArticleId(?string $id = null): self
    {
        return $this->andWhere([
            'same_article_id' => $id
        ]);
    }

    public function byParentArticlesIds(array $ids): self
    {
        return $this->andWhere([
            'same_article_id' => $ids
        ]);
    }

    public function skipBanned(bool $skip = true): self
    {
        if (!$skip) {
            return $this;
        }

        return $this->andWhere([
            'articles.banned_words' => false
        ]);
    }

    public function olderThan(?Carbon $date): self
    {
        return $this->andFilterWhere([
            'AND',
            ['<=', 'articles.created_at', $date ? $date->setTimezone('UTC')->toDateTimeString() : null],
            ['>=', 'articles.created_at', $date ? $date->subMonths(2)->setTimezone('UTC')->toDateString() : null],
        ]);
    }

    public function newerThan(Carbon $date): self
    {
        return $this->andFilterWhere([
            '>=',
            'articles.created_at',
            $date->setTimezone('UTC')->toDateTimeString()
        ]);
    }

    public function createdAt(?CarbonInterface $from, ?CarbonInterface $to = null): self
    {
        if (is_null($from)) {
            return $this;
        }

        if (is_null($to)) {
            $to = clone $from;
            $to = $to->endOfDay();
        }

        return $this->andFilterWhere([
            'AND',
            ['>=', '`articles`.`created_at`', $from->toDateTimeString()],
            ['<=', '`articles`.`created_at`', $to->toDateTimeString()]
        ]);
    }

    public function newestFirst(): self
    {
        return $this->addOrderBy([
            'articles.created_at' => SORT_DESC
        ]);
    }

    public function skipSameArticles($skip = true): self
    {
        if ($skip) {
            return $this->andWhere([
                'IS', 'same_article_id', null
            ]);
        }

        return $this;
    }

    public function one($db = null, $skipSameArticles = true)
    {
        $this->skipSameArticles($skipSameArticles);
        return parent::one($db);
    }

    public function count($q = '*', $db = null, $skipSameArticles = true)
    {
        $this->skipSameArticles($skipSameArticles);
        return parent::count($q, $db);
    }

    public function column($db = null, $skipSameArticles = true)
    {
        $this->skipSameArticles($skipSameArticles);
        return parent::column($db);
    }

    public function all($db = null, $skipSameArticles = true)
    {
        $this->skipSameArticles($skipSameArticles)
        ->with('comments');

        if (!(Yii::$app instanceof yii\console\Application)) {
            /** Текущая оценка новости авторизованным юзером */
            $this->with('currentUserRating');
            $this->with('source');
        }

        return parent::all($db);
    }
}