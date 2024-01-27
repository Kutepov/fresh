<?php

/* @var $this \yii\web\View */

/* @var $content string */

use backend\assets\AppAsset;
use yii\helpers\Html;
use kartik\nav\NavX;
use yii\bootstrap\NavBar;
use yii\widgets\Breadcrumbs;
use common\widgets\Alert;
use backend\assets\TooltipsterAsset;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<div class="wrap">
    <?php
    NavBar::begin([
        'brandLabel' => Yii::$app->name,
        'brandUrl' => Yii::$app->homeUrl,
        'options' => [
            'class' => 'navbar-inverse navbar-fixed-top',
            'id' => 'main-menu',
        ],
    ]);
    $menuItems = [
        [
            'label' => 'Статистика',
            'items' => [
                [
                    'label' => 'По дням',
                    'url' => ['/statistics/index'],
                ],
                [
                    'label' => 'По новостям',
                    'url' => ['/statistics/news'],
                ],
                [
                    'label' => 'По категориям',
                    'url' => ['/statistics/categories'],
                ],
                [
                    'label' => 'По странам',
                    'url' => ['/statistics/countries'],
                ],
                [
                    'label' => 'По PUSH-уведомлениям',
                    'url' => ['/statistics/push-notifications'],
                ],
            ],
        ],
        [
            'label' => 'Новости',
            'items' => [
                [
                    'label' => 'Категории новостей',
                    'url' => ['/categories/index']
                ],
                [
                    'label' => 'Источники',
                    'items' => [
                        [
                            'label' => 'Источники',
                            'url' => ['/sources/index'],
                        ],
                        [
                            'label' => 'Категории источников',
                            'url' => ['/sources/categories']
                        ],
                    ]
                ]
            ]
        ],
        [
            'label' => 'Каталог',
            'items' => [
                [
                    'label' => 'История поиска',
                    'url' => ['/catalog-search-history/index']
                ]
            ]
        ],
        [
            'label' => 'Настройки',
            'items' => [
                [
                    'label' => 'Страны',
                    'url' => ['/countries/index']
                ],
                [
                    'label' => 'Языки',
                    'url' => ['/languages/index']
                ],
                [
                    'label' => 'Топ новостей',
                    'url' => ['/settings/top']
                ],
                [
                    'label' => 'Постинг в телеграм',
                    'url' => ['/settings/telegram']
                ],
                [
                    'label' => 'PUSH-уведомления',
                    'url' => ['/settings/push-notifications']
                ],
                [
                    'label' => 'Поиск',
                    'url' => ['/settings/search']
                ],
            ]
        ],
        [
            'label' => 'Реклама',
            'url' => ['/ad-banners/index'],
        ],
        [
            'label' => 'Пользователи',
            'url' => ['/users'],
        ],
        [
            'label' => 'Комментарии',
            'url' => ['/comments'],
        ]
    ];

    if (Yii::$app->user->isGuest) {
        $menuItems[] = ['label' => 'Login', 'url' => ['/site/login']];
    } else {
        $menuItems[] = '<li>'
            . Html::beginForm(['/site/logout'], 'post')
            . Html::submitButton(
                'Выйти (' . Yii::$app->user->identity->email . ')',
                ['class' => 'btn btn-link logout']
            )
            . Html::endForm()
            . '</li>';
    }
    echo NavX::widget([
        'options' => ['class' => 'navbar-nav navbar-right'],
        'items' => $menuItems,
    ]);
    NavBar::end();
    ?>

    <div class="container">
        <?= Breadcrumbs::widget([
            'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
        ]) ?>
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p class="pull-left">&copy; <?= Html::encode(Yii::$app->name) ?> <?= date('Y') ?></p>

        <p class="pull-right"><?= Yii::powered() ?></p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
