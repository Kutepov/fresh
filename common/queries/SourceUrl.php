<?php namespace common\queries;

use common\services\HashtagsService;
use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\SourceUrl
 */
class SourceUrl extends ActiveQuery
{
    public function enabled(bool $enabled = true): self
    {
        return $this
            ->innerJoinWith(['source' => static function (Source $query) use ($enabled) {
                $query->enabled($enabled);
            }], false)
            ->andWhere([
                'sources_urls.enabled' => $enabled
            ])
            ->andWhere([
                'IS NOT', 'sources_urls.category_id', null
            ]);
    }

    public function byCountry(?string $countryCode, $strict = true): self
    {
        if (is_null($countryCode)) {
            return $this;
        }

        if ($strict) {
            return $this->innerJoinWith('countries')
                ->andWhere(['countries.code' => $countryCode]);
        }

        return $this->joinWith('countries')
            ->andWhere([
                'OR',
                ['=', 'countries.code', $countryCode],
                ['IS', 'countries.code', null]
            ]);
    }

    public function excludeCountries(array $countriesCodes): self
    {
        return $this
            ->joinWith('countries', false)
            ->andWhere([
                    'OR',
                    ['NOT IN', 'countries.code', $countriesCodes],
                    ['IS', 'countries.code', null]
                ]
            );
    }

    public function byLanguage(?string $languageCode, $strict = true): self
    {
        return $this
            ->innerJoinWith(['source' => static function (Source $query) use ($languageCode, $strict) {
                $query->byLanguage($languageCode, $strict);
            }], false);
    }

    public function notLocked(): self
    {
        return $this->andWhere([
            'IS', 'sources_urls.locked_at', null
        ]);
    }

    public function locked(): self
    {
        return $this->andWhere([
            'IS NOT', 'sources_urls.locked_at', null
        ]);
    }

    public function oldestFirst(): self
    {
        return $this->addOrderBy([
            'sources_urls.last_scraped_at' => SORT_DESC
        ]);
    }

    public function mostPopularFirst(): self
    {
        return $this->addOrderBy([
            'sources_urls.subscribers_count' => SORT_DESC
        ]);
    }

    public function orderedByName(): self
    {
        return $this->addOrderBy('name');
    }

    /**
     * @param string|array $type
     * @return $this
     */
    public function byType($type): self
    {
        if ($type === \common\models\Source::TYPE_YOUTUBE) {
            $type = [\common\models\Source::TYPE_YOUTUBE, 'video', \common\models\Source::TYPE_YOUTUBE_PREVIEW];
        }

        return $this->innerJoinWith(['source' => function (Source $query) use ($type) {
            $query->byType($type);
        }]);
    }

    public function byHashTag(string $hashtag): self
    {
        $service = \Yii::$container->get(HashtagsService::class);
        $hashtag = $service->clear($hashtag);

        return $this->innerJoinWith(['hashtags' => function (ActiveQuery $query) use ($hashtag) {
            $query->andWhere(['hashtags.tag' => $hashtag]);
        }])->groupBy('sources_urls.id');
    }

    public function byUrl(string $url, bool $strict = false): self
    {
        if (!$strict) {
            $host = parse_url($url, PHP_URL_HOST);
        }

        return $this
            ->innerJoinWith('source parent_source', false)
            ->andWhere([
                    'AND',
                    ['=', 'parent_source.enabled', 1],
                    [
                        'OR',
                        [$strict ? '=' : 'LIKE', 'sources_urls.url', !$strict ? $host : $url],
                        [$strict ? '=' : 'LIKE', 'parent_source.url', !$strict ? $host : $url]
                    ]
                ]
            );
    }

    public function byChannelName(string $channelName)
    {
        return $this->innerJoinWith([
            'source' => function (Source $query) use ($channelName) {
                $query->andWhere([
                    'LIKE', 'source.url', '%/' . $channelName
                ]);
            }
        ]);
    }

    public function byKeyword(string $keyword)
    {
        return $this->innerJoinWith([
            'source' => function (Source $query) use ($keyword) {
                $query->andWhere([
                    'OR',
                    ['LIKE', 'sources.name', $keyword],
                    ['LIKE', 'sources.url', $keyword],
                    ['LIKE', 'sources_urls.name', $keyword],
                    ['LIKE', 'sources_urls.url', $keyword]
                ]);
            }
        ], false)
            ->groupBy('sources_urls.id');
    }

    public function bySourceName(string $name): self
    {
        return $this->innerJoinWith([
            'source' => function (Source $query) use ($name) {
                $query->andWhere([
                    'LIKE', 'sources.name', $name
                ]);
            }
        ], false);
    }

    public function defaultOnly(bool $bool = true): self
    {
        return $this->andWhere([
            'sources_urls.default' => $bool
        ]);
    }

    public function withSubscribersOnly(): self
    {
        return $this->andWhere([
            '>=', 'sources_urls.subscribers_count', 0
        ]);
    }
}