<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use yii\widgets\Pjax;
use backend\models\Comment;
use kartik\widgets\DatePicker;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\CommentSearch $searchModel
 */

$this->title = 'Комментарии';
$this->params['breadcrumbs'][] = $this->title;

$this->registerJsFile(
    '/js/manage-comments.js',
    ['position' => \yii\web\View::POS_END, 'depends' => 'yii\web\JqueryAsset']
);
?>
<div class="comment-index">


    <?php Pjax::begin();
    echo GridView::widget([
        'resizableColumns' => false,
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'hAlign' => 'center',
                'width' => '40px'
            ],
            [
                'attribute' => 'created_at',
                'hAlign' => 'center',
                'width' => '250px',
                'filter' => DatePicker::widget([
                    'model' => $searchModel,
                    'attribute' => 'created_at_filter',
                    'pluginOptions' => [
                        'autoclose' => true,
                    ]
                ]),
            ],
            [
                'attribute' => 'article_text',
                'format' => 'raw',
                'value' => static function (Comment $comment) {
                    return Html::a('<i class="glyphicon glyphicon-stats text-success"></i>', \yii\helpers\Url::to([
                            '/statistics/news',
                            'ByNewsSearch[id]' => $comment->article_id,
                            'ByNewsSearch[dateInterval]' => $comment->article->created_at->toDateString() . ' - ' . $comment->article->created_at->toDateString()
                        ]), ['title' => 'Статистика', 'target' => '_blank', 'data-pjax' => 0]) . ' ' .
                        Html::a(\yii\helpers\StringHelper::truncate($comment->article->title, 100, '...'),
                            $comment->article->url,
                            ['target' => '_blank']
                        );
                },
            ],
            [
                'attribute' => 'user_id',
                'format' => 'raw',
                'value' => function (Comment $comment) {
                    return Html::a(
                        $comment->user->backendName,
                        Url::to(['/users/index', 'UserSearch[id]' => $comment->user_id]),
                        [
                            'target' => '_blank',
                            'data-pjax' => '0'
                        ]);
                },
                'width' => '100px',
            ],
            [
                'attribute' => 'country',
                'label' => 'Страна',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'content' => function (Comment $comment) {
                    if (!$comment->country) {
                        return '&mdash;';
                    }

                    return Html::img(
                        Yii::getAlias('@backendBaseUrl') . '/img/flags/' . strtolower($comment->country) . '.png',
                        [
                            'title' => $comment->country,
                            'alt' => $comment->country
                        ]);
                }
            ],
            [
                'attribute' => 'rating',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'width' => '60px',
                'filter' => false,
                'content' => static function (Comment $comment) {
                    if (!$comment->rating) {
                        return '0';
                    }

                    return Html::a(
                        $comment->rating,
                        'javascript:void(0);',
                        [
                            'style' => 'border-bottom: 1px dashed;',
                            'title' => 'Плюсов: ' . count($comment->ratingPluses) . '<br />Минусов: ' . count($comment->ratingMinuses)
                        ]
                    );
                }
            ],
            [
                'label' => 'Ответы',
                'attribute' => 'answers_count',
                'format' => 'raw',
                'value' => function (Comment $data) {
                    return $data->answers_count ? Html::a($data->answers_count,
                        Url::to(['comments/index', 'parent_comment_id' => $data->id])
                    ) : $data->answers_count;
                },
                'hAlign' => 'center',
                'width' => '60px',
                'filter' => [1 => 'Есть', 0 => 'Нет'],
            ],
            [
                'attribute' => 'text',
                'format' => 'raw',
                'value' => static function (Comment $data) {
                    return Html::tag('div', $data->text, [
                            'data-comment-original-text' => $data->id
                        ]) .
                        Html::tag('div', $data->text, [
                            'data-comment-translated-text' => $data->id,
                            'style' => 'display: none;'
                        ]) .
                        Html::a('Перевести', 'javascript:void(0);', [
                            'onclick' => 'comments.translate(' . $data->id . ');',
                            'data-comment-translate-button' => $data->id
                        ]);
                },
            ],
            [
                'attribute' => 'enabled',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => [1 => 'Да', 0 => 'Нет'],
                'format' => 'raw',
                'value' => function (Comment $data) {
                    $classes = $data->enabled ? '-check text-success' : '-remove text-danger';
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>';
                },
                'width' => '80px'
            ],
            [
                'class' => 'kartik\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Yii::$app->urlManager->createUrl(['comments/process', 'id' => $model->id]),
                            ['title' => 'Редактировать', 'data-pjax' => 0]
                        );
                    },
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
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info', 'data-pjax' => 0]),
            'showFooter' => false
        ],
    ]);
    Pjax::end(); ?>

</div>
