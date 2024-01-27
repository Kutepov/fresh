<?php

use yii\helpers\Html;
use backend\components\GridView;
use yii\widgets\Pjax;
use common\models\Category;
use \backend\models\Country;
use \kartik\select2\Select2;
use \common\services\MultilingualService;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\CategorySearch $searchModel
 */

$this->title = 'Категории';
$this->params['breadcrumbs'][] = $this->title;
$jpCondition = $searchModel->listCountries === 'JP';
?>
<div class="category-index">


    <?php Pjax::begin();
    echo GridView::widget([
        'sortableAction' => ['categories/sort', 'jpCondition' => (int)$jpCondition],
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'name',
                'format' => 'raw',
                'hAlign' => 'left',
                'value' => function ($data) use ($searchModel) {
                    /** @var $data Category */
                    $tooltip = '';
                    foreach ($searchModel->multilingualService->getAvailableLanguagesCodes() as $lang) {
                        $attr = 'title_' . $lang;
                        $titleLang = $data->$attr;
                        if ($titleLang) {
                            $tooltip .= $titleLang . '<br>';
                        }
                    }
                    $title = $data->title ?: '—';
                    return '<a href="#" style="border-bottom: 1px dashed;" title="' . $tooltip . '">' . $title . '</span>';
                },
                'width' => '200px'
            ],
            [
                'width' => '120px',
                'attribute' => 'listCountries',
                'header' => 'Страны',
                'filter' => Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'listCountries',
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
                'hAlign' => 'center',
                'format' => 'raw',
                'value' => function ($data) {
                    /** @var $data Category */
                    $result = '';
                    $progress = $data->getProgressCountries();

                    foreach ($data->countries as $country) {
                        $result .= Html::img($country->countryModel->getAbsoluteUrlToImage(), ['width' => 30, 'height' => 20]) . ' <span>' .
                            $country->countryModel->name . '</span><br>';
                    }

                    $title = $progress['choice'] > 0 ? 'title="' . htmlspecialchars($result) . '"' : '';

                    return '<span class="badge badge-secondary" ' . $title . '>' . $progress['choice'] . ' из ' . $progress['total'] . '</span>';
                }
            ],
            [
                'label' => 'Активные<br />Источники',
                'encodeLabel' => false,
                'mergeHeader' => true,
                'hAlign' => 'center',
                'width' => '120px',
                'content' => function (\backend\models\Category $category) {
                    if ($count = count($category->enabledSourcesUrls)) {
                        return Html::a($count, [
                            'source-urls/index',
                            'SourceUrlSearch' => [
                                'category_id' => $category->id,
                                'country' => $category->country,
                                'enabled' => 1
                            ]
                        ], [
                            'target' => '_blank',
                            'data-pjax' => 0,
                            'style' => 'text-decoration: underline'
                        ]);
                    }

                    return '&mdash;';
                }
            ],
            [
                'attribute' => 'translatesProgress',
                'header' => 'Переводы',
                'mergeHeader' => true,
                'format' => 'raw',
                'hAlign' => 'center',
                'width' => '120px',
                'contentOptions' => function ($data) {
                    /** @var $data Category */
                    $progress = $data->getProgressTranslates();
                    $class = $progress['total'] === $progress['filled'] ? 'bg-success' : 'bg-danger';
                    return ['class' => $class];
                },
                'value' => function ($data) {
                    /** @var $data Category */
                    $progress = $data->getProgressTranslates();
                    return $progress['filled'] . ' из ' . $progress['total'];
                }

            ],
            [
                'attribute' => 'image',
                'format' => 'raw',
                'mergeHeader' => true,
                'hAlign' => 'center',
                'width' => '90px',
                'value' => function ($data) {
                    /** @var $data Category */
                    return Html::img($data->getAbsoluteUrlToImage(), [
                        'style' => 'max-height: 50px; max-width: 50px'
                    ]);
                }
            ],
            [
                'attribute' => 'icon',
                'format' => 'raw',
                'mergeHeader' => true,
                'hAlign' => 'center',
                'width' => '90px',
                'value' => function ($data) {
                    /** @var $data Category */
                    return Html::img($data->getAbsoluteUrlToIcon(), [
                        'style' => 'max-height: 50px; max-width: 50px'
                    ]);
                }
            ],
            [
                'attribute' => $jpCondition ? 'jp_priority' : 'priority',
                'label' => $jpCondition ? 'Приоритет<br />(Только Япония)' : 'Приоритет',
                'mergeHeader' => true,
                'encodeLabel' => false,
                'hAlign' => 'center',
                'width' => '30px',
            ],
            [
                'mergeHeader' => true,
                'hAlign' => 'center',
                'width' => '100px',
                'label' => 'Сортировать',
                'content' => function ($model) {
                    return '<i class="glyphicon glyphicon-menu-hamburger" style="cursor: move;"></i>';
                }
            ],
            [
                'class' => 'kartik\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        /** @var $model \backend\models\Category */
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Yii::$app->urlManager->createUrl(['categories/process', 'id' => $model->id]),
                            ['title' => 'Редактировать', 'data-pjax' => 0]
                        );
                    }
                ]
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
            'before' => Html::a('<i class="glyphicon glyphicon-plus"></i> Добавить', ['process'], ['class' => 'btn btn-success', 'data-pjax' => 0]),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],
    ]);
    Pjax::end(); ?>

</div>
