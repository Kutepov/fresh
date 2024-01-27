<?php namespace common\queries;

use common\components\multilingual\db\MultilingualQuery;
use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\Category
 */
class Folder extends MultilingualQuery
{
    public function withoutDefaultFolder(): self
    {
        return $this->andWhere([
            '=', 'default', 0
        ]);
    }

    public function forPlatform(string $platform): self
    {
        return $this;
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
                    'folders_countries.country' => $countryCode,
                    'folders_countries.articles_exists' => $withArticlesOnly
                ]);
            }], false)
            ->groupBy('folders.id');
    }

    public function orderByPriority(): self
    {
        return $this->addOrderBy([
            (defined('JP_CONDITION') ? 'jp_priority' : 'priority') => SORT_ASC
        ]);
    }
}