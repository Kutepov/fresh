<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use yii\widgets\Pjax;
use backend\models\Source;
use kartik\widgets\Select2;
use backend\models\Country;
use backend\models\Language;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\SourceSearch $searchModel
 */

$this->title = 'Источники';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="source-index">


    <?php Pjax::begin();
    echo GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'showPageSummary' => true,
        'columns' => array_filter([
            [
                'attribute' => 'id',
                'hAlign' => 'center',
                'width' => '150px'
            ],
            [
                'attribute' => 'image',
                'format' => 'raw',
                'mergeHeader' => true,
                'hAlign' => 'center',
                'width' => '50px',
                'content' => function ($data) {
                    /** @var $data Source */
                    if ($data->image) {
                        return Html::img($data->getAbsoluteUrlToImage(), ['style' => 'max-width: 50px; max-height: 50px;']);
                    }

                    if ($data->external_image_url) {
                        return Html::img($data->external_image_url, ['style' => 'max-width: 50px; max-height: 50px;']);
                    }

                    return '-';
                }
            ],
            [
                'attribute' => 'name',
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data Source */
                    $groupLink = $data->group_id ? Html::a('<span class="glyphicon glyphicon-inbox"></span>', Url::to(['/sources/index', 'SourceSearch[group_id]' => $data->group_id])) : '';
                    return Html::a($data->name, $data->url, ['target' => '_blank', 'rel' => 'nofollow']) . ' ' . $groupLink;
                }
            ],
            $searchModel->dateInterval ? [
                'attribute' => 'articlesAmount',
                'label' => 'Новостей',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'content' => static function ($model) use ($searchModel) {
                    return Html::a($model->articlesAmount, ['sources/categories/index', 'SourceUrlSearch[source_id]' => $model->id, 'SourceUrlSearch[dateInterval]' => $searchModel->dateInterval], ['target' => '_blank', 'data-pjax' => 0]);
                },
                'value' => function ($model) {
                    return $model->articlesAmount;
                },
                'pageSummary' => true
            ] : false,
            [
                'attribute' => 'urls',
                'format' => 'raw',
                'hAlign' => 'center',
                'value' => function ($data) {
                    /** @var $data Source */
                    return Html::a(
                        $data->getUrls()->count(),
                        Url::to(['/sources/categories/index', 'SourceUrlSearch[source_id]' => $data->id]),
                        ['target' => '_blank', 'rel' => 'nofollow', 'data-pjax' => '0']
                    );
                }
            ],
            [
                'attribute' => 'country',
                'label' => 'Страны',
                'format' => 'raw',
                'hAlign' => 'center',
                'filter' => Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'country',
                        'data' => Country::getForDropdown(),
                        'options' => [
                            'class' => 'form-control',
                            'placeholder' => 'Выберите значение'
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'selectOnClose' => true,
                        ]
                    ]
                ),
                'value' => function ($data) {
                    /** @var $data Source */
                    if (!$data->countries) {
                        return 'Все';
                    }

                    $countriesIcons = array_map(static function (\common\models\Country $country) {
                        return Html::img($country->getAbsoluteUrlToImage(), ['height' => 20, 'width' => 40, 'alt' => $country->name]);
                    }, $data->countries);


                    return implode(' ', $countriesIcons);
                }
            ],
            [
                'attribute' => 'language',
                'format' => 'raw',
                'filter' => Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'language',
                        'data' => Language::getForDropdown(),
                        'options' => [
                            'class' => 'form-control',
                            'placeholder' => 'Выберите значение'
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'selectOnClose' => true,
                        ]
                    ]
                ),

                'value' => function ($data) {
                    /** @var $data Source */
                    return $data->languageModel->name;
                }
            ],
            [
                'attribute' => 'type',
                'filter' => Source::TYPES,
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data Source */
                    return Source::TYPES[$data->type];
                }
            ],
            [
                'attribute' => 'created_at',
                'mergeHeader' => true,
                'width' => '150px',
                'value' => function ($data) {
                    /** @var $data Source */
                    return $data->created_at->format('d.m.Y H:i');
                }
            ],
            [
                'attribute' => 'subscribers_count',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'width' => '60px'
            ],
            [
                'attribute' => 'enabled',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [0 => 'Нет', 1 => 'Да'],
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data Source */
                    $classes = $data->enabled ? '-check text-success' : '-remove text-danger';
                    $note = $data->note ? ' <span class="text-primary glyphicon glyphicon-comment" title="' . $data->note . '"></span>' : '';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>' . $note;
                }
            ],
            [
                'attribute' => 'default',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [0 => 'Нет', 1 => 'Да'],
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data Source */
                    $classes = $data->default ? '-check text-success' : '-remove text-danger';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>';
                }
            ],
            [
                'attribute' => 'processed',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [0 => 'Нет', 1 => 'Да'],
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data Source */
                    $classes = $data->processed ? '-check text-success' : '-remove text-danger';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>';
                }
            ],
            [
                'class' => 'kartik\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Yii::$app->urlManager->createUrl(['sources/process', 'id' => $model->id]),
                            ['title' => 'Редактировать', 'data-pjax' => 0]
                        );
                    },
                ],
            ],
        ]),
        'responsive' => true,
        'hover' => true,
        'condensed' => true,
        'floatHeader' => true,
        'export' => false,

        'panel' => [
            'heading' => '<h3 class="panel-title"><i class="glyphicon glyphicon-th-list"></i> ' . Html::encode($this->title) . ' </h3>',
            'type' => 'info',
            'before' => Html::a('<i class="glyphicon glyphicon-plus"></i> Добавить', ['process'], ['class' => 'btn btn-success', 'data-pjax' => 0]) . '<hr />' . $this->render('_filter', ['model' => $searchModel]),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],
    ]);
    Pjax::end(); ?>

</div>
