<?php namespace backend\models\search\statistics;

use common\models\aggregate\HistoricalStatistics;
use common\models\Article;
use common\models\Category;
use common\models\Country;
use common\models\Source;
use common\models\statistics\ArticleClick;
use common\models\statistics\ArticleView;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

class CategoriesSearch extends Category
{
    use Calendar;

    public $totalArticlesCount = 0;

    public $platform;
    public $countryCode = 'UA';
    public $articlesLanguage;
    public $previewType;

    public $title;
    public $categoryId;
    public $articlesAmount;
    public $clicks;
    public $views;
    public $ctr;

    public function rules(): array
    {
        return [
            [['dateInterval', 'countryCode', 'articlesLanguage', 'platform', 'previewType'], 'safe']
        ];
    }

    public function attributeLabels()
    {
        return [
            'dateInterval' => 'Дата',
            'platform' => 'ОС',
            'countryCode' => 'Страна',
            'articlesLanguage' => 'Язык новостей',
            'previewType' => 'Размер картинок-превью'
        ];
    }

    public function search($params)
    {
        $this->load($params);

        if ($this->previewType === 'small') {
            $this->previewType = ['small', null];
        }

        if (!$this->dateInterval) {
            $this->createDefaultDatesInterval(0);
        }

        $sourcesIds = Source::find()
            ->select('sources.id')
            ->byCountry($this->countryCode)
            ->byLanguage($this->articlesLanguage)
            ->column();

        $country = Country::findByCode($this->countryCode);

        $language = substr($country->locale, 0, 2);
        if (!$language && $this->articlesLanguage) {
            $language = $this->articlesLanguage;
        }

        $articlesIds = Article::find()
            ->where([
                'source_id' => $sourcesIds
            ])
            ->andWhere($this->dateCondition('created_at'))
            ->select('id')
            ->column(null, false);

        $this->totalArticlesCount = count($articlesIds);

        $categoriesCount = Category::find()->forCountry($this->countryCode)->count();

        $query = self::find()
            ->forCountry($this->countryCode)
            ->leftJoin('categories_lang', [
                'categories_lang.language' => $language,
                'categories_lang.owner_id' => new Expression('categories.id')
            ])
            ->select([
                'categories_lang.title as title',
                'categories.id as categoryId',
                'articlesAmount',
                'clicks',
                'views',
                'ROUND(clicks/views*100, 2) as ctr'
            ])
            ->groupBy('categories.id');

        $query->leftJoin([
            'articles' => Article::find()
                ->select([
                    'COUNT(*) as articlesAmount',
                    'category_id',
                    'created_at'
                ])
                ->andWhere(['id' => $articlesIds])
                ->groupBy('articles.category_id')
        ], 'articles.category_id = categories.id');

        if (!$this->previewType) {
            $query->leftJoin([
                'clicks' => HistoricalStatistics::find()
                    ->from(['clicks' => HistoricalStatistics::tableName()])
                    ->select([
                        'SUM(clicks) as clicks',
                        'category_id',
                        'date'
                    ])
                    ->andWhere($this->dateCondition('clicks.date', true))
                    ->andFilterWhere([
                        'clicks.platform' => $this->platform,
                        'clicks.country' => $this->countryCode,
                        'clicks.articles_language' => $this->articlesLanguage
                    ])
                    ->groupBy('clicks.category_id')
            ], 'clicks.category_id = categories.id');


            $query->leftJoin([
                'views' => HistoricalStatistics::find()
                    ->from(['views' => HistoricalStatistics::tableName()])
                    ->select([
                        'SUM(views) as views',
                        'category_id',
                        'date'
                    ])
                    ->andWhere($this->dateCondition('views.date', true))
                    ->andFilterWhere([
                        'views.platform' => $this->platform,
                        'views.country' => $this->countryCode,
                        'views.articles_language' => $this->articlesLanguage
                    ])
                    ->groupBy('views.category_id')
            ], 'views.category_id = categories.id');


        }
        else {
            $query->leftJoin([
                'clicks' => ArticleClick::find()
                    ->from(['clicks' => ArticleClick::tableName()])
                    ->select([
                        'COUNT(*) as clicks',
                        'category_id',
                        'date'
                    ])
                    ->andWhere($this->dateCondition('clicks.date', true))
                    ->andWhere(['clicks.article_id' => $articlesIds])
                    ->andFilterWhere([
                        'clicks.platform' => $this->platform,
                        'preview_type' => $this->previewType
                    ])
                    ->groupBy('clicks.category_id')
            ], 'clicks.category_id = categories.id');

            $query->leftJoin([
                'views' => ArticleView::find()
                    ->from(['views' => ArticleView::tableName()])
                    ->select([
                        'COUNT(*) as views',
                        'category_id',
                        'date'
                    ])
                    ->andWhere($this->dateCondition('views.date', true))
                    ->andWhere(['views.article_id' => $articlesIds])
                    ->andFilterWhere([
                        'views.platform' => $this->platform,
                        'preview_type' => $this->previewType
                    ])
                    ->groupBy('views.category_id')
            ], 'views.category_id = categories.id');
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'attributes' => [
                    'articlesAmount',
                    'clicks',
                    'views',
                    'ctr'
                ],
                'defaultOrder' => [
                    'clicks' => SORT_DESC,
                    'articlesAmount' => SORT_DESC
                ],
            ],
        ]);

        $dataProvider->setTotalCount($categoriesCount);

        return $dataProvider;
    }
}