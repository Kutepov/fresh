<?php

use yii\widgets\Pjax;
use kartik\grid\GridView;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\statistics\CategoriesSearch $searchModel
 */

$this->title = 'Статистика по категориям';
$this->params['breadcrumbs'][] = ['label' => 'Статистика', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Категории';
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
                'attribute' => 'title',
                'label' => 'Категория',
                'hAlign' => 'right'
            ],
            [
                'attribute' => 'articlesAmount',
                'label' => 'Новости',
                'hAlign' => 'center',
                'content' => static function (\backend\models\search\statistics\CategoriesSearch $search) use ($searchModel) {
                    if ($search->articlesAmount) {
                        return $search->articlesAmount . ' (' . round($search->articlesAmount / $searchModel->totalArticlesCount * 100) . '%)';
                    }

                    return '&mdash;';
                }
            ],
            [
                'attribute' => 'clicks',
                'label' => 'Клики',
                'hAlign' => 'center',
            ],
            [
                'attribute' => 'views',
                'label' => 'Показы',
                'hAlign' => 'center',
            ],
            [
                'attribute' => 'ctr',
                'label' => 'CTR',
                'hAlign' => 'center',
                'content' => static function (\backend\models\search\statistics\CategoriesSearch $search) {
                    if (!$search->ctr) {
                        return '&mdash;';
                    }
                    return $search->ctr . '%';
                }
            ]
        ],
        'responsive' => true,
        'hover' => true,
        'condensed' => true,
        'floatHeader' => true,
        'export' => false,

        'panel' => [
            'heading' => '<h3 class="panel-title"><i class="glyphicon glyphicon-th-list"></i> ' . Html::encode($this->title) . ' </h3>',
            'type' => 'info',
            'before' => $this->render('_categoriesSearch', ['model' => $searchModel, 'languages' => $languages]),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],

    ]);
    Pjax::end(); ?>
</div>
