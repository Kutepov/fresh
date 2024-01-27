<?php

use yii\helpers\Html;
use kartik\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\search\LanguageSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Языки';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="language-index">
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
            [
                'attribute' => 'short_name',
                'hAlign' => 'center',
                'width' => '120px'
            ],
            'name',
            [
                'class' => \kartik\grid\ActionColumn::class,
                'hAlign' => 'center',
                'mergeHeader' => true,
                'template' => '{update} {delete}'
            ],
        ],
    ]); ?>


</div>
