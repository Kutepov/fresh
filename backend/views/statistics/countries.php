<?php

use yii\widgets\Pjax;
use kartik\grid\GridView;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\statistics\CommonSearch $searchModel
 */

$this->title = 'Статистика по странам';
$this->params['breadcrumbs'][] = ['label' => 'Статистика', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Страны';
?>
<div class="statistics-news">
    <?php Pjax::begin();
    echo GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'layout' => '{items}\n{pager}',
        'toolbar' => false,
        'columns' => [
            [
                'attribute' => 'name',
                'label' => 'Страна'
            ],
            [
                'attribute' => 'ctr',
                'label' => 'CTR',
                'hAlign' => 'center',
                'content' => static function ($model) {
                    if (!$model['ctr']) {
                        return '&mdash;';
                    }

                    return $model['ctr'] . '%';
                }
            ],
            [
                'attribute' => 'clicks',
                'label' => 'Клики',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'all_users',
                'label' => 'Все пользователи',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'new_users',
                'label' => 'Новые пользователи',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'views',
                'label' => 'Просмотры',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'comments',
                'label' => 'Комментарии',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'rating_articles',
                'label' => 'Лайки новостей',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'rating_comments',
                'label' => 'Лайки комментариев',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'shares_count',
                'label' => 'Шеринг',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'registrations',
                'label' => 'Регистрации',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'articles',
                'label' => 'Новости',
                'hAlign' => 'center'
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
            'before' => $this->render('_countriesSearch', ['model' => $searchModel]),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],

    ]);
    Pjax::end(); ?>
</div>
