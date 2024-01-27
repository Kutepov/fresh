<?php namespace common\services;

use api\models\search\ArticlesSearch;
use api\models\search\SimilarArticlesSearch;
use common\components\helpers\Api;
use common\models\elasticsearch\Article;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class ArticlesIndexer
{
    private const INDEX_MAPPING = [
        'article' => [
            'properties' => [
                'created_at' => ['type' => 'integer'],
                'country' => ['type' => 'keyword', 'null_value' => 'NULL'],
                'language' => ['type' => 'keyword', 'null_value' => 'NULL'],
                'source_id' => ['type' => 'keyword'],
                'source_url_id' => ['type' => 'keyword'],
                'category_id' => ['type' => 'keyword'],
                'category_name' => ['type' => 'keyword'],
                'title' => ['type' => 'text', 'analyzer' => 'custom_analyzer'],
                'banned_words' => ['type' => 'boolean'],
                'body' => ['type' => 'text', 'analyzer' => 'custom_analyzer'],
            ]
        ]
    ];

    public function searchArticlesIds(ArticlesSearch $searchForm)
    {
        if (Api::version(Api::V_2_20) && !$searchForm->sourceUrl) {
            return [];
        }

        $filters = array_filter([
            !$searchForm->source ? false : [
                'terms' => [
                    'source_id' => $searchForm->source
                ]
            ],
            !$searchForm->sourceUrl ? false : [
                'terms' => [
                    'source_url_id' => $searchForm->sourceUrl
                ]
            ]
        ]);

        if ($searchForm->skipBanned) {
            $filters[] = [
                'term' => [
                    'banned_words' => false
                ],
            ];
        }

        return Article::findByLocale($searchForm->locale)
            ->query([
                'bool' => [
                    'must' => array_values(ArrayHelper::merge($filters, [
                        [
                            'term' => [
                                'country' => $searchForm->country
                            ],
                        ],
                        [
                            'term' => [
                                'language' => $searchForm->articlesLanguage ?: 'NULL'
                            ]
                        ],
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'match_phrase' => [
                                            'title' => mb_strtolower($searchForm->query)
                                        ],
                                    ],
                                    [
                                        'wildcard' => [
                                            'title' => '*' . mb_strtolower($searchForm->query) . '*'
                                        ],
                                    ],
                                ]
                            ]
                        ],
//                        $article->source->isParsedArticlesBodies ? [
//                            'query_string' => [
//                                'fields' => ['body'],
//                                'query' => $article->title,
//                                'minimum_should_match' => '70%',
//                            ],
//                        ] : false,
                    ]))
                ],
            ])
            ->orderBy([
                'created_at' => SORT_DESC
            ])
            ->offset($searchForm->offset)
            ->limit($searchForm->limit)
            ->column('_id');
    }

    /**
     * Поиск похожих новостей по заданным параметрам
     * @param SimilarArticlesSearch $searchForm
     * @return \common\models\Article[]
     * @throws \yii\elasticsearch\Exception
     */
    public function findSimilarArticles(SimilarArticlesSearch $searchForm): array
    {
        if ($searchForm->article && !$searchForm->article->locale) {
            return [];
        }

        $filters = array_filter([
            !$searchForm->source ? false : [
                'terms' => [
                    'source_id' => $searchForm->source,
                ],
            ],
            !$searchForm->sourceUrl ? false : [
                'terms' => [
                    'source_url_id' => $searchForm->sourceUrl
                ],
            ],
            [
                'range' => [
                    'created_at' => [
                        'gt' => (new \DateTimeImmutable('-30 days'))->getTimestamp()
                    ]
                ]
            ]
        ]);

        if ($searchForm->skipBanned) {
            $filters[] = [
                'term' => [
                    'banned_words' => false
                ],
            ];
        }

        $ids = Article::findByLocale($searchForm->article->locale ?? '*')
            ->query([
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must' => array_values($filters),
                            'must_not' => [
                                [
                                    'term' => $searchForm->article ? ['_id' => $searchForm->articleId] : ['title' => $searchForm->title],
                                ]
                            ],
                        ],
                    ],
                    'boost' => '1',
                    'score_mode' => 'sum',
                    'boost_mode' => 'sum',
                    'functions' => array_values(array_filter([
                        [
                            'filter' => [
                                'match' => [
                                    'title' => [
                                        'query' => $searchForm->article->title ?? $searchForm->title,
                                        'minimum_should_match' => '50%',
                                    ],
                                ],
                            ],
                            'weight' => 80,
                        ],
                        $searchForm->article ? [
                            'filter' => [
                                'term' => [
                                    'category_name' => $searchForm->article->category_name,
                                ],
                            ],
                            'weight' => 10,
                        ] : false,
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('today'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable())->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 30,
                        ],
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('-2 days'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable('today'))->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 12,
                        ],
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('-3 days'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable('-2 days'))->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 10,
                        ],
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('-4 days'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable('-3 days'))->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 8,
                        ],
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('-5 days'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable('-4 days'))->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 5,
                        ],
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('-6 days'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable('-5 days'))->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 3,
                        ],
                        [
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gt' => (new \DateTimeImmutable('-30 days'))->getTimestamp(),
                                        'lt' => (new \DateTimeImmutable('-6 days'))->getTimestamp(),
                                    ],
                                ],
                            ],
                            'weight' => 2,
                        ],
                    ])),
                ]
            ])
            ->limit($searchForm->limit + (!$searchForm->article ? 1 : 0))
            ->column('_id');


        $articles = \common\models\Article::findByIds($ids);

        /** Для старых версий приложения */
        if (!$searchForm->article) {
            $articles = array_values(array_filter($articles, static function (\common\models\Article $article) use ($searchForm) {
                return $article->title !== $searchForm->title;
            }));

            if (count($articles) > $searchForm->limit) {
                $articles = array_slice($articles, 0, $searchForm->limit);
            }
        }

        return $articles;
    }

    /**
     * Поиск "одинаковой" новости в индексе
     * @param \common\models\Article $article
     * @return \common\models\Article|null
     */
    public function findSameArticle(\common\models\Article $article): ?\common\models\Article
    {
        if (!$article->locale) {
            return null;
        }

        $model = Article::findByLocale($article->locale)
            ->query([
                'bool' => array_filter([
                    'must' => array_filter([
                        [
                            'term' => [
                                'country' => $article->source->country ?: 'NULL'
                            ],
                        ],
                        [
                            'term' => [
                                'language' => $article->source->language ?: 'NULL'
                            ]
                        ],
                        [
                            'match' => [
                                'title' => [
                                    'query' => $article->title,
                                    'minimum_should_match' => $article->source->isParsedArticlesBodies ? '45%' : '50%',
                                ],
                            ],
                        ],
//                        $article->source->isParsedArticlesBodies ? [
//                            'query_string' => [
//                                'fields' => ['body'],
//                                'query' => $article->title,
//                                'minimum_should_match' => '70%',
//                            ],
//                        ] : false,
                        [
                            'range' => [
                                'created_at' => [
                                    'gt' => time() - 86400,
                                ],
                            ],
                        ],
                    ]),
                    'must_not' => !$article->isNewRecord ? [
                        'term' => ['_id' => $article->id]
                    ] : false
                ]),
            ])
            ->one();

        if ($model) {
            return \common\models\Article::findById($model->_id);
        }

        return null;
    }

    /**
     * Добавление новости в индекс
     * @param \common\models\Article $article
     */
    public function add(\common\models\Article $article): void
    {
        if ($article->locale) {
            $model = $this->createDocument($article);
            $model::$currentLocale = $article->locale;
            $model->save();
        }
    }

    /**
     * @param \common\models\Article[] $articles
     */
    public function batchAdd(array $articles): void
    {
        $indexClass = new Article();

        $bulk = [];

        foreach ($articles as $article) {
            if ($article->locale) {
                $action = Json::encode([
                    'index' => [
                        '_id' => $article->getPrimaryKey(),
                        '_type' => $indexClass::type(),
                        '_index' => $indexClass::indexForLocale($article->locale)
                    ]
                ]);

                $document = $this->createDocument($article);
                $data = Json::encode($document->toArray());
                $bulk[$article->locale] .= $action . "\n" . $data . "\n";
            }
        }

        foreach ($bulk as $locale => $actions) {
            $url = [
                $indexClass::indexForLocale($locale),
                $indexClass::type(),
                '_bulk'
            ];

            Article::getDb()->post($url, [], $actions);
        }
    }

    private function createDocument(\common\models\Article $article): Article
    {
        return new Article([
            '_id' => $article->id,
            'created_at' => $article->created_at->timestamp,
            'source_id' => $article->source_id,
            'source_url_id' => $article->source_url_id,
            'country' => $article->source->countriesCodes,
            'language' => $article->source->language,
            'category_id' => $article->category_id,
            'category_name' => $article->category_name,
            'title' => $article->title,
            'banned_words' => (bool)$article->banned_words,
            'body' => $article->bodyAsString
        ]);
    }

    /**
     * Обновление новости в индексе
     * @param \common\models\Article $article
     */
    public function update(\common\models\Article $article): void
    {
        if ($article->locale && ($model = Article::findOneByLocale($article->id, $article->locale))) {
            $model->setAttributes([
                'created_at' => $article->created_at->timestamp,
                'source_id' => $article->source_id,
                'source_url_id' => $article->source_url_id,
                'category_id' => $article->category_id,
                'category_name' => $article->category_name,
                'title' => $article->title,
                'banned_words' => (bool)$article->banned_words,
                'body' => $article->bodyAsString
            ]);

            $model->save();
        }
    }

    /**
     * Удаление новости из индекса
     * @param \common\models\Article $article
     * @throws \yii\db\Exception
     * @throws \yii\db\StaleObjectException
     * @throws \yii\elasticsearch\Exception
     */
    public function delete(\common\models\Article $article): void
    {
        if ($article->locale) {
            if ($model = Article::findOneByLocale(
                $article->id,
                $article->locale)
            ) {
                $model->delete();
            }
        }
    }

    /**
     * Удаление всех новостей из индекса
     * @throws \yii\elasticsearch\Exception
     */
    public function clear(string $locale): void
    {
        Article::deleteAllForLocale($locale);
    }

    public function isIndexExists(string $locale): bool
    {
        return Article::getDb()
            ->createCommand()
            ->indexExists(Article::indexForLocale($locale));
    }

    public function createIndex(string $locale): void
    {
        Article::getDb()
            ->createCommand()
            ->createIndex(
                Article::indexForLocale($locale),
                [
                    'settings' => [
                        'analysis' => [
//                            'normalizer' => $this->initNormalizer(),
                            'analyzer' => $this->initAnalyzer($locale),
                            'filter' => $this->initFilter($locale),
                        ]
                    ],
                    'mappings' => self::INDEX_MAPPING
                ]
            );
    }

    public function deleteIndex(string $locale): void
    {
        Article::getDb()
            ->createCommand()
            ->deleteIndex(
                Article::indexForLocale($locale)
            );
    }

    private function initFilter(string $language): array
    {
        return [
            $language => [
                'type' => 'hunspell',
                'locale' => $language,
            ]
        ];
    }

    private function initAnalyzer(string $language): array
    {
        return [
            'custom_analyzer' => [
                'tokenizer' => 'standard',
                'filter' => [$language, 'lowercase'],
//                'normalizer' => 'lowercase_normalizer'
            ]
        ];
    }

    private function initNormalizer(): array
    {
        return [
            'lowercase_normalizer' => [
                'type' => 'custom',
                'char_filter' => [],
                'filter' => ['lowercase']
            ]
        ];
    }
}