<?php namespace common\queries;

use yii\db\ActiveQuery;

/**
 * Class SourceUrl
 * @package common\queries
 *
 * @see \common\models\Source
 */
class Source extends ActiveQuery
{
    public function withEnabledUrlsOnly($notLocked = true): self
    {
        return $this
            ->innerJoinWith([
                'urls' => function (SourceUrl $query) use ($notLocked) {
                    $query->enabled();
                    if ($notLocked) {
                        $query->notLocked();
                    }
                }
            ], false);
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
        if ($strict) {
            return $this->andFilterWhere([
                'sources.language' => $languageCode
            ]);
        }

        return $this;
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->andWhere([
            'sources.enabled' => $enabled
        ]);
    }

    public function defaultOnly(bool $bool = true): self
    {
        return $this->andWhere([
            'sources.default' => $bool
        ]);
    }

    public function withAdBlockRules(): self
    {
        return $this->andWhere([
            'AND',
            ['<>', 'sources.adblock_css_selectors', ''],
            ['IS NOT', 'sources.adblock_css_selectors', null]
        ]);
    }

    public function byType($type): self
    {
        return $this->andWhere([
            'sources.type' => $type
        ]);
    }

    public function withSubscribersOnly(): self
    {
        return $this->andWhere([
            '>=', 'sources.subscribers_count', 0
        ]);
    }
}