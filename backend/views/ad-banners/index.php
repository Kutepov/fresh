<?php

use common\models\Category;
use yii\helpers\Html;
use kartik\grid\GridView;
use yii\widgets\Pjax;
use common\models\AdBanner;
use kartik\widgets\Select2;
use backend\models\Country;
use kartik\widgets\SwitchInput;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\AdBannerSearch $searchModel
 */

$this->title = 'Рекламные баннеры';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ad-banner-index">
    <?php Pjax::begin();
    echo GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'enabled',
                'format' => 'raw',
                'filter' => [0 => 'Нет', 1 => 'Да'],
                'value' => function (AdBanner $data) {
                    return SwitchInput::widget(
                        [
                            'name' => 'enabled',
                            'pluginEvents' => [
                                'switchChange.bootstrapSwitch' => " function(e) { $.ajax({url: '/ad-banners/change-status?id= " . $data->id . "&enabled=' + (e.currentTarget.checked ? 1 : 0)})}"
                            ],

                            'pluginOptions' => [
                                'size' => 'mini',
                                'onColor' => 'success',
                                'offColor' => 'danger',
                                'onText' => 'Да',
                                'offText' => 'Нет'
                            ],
                            'value' => $data->enabled
                        ]
                    );
                }

            ],
            [
                'attribute' => 'type',
                'value' => function (AdBanner $data) {
                    return AdBanner::TYPES[$data->type];
                },
                'filter' => AdBanner::TYPES,
            ],
            [
                'attribute' => 'platform',
                'value' => function (AdBanner $data) {
                    return AdBanner::PLATFORMS[$data->platform];
                },
                'filter' => AdBanner::PLATFORMS,
            ],
            [
                'attribute' => 'country',
                'format' => 'raw',
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
                'value' => function (AdBanner $data) {
                    return Html::img($data->countryModel->getAbsoluteUrlToImage(), ['height' => 20, 'width' => 40]) . ' ' . $data->countryModel->name;
                }
            ],
            [
                'attribute' => 'provider',
                'value' => function (AdBanner $data) {
                    return AdBanner::PROVIDERS[$data->provider];
                },
                'filter' => AdBanner::PROVIDERS,
            ],
            'position',
            'repeat_factor',
            'limit',
            [
                'attribute' => 'categories',
                'format' => 'raw',
                'hAlign' => 'center',
                'value' => function (AdBanner $data) {
                    $result = 'title="';
                    $categories = $data->getCategories();
                    foreach ($categories as $category) {
                        $result .= $category->title . '<br>';
                    }

                    return '<span class="badge badge-secondary" ' . $result . '" >' . count($categories) . ' из ' . Category::find()->count() . '</span>';
                }
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Yii::$app->urlManager->createUrl(['ad-banners/update', 'id' => $model->id]),
                            ['title' => Yii::t('yii', 'Редактировать'),]
                        );
                    }
                ],
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
            'before' => Html::a('<i class="glyphicon glyphicon-plus"></i> Добавить', ['create'], ['class' => 'btn btn-success']),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info']),
            'showFooter' => false
        ],
    ]);
    Pjax::end(); ?>

</div>
