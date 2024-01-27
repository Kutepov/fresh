<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use yii\widgets\Pjax;
use backend\models\User;
use kartik\widgets\DatePicker;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var backend\models\search\UserSearch $searchModel
 */

$this->title = 'Пользователи';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-index">
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
                'width' => '250px',
                'value' => function (User $data) {
                    return $data->created_at->format('d.m.Y H:i');
                },
                'filter' => DatePicker::widget([
                    'model' => $searchModel,
                    'attribute' => 'created_at_filter',
                    'pluginOptions' => [
                        'autoclose' => true,
                    ]
                ]),
                'hAlign' => 'center',
            ],
            [
                'attribute' => 'email',
                'format' => 'email',
            ],
            'name',
            [
                'mergeHeader' => true,
                'attribute' => 'comments',
                'contentOptions' => ['style' => 'text-align: center;'],
                'format' => 'raw',
                'value' => static function (User $user) {
                    if (!count($user->comments)) {
                        return 0;
                    }

                    return Html::a(
                        count($user->comments),
                        ['comments/index', 'user_id' => $user->id],
                        [
                            'target' => '_blank',
                            'data-pjax' => 0
                        ]
                    );
                },
                'hAlign' => 'center',
                'width' => '50px'
            ],
            [
                'attribute' => 'socials',
                'format' => 'raw',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'value' => static function (User $user) {
                    if (!count($user->oauthAccounts)) {
                        return '&mdash;';
                    }
                    $result = [];
                    foreach ($user->oauthAccounts as $account) {
                        $result[] = Html::img(Yii::getAlias('@backendBaseUrl') . '/oauth/' . $account->source . '.png', ['width' => '20px', 'height' => '20px']);
                    }
                    return implode(' ', $result);
                },
            ],
            [
                'attribute' => 'country_code',
                'hAlign' => 'center',
                'mergeHeader' => true,
                'content' => function (User $user) {
                    if (!$user->geo) {
                        return '&mdash;';
                    }

                    return Html::img(
                        Yii::getAlias('@backendBaseUrl') . '/img/flags/' . strtolower($user->country_code) . '.png',
                        [
                            'title' => $user->country_code,
                            'alt' => $user->country_code
                        ]);
                }
            ],
            [
                'attribute' => 'platform',
                'hAlign' => 'center',
                'content' => static function (User $user) {
                    if (!$user->platform) {
                        return '&mdash;';
                    }

                    if ($user->isIos) {
                        return 'iOS';
                    }

                    return 'Android';
                },
                'filter' => User::PLATFORMS_FOR_DROPDOWN
            ],
            [
                'attribute' => 'status',
                'contentOptions' => ['style' => 'text-align: center;'],
                'filter' => User::STATUSES_FOR_DROPDOWN,
                'format' => 'raw',
                'value' => function (User $data) {
                    switch ($data->status) {
                        case 0:
                            $classes = '-remove text-danger';
                            break;
                        case 1:
                            $classes = '-check text-success';
                            break;
                        case -1:
                            $classes = '-trash text-danger';
                            break;
                    }
                    return '<span class="glyphicon glyphicon' . $classes . '"></span>';
                },
                'width' => '100px'
            ],
            [
                'class' => 'kartik\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'update' => function ($url, $model) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Yii::$app->urlManager->createUrl(['users/process', 'id' => $model->id]),
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
            'before' => Html::a('<i class="glyphicon glyphicon-plus"></i> Добавить', ['process'], ['class' => 'btn btn-success', 'data-pjax' => 0]),
            'after' => Html::a('<i class="glyphicon glyphicon-repeat"></i> Сбросить', ['index'], ['class' => 'btn btn-info']),
            'showFooter' => false
        ],
    ]);
    Pjax::end(); ?>

</div>
