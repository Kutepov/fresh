<?php namespace common\services;

use api\models\search\ArticlesSearch;
use backend\models\forms\SettingsSearchForm;
use Carbon\CarbonImmutable;
use common\models\Country;
use common\models\SearchQuery;
use common\models\SearchQueryLog;
use yii\db\Query;

class SearchService
{
    private $articlesService;

    public function __construct(ArticlesService $articlesService)
    {
        $this->articlesService = $articlesService;
    }

    /**
     * @param string $query
     * @param string $country
     * @param string $language
     * @return \common\models\Article[]
     */
    public function findArticles(ArticlesSearch $searchModel): array
    {
        if ($searchModel->hasErrors()) {
            return [];
        }

        $articles = $this->articlesService->search($searchModel);

        if (count($articles) > 0) {
            $this->createLog(
                $searchModel
            );
        }

        return $articles;
    }

    /**
     * @param string $query
     * @param string $country
     * @param string $language
     */
    private function createLog(ArticlesSearch $searchModel): void
    {
        (new SearchQueryLog([
            'locale' => $searchModel->locale,
            'country' => $searchModel->country,
            'query' => $searchModel->query
        ]))->save();
    }

    /**
     * @param string $country
     * @param string $language
     * @return string[]
     */
    public function getTopQueries(ArticlesSearch $searchModel): array
    {
        return SearchQuery::find()
            ->select('query')
            ->where([
                'locale' => $searchModel->locale,
                'country' => $searchModel->country
            ])
            ->orderBy(['amount' => SORT_DESC])
            ->column();
    }

    /**
     * @param string $country
     * @param string $language
     * @param int $amount
     * @return void
     */
    public function cacheTopQueries(string $country, string $locale): void
    {
        $period = SettingsSearchForm::get('topQueriesPeriod', $country);
        $amount = SettingsSearchForm::get('topQueriesAmount', $country);
        $queriesLimit = SettingsSearchForm::get('queriesLimit', $country) ?: 1;

        $dateEnd = CarbonImmutable::now();
        $dateStart = $dateEnd->subHours($period);

        $topQueries = (new Query())
            ->from(SearchQueryLog::tableName())
            ->indexBy('query')
            ->select([
                'query',
                'country',
                'locale',
                'COUNT(*) as amount'
            ])
            ->where([
                'AND',
                ['>=', 'created_at', $dateStart->toDateTimeString()],
                ['<=', 'created_at', $dateEnd->toDateTimeString()],
                ['=', 'country', $country],
                ['=', 'locale', $locale],
            ])
            ->andWhere(['NOT LIKE', 'query', 'порно'])
            ->andWhere(['NOT LIKE', 'query', 'секс'])
            ->andWhere(['<>', 'query', 'что'])
            ->andHaving([
                '>=', 'amount', $queriesLimit
            ])
            ->orderBy([
                'query' => SORT_ASC
            ])
            ->groupBy('query')
            ->all();


        if ($topQueries) {
            $preparedTopQueries = [];
            foreach ($topQueries as $query => $data) {
                $found = false;
                foreach ($preparedTopQueries as $preparedQuery => $preparedData) {
                    if (preg_match('#^' . preg_quote($preparedQuery, '#') . '#siu', $query) && mb_strlen($query) > mb_strlen($preparedQuery)) {
                        unset($preparedTopQueries[$preparedQuery]);
                        $preparedTopQueries[$query] = $data;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $preparedTopQueries[$query] = $data;
                }
            }


            if (count($preparedTopQueries)) {
                SearchQuery::deleteAll([
                    'country' => $country,
                    'locale' => $locale
                ]);

                $preparedTopQueries = array_slice($preparedTopQueries, 0, $amount);

                \Yii::$app->db->createCommand()
                    ->batchInsertIgnoreFromArray(
                        SearchQuery::tableName(),
                        array_values($preparedTopQueries)
                    )->execute();
            }
        }

    }

    public function cacheAllTopQueries(): void
    {
        $countries = Country::find()->all();
        foreach ($countries as $country) {
            foreach ($country->articlesLanguages ?: [null] as $language) {
                $this->cacheTopQueries($country->code, $language->locale ?? $country->locale);
            }
        }
    }
}