<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use backend\models\Country;
use kartik\widgets\Select2;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $searchModel \backend\models\search\CountrySearch */

$this->title = 'Страны';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="country-index">

    <?= GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'responsive' => true,
        'hover' => true,
        'condensed' => true,
        'floatHeader' => true,
        'export' => false,
        'panel' => [
            'heading' => '<h3 class="panel-title"><i class="glyphicon glyphicon-th-list"></i> ' . Html::encode($this->title) . ' </h3>',
            'before' => Html::a('Создать', ['create'], ['class' => 'btn btn-success']),
            'type' => 'info',
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],
        'columns' => [
            [
                'attribute' => 'id',
                'hAlign' => 'center',
                'width' => '60px'
            ],
            [
                'attribute' => 'code',
                'hAlign' => 'center',
                'width' => '60px'
            ],
            'name',
            [
                'attribute' => 'timezone',
                'filter' => Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'timezone',
                        'data' => Country::getTimezonesForDropdown(),
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
            ],
            [
                'label' => 'Опрос',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'width' => '100px',
                'content' => function (Country $model) {
                    if (!$model->quality_survey) {
                        return '&mdash;';
                    }
                    else {
                        return Html::tag('span', '<strong>' . $model->quality_survey_good . '<strong>', ['class' => 'text-success']) . ' / ' .
                            Html::tag('span', '<strong>' . $model->quality_survey_bad . '<strong>', ['class' => 'text-danger']);
                    }
                }
            ],
            [
                'attribute' => 'image',
                'format' => 'raw',
                'mergeHeader' => true,
                'hAlign' => 'center',
                'value' => function ($data) {
                    /** @var $data Country */
                    return Html::img($data->getAbsoluteUrlToImage(), ['height' => 20, 'width' => 40]);
                }
            ],
            [
                'class' => \kartik\grid\ActionColumn::class,
                'hAlign' => 'center',
                'mergeHeader' => true,
                'template' => '{update} {delete}'
            ],
        ]
    ]); ?>

</div>
