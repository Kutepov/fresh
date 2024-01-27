<?php

use backend\models\search\statistics\PushNotifications;
use common\models\aggregate\HistoricalPushNotifications;
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
 * @var string $pushesByCountry
 */

$this->title = 'Статистика по PUSH-уведомлениям';
$this->params['breadcrumbs'][] = ['label' => 'Статистика', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'PUSH-уведомления';
?>
<div class="statistics-news">
    <?php Pjax::begin();
    echo GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'columns' => [
            [
                'attribute' => 'pushedAt',
                'format' => 'dateTime',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'previewImage',
                'format' => 'raw',
                'hAlign' => 'center',
                'content' => static function (HistoricalPushNotifications $model) {
                    if ($model->previewImage) {
                        $url = 'https://stx.myfresh.app/' . $model->previewImage;
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
                'content' => static function (HistoricalPushNotifications $data) {
                    return Html::a('<i class="glyphicon glyphicon-stats text-success"></i>', \yii\helpers\Url::to(['/statistics/news', 'ByNewsSearch[id]' => $data->articleId]), ['title' => 'Статистика', 'target' => '_blank', 'data-pjax' => 0]) . ' ' . Html::a($data->title ?: $data->article_id, $data->url, ['target' => '_blank', 'rel' => 'nofollow']);
                }
            ],
            [
                'attribute' => 'sends',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'views',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'clicks',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'ctr',
                'hAlign' => 'center'
            ],
            [
                'attribute' => 'country',
                'format' => 'raw',
                'hAlign' => 'center',
                'value' => static function (HistoricalPushNotifications $data) {
                    return Html::img(Yii::getAlias('@frontendBaseUrl') . '/img/' . $data->flag, ['width' => 40, 'height' => 20, 'title' => $data->countryCode]);
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
            'before' => $this->render('_pushNotificationsSearch', ['model' => $searchModel, 'sources' => $sources, 'languages' => $languages]) . '<hr />' . $pushesByCountry,
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],
    ]);
    Pjax::end(); ?>
</div>
