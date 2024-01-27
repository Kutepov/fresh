<?php
/**
 * @var $dataProvider \yii\data\ActiveDataProvider
 * @var $searchModel CatalogSearchHistorySearch
 */

use kartik\grid\GridView;
use backend\models\search\CatalogSearchHistorySearch;
use yii\widgets\Pjax;

$this->title = 'История поиска в каталоге';
$this->params['breadcrumbs'][] = $this->title;
?>

<div>
    <?php Pjax::begin() ?>
    <?=
    GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'query',
                'width' => '600px',
                'content' => static function (CatalogSearchHistorySearch $model) {
                    return $model->formattedQuery;
                }
            ],
            [
                'attribute' => 'created_at',
                'width' => '150px',
                'hAlign' => 'center',
                'filter' => false,
                'mergeHeader' => true,
                'content' => static function (CatalogSearchHistorySearch $model) {
                    return $model->created_at->format('d.m.Y H:i');
                }
            ],
            [
                'attribute' => 'country_code',
                'width' => '50px',
                'hAlign' => 'center',
                'filter' => \backend\models\Country::getForDropdown(),
                'content' => function (CatalogSearchHistorySearch $model) {
                    if ($model->app && $model->app->countryModel) {
                       return \yii\helpers\Html::img($model->app->countryModel->getAbsoluteUrlToImage(), ['height' => 20, 'width' => 40, 'alt' => $model->app->countryModel->name]);
                    }

                    return '&mdash;';
                }
            ],
            [
                'attribute' => 'type',
                'width' => '150px',
                'hAlign' => 'center',
                'filter' => \common\models\CatalogSearchHistory::AVAILABLE_TYPES,
                'content' => static function (CatalogSearchHistorySearch $model) {
                    return $model->typeLabel;
                }
            ],
            [
                'attribute' => 'section',
                'width' => '150px',
                'hAlign' => 'center',
                'filter' => \common\models\CatalogSearchHistory::AVAILABLE_SECTIONS,
                'content' => static function (CatalogSearchHistorySearch $model) {
                    return $model->sectionLabel;
                }
            ]
        ],
    ])
    ?>
    <?php Pjax::end() ?>
</div>
