<?php namespace common\queries;

use common\components\multilingual\db\MultilingualQuery;
use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\Category
 */
class Category extends MultilingualQuery
{
    public function withoutDefaultCategory(): self
    {
        return $this->andWhere([
            '<>', 'categories.name', \common\models\Category::DEFAULT_CATEGORY_NAME
        ]);
    }

    public function forPlatform(string $platform): self
    {
        return $this;
    }

    /**
     * @param string|string[] $slug
     * @return $this
     */
    public function bySlugName($slug): self
    {
        return $this->andWhere([
            'categories.name' => $slug
        ]);
    }

    public function forLanguage(string $languageCode): self
    {
        return $this->localized($languageCode);
    }

    public function forCountry(string $countryCode, bool $withArticlesOnly = true): self
    {
        return $this
            ->innerJoinWith(['countries' => function (ActiveQuery $query) use ($countryCode, $withArticlesOnly) {
                $query->andWhere([
                    'categories_countries.country' => $countryCode,
                    'categories_countries.articles_exists' => $withArticlesOnly
                ]);
            }], false)
            ->groupBy('categories.id');
    }

    public function orderByPriority(): self
    {
        return $this->addOrderBy([
            (defined('JP_CONDITION') ? 'jp_priority' : 'priority') => SORT_ASC
        ]);
    }

    public function withLatestArticles(?int $limit = null)
    {
        return $this->with([
            'latestArticles' => function (Article $query) use ($limit) {
                $query->limit($limit);
            }
        ]);
    }
}