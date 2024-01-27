<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use yii\widgets\Pjax;
use backend\models\SourceUrl;
use \kartik\editable\Editable;
use \backend\models\Category;
use yii\helpers\Url;
use kartik\widgets\Select2;
use yii\web\View;
use \yii\bootstrap\Modal;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\SourceUrlSearch $searchModel
 */

$this->title = 'Категории источников';
$this->params['breadcrumbs'][] = ['label' => 'Источники', 'url' => ['/sources']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerJsFile(
    '/js/manage-sources-urls.js',
    ['position' => View::POS_END, 'depends' => 'yii\web\JqueryAsset']
);
?>
<div class="source-url-index">

    <textarea id="js-copy-data" style="position: absolute; left: -10000px; top: -10000px;"></textarea>

    <?php Pjax::begin(['id' => 'pjax-sources-urls']);
    echo GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'showPageSummary' => true,
        'rowOptions' => function ($data) {
            /** @var SourceUrl $data */
            return !$data->category_id ? ['class' => 'row-danger'] : [];
        },
        'columns' => array_filter([
            [
                'class' => '\kartik\grid\CheckboxColumn'
            ],
            [
                'attribute' => 'id',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'url',
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var SourceUrl $data */
                    return Html::a($data->url, $data->url, ['target' => 'blank', 'rel' => 'nofollow', 'class' => 'js-source-url']);
                }
            ],
            $searchModel->dateInterval ? [
                'attribute' => 'articlesAmount',
                'label' => 'Новостей',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'pageSummary' => true
            ] : false,
            [
                'filter' => Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'category_id',
                        'data' => Category::getForDropdown(),
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
                'class' => '\kartik\grid\EditableColumn',
                'attribute' => 'category_id',
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var SourceUrl $data */
                    return $data->category->title;
                },
                'editableOptions' => [
                    'pjaxContainerId' => 'pjax-sources-urls',
                    'formOptions' => ['action' => ['/sources/categories/edit-category']],
                    'header' => 'категорию',
                    'inputType' => Editable::INPUT_SELECT2,
                    'options' => [
                        'data' => Category::getForDropdown(),
                    ],
                ],
            ],
            [
                'attribute' => 'country',
                'label' => 'Страны',
                'format' => 'raw',
                'hAlign' => 'center',
                'filter' => Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'country',
                        'data' => \backend\models\Country::getForDropdown(),
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
                    /** @var $data SourceUrl */
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
                'label' => 'Источник',
                'mergeHeader' => true,
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var SourceUrl $data */
                    return Html::a(
                        $data->source->name,
                        Url::to(['/sources/index', 'SourceSearch[id]' => $data->source->id]),
                        ['target' => '_blank', 'rel' => 'nofollow', 'data-pjax' => '0']
                    );
                }
            ],
            [
                'mergeHeader' => true,
                'attribute' => 'last_scraped_at',
                'width' => '150px',
                'hAlign' => 'center',
                'filter' => false,
                'value' => function ($data) {
                    /** @var SourceUrl $data */
                    if (is_object($data->last_scraped_at)) {
                        return $data->last_scraped_at->format('d.m.Y H:i');
                    } else {
                        return '—';
                    }
                }
            ],
            [
                'mergeHeader' => true,
                'attribute' => 'last_scraped_article_date',
                'width' => '150px',
                'hAlign' => 'center',
                'filter' => false,
                'value' => function ($data) {
                    /** @var SourceUrl $data */
                    if (is_object($data->last_scraped_article_date)) {
                        return $data->last_scraped_article_date->format('d.m.Y H:i');
                    } else {
                        return '—';
                    }
                }
            ],
            [
                'mergeHeader' => true,
                'label' => 'Новости',
                'width' => '100px',
                'hAlign' => 'center',
                'content' => function (\common\models\SourceUrl $model) {
                    $today = \Carbon\CarbonImmutable::now();
                    $weekAgo = $today->subWeek();
                    return Html::a('Новости', [
                        'statistics/news',
                        'ByNewsSearch' => [
                            'source_url_id' => $model->id,
                            'dateInterval' => $weekAgo->format('Y-m-d') . ' - ' . $today->format('Y-m-d')
                        ]],
                        [
                            'target' => '_blank',
                            'data-pjax' => 0,
                            'style' => 'text-decoration: underline'
                        ]);
                }
            ],
            [
                'mergeHeader' => true,
                'attribute' => 'created_at',
                'width' => '150px',
                'hAlign' => 'center',
                'filter' => false,
                'value' => function ($data) {
                    /** @var SourceUrl $data */
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
                'attribute' => 'ios_enabled',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [-1 => 'Нет', 1 => 'Да'],
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data SourceUrl */
                    $classes = $data->ios_enabled ? '-check text-success' : '-remove text-danger';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>';
                }
            ],
            [
                'attribute' => 'android_enabled',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [-1 => 'Нет', 1 => 'Да'],
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data SourceUrl */
                    $classes = $data->android_enabled ? '-check text-success' : '-remove text-danger';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>';
                }
            ],
            [
                'attribute' => 'enabled',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [-1 => 'Нет', 1 => 'Да'],
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data SourceUrl */
                    $classes = $data->enabled ? '-check text-success' : '-remove text-danger';
                    $note = $data->note ? ' <span class="text-primary glyphicon glyphicon-comment" title="' . $data->note . '"></span>' : '';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>' . $note;
                }
            ],
            [
                'class' => 'kartik\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Yii::$app->urlManager->createUrl(['sources/categories/process', 'id' => $model->id]),
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
            'before' => Html::a('<i class="glyphicon glyphicon-plus"></i> Добавить', ['process'], ['class' => 'btn btn-success', 'data-pjax' => 0]) . ' ' .
                Html::a(
                    '<i class="glyphicon glyphicon-trash"></i> Удалить',
                    Url::to('/sources/categories/delete-batch'),
                    [
                        'id' => 'js-button-delete-batch',
                        'class' => 'btn btn-danger',
                        'data-confirm' => 'Вы действительно хотите удалить выбранные записи?',
                        'data-method' => 'post',
                    ]) . ' ' .
                Html::button('<i class="glyphicon glyphicon-copy"></i> Копировать в буфер', ['class' => 'btn btn-primary', 'id' => 'js-button-copy-to-buffer']) . ' ' .
//                Html::a('<i class="glyphicon glyphicon-stop"></i> Отключить', Url::to('/sources/categories/change-status-batch'), ['class' => 'btn btn-warning', 'id' => 'js-button-disable-batch']) . ' ' .
//                Html::a('<i class="glyphicon glyphicon-play"></i> Включить', Url::to('/sources/categories/change-status-batch'), ['class' => 'btn btn-warning', 'id' => 'js-button-enable-batch']) . ' ' .
                Html::button('<i class="glyphicon glyphicon-import"></i> Импортировать', ['class' => 'btn btn-success', 'id' => 'js-button-import']) . '<hr />' . $this->render('_filter', ['model' => $searchModel]),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],
    ]);
    Pjax::end();

    echo Modal::widget(['id' => 'js-modal-import']); ?>


</div>
