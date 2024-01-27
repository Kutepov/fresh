<?php

use yii\bootstrap\Modal;
use yii\widgets\Pjax;
use kartik\grid\GridView;
use yii\helpers\Html;
use backend\models\search\statistics\ByNewsSearch;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\statistics\ByNewsSearch $searchModel
 * @var array $sources
 * @var array $languages
 */

$this->title = 'Статистика по новостям';
$this->params['breadcrumbs'][] = ['label' => 'Статистика', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Новости';
?>
    <div class="statistics-news">
        <?php
        echo GridView::widget([
            'pjax' => false,
            'resizableColumns' => false,
            'dataProvider' => $dataProvider,
            'beforeHeader' => [
                [
                    'columns' => array_filter([
                        [
                            'options' => ['colspan' => 4]
                        ],
                        [
                            'options' => ['colspan' => 4],
                            'content' => 'Клики'
                        ],
                        [
                            'options' => ['colspan' => 4],
                            'content' => 'Показы'
                        ],
                        [
                            'options' => ['colspan' => 4],
                            'content' => 'CTR'
                        ],
                    ])
                ]
            ],
            'columns' => [
                [
                    'attribute' => 'created_at',
                    'width' => '100px',
                    'label' => 'Дата',
                    'format' => 'dateTime',
                    'hAlign' => 'center',
                    'content' => function (ByNewsSearch $model) {
                        return \Carbon\Carbon::parse($model->created_at)
                            ->setTimezone(new \Carbon\CarbonTimeZone('Europe/Kiev'))
                            ->toDateTimeString();
                    }
                ],
                [
                    'attribute' => 'preview_image',
                    'format' => 'raw',
                    'hAlign' => 'center',
                    'content' => static function (ByNewsSearch $model) {
                        if ($model->preview_image) {
                            $url = 'https://stx.myfresh.app/' . $model->preview_image;
                            return Html::a(
                                Html::img($url, [
                                    'style' => 'width: 50px;'
                                ]), $url, [
                                'target' => '_blank'
                            ]);
                        }

                        return '&mdash;';
                    }
                ],
                [
                    'attribute' => 'title',
                    'content' => static function (ByNewsSearch $data) {
                        return Html::a($data->title ?: $data->id, $data->url, ['target' => '_blank', 'rel' => 'nofollow']);
                    }
                ],
                [
                    'label' => 'Источник',
                    'mergeHeader' => true,
                    'hAlign' => 'center',
                    'width' => '200px',
                    'content' => static function (ByNewsSearch $data) {
                        return Html::a($data->sourceUrl->url, $data->sourceUrl->url, [
                            'target' => '_blank',
                            'data-pjax' => 0,
                            'style' => 'text-decoration: underline;',
                            'rel' => 'nofollow'
                        ]);
                    }
                ],
                [
                    'attribute' => 'clicks',
                    'width' => 50,
                    'label' => 'Всего',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'clicks_feed',
                    'width' => 50,
                    'label' => 'Фид',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'clicks_top',
                    'width' => 50,
                    'label' => 'ТОП',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'clicks_similar_articles',
                    'width' => 50,
                    'encodeLabel' => false,
                    'label' => 'Читайте<br />также',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'views',
                    'width' => 50,
                    'hAlign' => 'center',
                    'label' => 'Всего',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'views_feed',
                    'width' => 50,
                    'label' => 'Фид',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'views_top',
                    'width' => 50,
                    'label' => 'ТОП',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'views_similar_articles',
                    'width' => 50,
                    'encodeLabel' => false,
                    'label' => 'Читайте<br />также',
                    'hAlign' => 'center',
                    'pageSummary' => true
                ],
                [
                    'attribute' => 'ctr',
                    'width' => 50,
                    'label' => 'Общий',
                    'encodeLabel' => false,
                    'hAlign' => 'center',
                    'pageSummary' => function ($summary, $data, $widget) {
                        if ($ctr = array_sum_key($widget->grid->dataProvider->models, 'ctr')) {
                            return '≈' . round($ctr / count(array_filter($widget->grid->dataProvider->models, static function ($model) {
                                        return $model->ctr;
                                    })));
                        } else {
                            return '&mdash;';
                        }
                    }
                ],
                [
                    'attribute' => 'ctr_feed',
                    'width' => 50,
                    'label' => 'Фид',
                    'encodeLabel' => false,
                    'hAlign' => 'center',
                    'pageSummary' => function ($summary, $data, $widget) {
                        if ($ctr = array_sum_key($widget->grid->dataProvider->models, 'ctr_feed')) {
                            return '≈' . round($ctr / count(array_filter($widget->grid->dataProvider->models, static function ($model) {
                                        return $model->ctr_feed;
                                    })));
                        } else {
                            return '&mdash;';
                        }
                    }
                ],
                [
                    'attribute' => 'ctr_top',
                    'width' => 50,
                    'label' => 'ТОП',
                    'encodeLabel' => false,
                    'hAlign' => 'center',
                    'pageSummary' => function ($summary, $data, $widget) {
                        if ($ctr = array_sum_key($widget->grid->dataProvider->models, 'ctr_top')) {
                            return '≈' . round($ctr / count(array_filter($widget->grid->dataProvider->models, static function ($model) {
                                        return $model->ctr_top;
                                    })));
                        } else {
                            return '&mdash;';
                        }
                    }
                ],
                [
                    'attribute' => 'ctr_similar_articles',
                    'width' => 50,
                    'encodeLabel' => false,
                    'label' => 'Читайте<br />также',
                    'hAlign' => 'center',
                    'pageSummary' => function ($summary, $data, $widget) {
                        if ($ctr = array_sum_key($widget->grid->dataProvider->models, 'ctr_similar_articles')) {
                            return '≈' . round($ctr / count(array_filter($widget->grid->dataProvider->models, static function ($model) {
                                        return $model->ctr_similar_articles;
                                    })));
                        } else {
                            return '&mdash;';
                        }
                    }
                ],
                [
                    'encodeLabel' => false,
                    'attribute' => 'ctr_common_modified',
                    'label' => $searchModel->attributeLabels()['ctr_common_modified'],
                    'hAlign' => 'center',
                    'content' => static function (ByNewsSearch $data) use ($searchModel) {
                        $data->country_id = $searchModel->country_id;
                        $data->source_id = $searchModel->source_id;

                        if (!$data->ctr_common_modified) {
                            if ($ctrTip = $data->getCtrCalcTip()) {
                                return Html::a(
                                    '&mdash;',
                                    'javascript:void(0);',
                                    [
                                        'style' => 'border-bottom: 1px dashed;',
                                        'title' => $ctrTip
                                    ]
                                );
                            }

                            return '&mdash;';
                        }

                        if ($ctrTip = $data->getCtrCalcTip()) {
                            return Html::a(
                                round($data->ctr_common_modified),
                                'javascript:void(0);',
                                [
                                    'style' => 'border-bottom: 1px dashed;',
                                    'title' => $ctrTip
                                ]
                            );
                        }


                        return round($data->ctr_common_modified);
                    },
                    'pageSummary' => function ($summary, $data, $widget) {
                        if ($ctr = array_sum_key($widget->grid->dataProvider->models, 'ctr_common_modified')) {
                            return '≈' . round($ctr / count(array_filter($widget->grid->dataProvider->models, static function ($model) {
                                        return $model->ctr_common_modified;
                                    })));
                        } else {
                            return '&mdash;';
                        }
                    }
                ],
                [
                    'attribute' => 'ctr_common_calc',
                    'encodeLabel' => false,
                    'hAlign' => 'center',
                    'content' => static function (ByNewsSearch $data) {
                        if (!$data->views) {
                            return '&mdash;';
                        }

                        return 'Клики: ' . ($data->clicked_top + $data->clicked) . '<br />' .
                            'Показы: ' . ($data->showed_top + $data->showed);
                    }
                ],
                [
                    'label' => 'Стат.',
                    'hAlign' => 'center',
                    'content' => function (ByNewsSearch $model) {
                        return Html::a('<i class="glyphicon glyphicon-stats"></i>', 'javascript:void(0);', [
                            'onclick' => 'loadArticleTopLog("' . $model->id . '")'
                        ]);
                    }
                ],
                [
                    'attribute' => 'rating',
                    'hAlign' => 'center',
                    'mergeHeader' => true,
                    'width' => '60px',
                    'filter' => false,
                    'content' => static function (ByNewsSearch $data) {
                        if (!$data->ratingPluses && !$data->ratingMinuses) {
                            return '0';
                        }

                        return Html::a(
                            $data->rating,
                            'javascript:void(0);',
                            [
                                'style' => 'border-bottom: 1px dashed;',
                                'title' => 'Плюсов: ' . count($data->ratingPluses) . '<br />Минусов: ' . count($data->ratingMinuses)
                            ]
                        );
                    }
                ],
                [
                    'attribute' => 'comments_count',
                    'hAlign' => 'center',
                    'label' => 'Комм.',
                    'mergeHeader' => true,
                    'width' => 50,
                    'filter' => false,
                    'content' => static function (ByNewsSearch $data) {
                        if (!$data->comments_count) {
                            return '0';
                        }

                        return Html::a(
                            $data->comments_count,
                            \yii\helpers\Url::to(['/comments/index', 'article_id' => $data->id]),
                            [
                                'target' => '_blank',
                                'data-pjax' => 0
                            ]
                        );
                    }
                ],
                [
                    'attribute' => 'shares_count',
                    'hAlign' => 'center',
                    'label' => 'Шеринг',
                    'mergeHeader' => true,
                    'width' => 50,
                    'filter' => false,
                    'content' => static function (ByNewsSearch $data) {
                        return $data->shares_count;
                    }
                ],
                [
                    'attribute' => 'top_position',
                    'hAlign' => 'center',
                    'content' => static function (ByNewsSearch $model) {
                        if (!$model->top_position) {
                            if ($model->accelerated_at) {
                                return 'Разгон';
                            }

                            return '&mdash;';
                        }

                        return $model->top_position;
                    }
                ],
                [
                    'attribute' => 'country_name',
                    'format' => 'raw',
                    'hAlign' => 'center',
                    'value' => static function (ByNewsSearch $data) {
                        return Html::img(Yii::getAlias('@frontendBaseUrl') . '/img/' . $data->flag, ['width' => 40, 'height' => 20, 'title' => $data->country_name]);
                    }
                ],
            ],
            'responsive' => true,
            'hover' => true,
            'condensed' => true,
            'floatHeader' => true,
            'export' => false,

            'panel' => [
                'heading' => '<h3 class="panel-title"><i class="glyphicon glyphicon-th-list"></i> ' . Html::encode($this->title) . ' </h3>',
                'type' => 'info',
                'before' => $this->render('_newsSearch', ['model' => $searchModel, 'sources' => $sources, 'languages' => $languages]) . (
                    $topCalculatedTime !== false ? '<strong style="float: right">Топ пересчитан ' . ($topCalculatedTime <= 0 ? 'только что' : ($topCalculatedTime . ' мин. назад.')) . '</strong><br clear="both">' : ''
                    ),
                'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
                'showFooter' => true,
            ],
            'showPageSummary' => true
        ]); ?>
    </div>
    <style>
        #top-log-modal {
            z-index: 9999999 !important;
        }
    </style>

<?php
Modal::begin([
    'id' => 'top-log-modal',
    'size' => Modal::SIZE_LARGE,
    'headerOptions' => [
        'data-header' => true
    ]
])
?>
<?php Modal::end() ?>