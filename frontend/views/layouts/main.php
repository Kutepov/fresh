<?php

/* @var $this \yii\web\View */

/* @var $content string */

use yii\helpers\Html;
use frontend\assets\AppAsset;
use yii\helpers\Url;
use \yii\widgets\ActiveForm;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <title><?= Yii::t('meta', 'fresh - Own News Feed, All News in One Place') ?></title>
    <meta charset="utf-8">
    <meta name="format-detection" content="telephone=no">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
          content="<?= Yii::t('meta', 'All world news of the day in one mobile application. Now, to keep abreast of the latest developments, you do not need to take up a lot of space on your smartphone and install a bunch of news applications') ?>">
    <link rel="alternate" href="https://myfresh.app" hreflang="en">
    <link rel="alternate" href="https://myfresh.app/ru/" hreflang="ru">
    <link rel="alternate" href="https://myfresh.app/ua/" hreflang="uk">
    <link rel="alternate" href="https://myfresh.app" hreflang="x-default">
    <link rel="apple-touch-icon" sizes="144x144" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="mask-icon" href="/favicon/safari-pinned-tab.svg" color="#2abe8f">
    <link rel="shortcut icon" href="/favicon/favicon.ico">
    <meta name="msapplication-config" content="/favicon/browserconfig.xml">
    <meta name="msapplication-TileColor" content="#2abe8f">
    <meta name="theme-color" content="#ffffff">

    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="homepage">
<?php $this->beginBody() ?>
<div class="wrapper">
    <div class="site">
        <header>
        <?php if (!isset($this->params['embed'])): ?>
            <div class="container">
                <a href="/" class="logo"></a>
                <?php if (!in_array($this->context->action->id, ['policy', 'rules'], true)): ?>
                    <ul class="lang">
                        <li>
                            <?= Yii::$app->language === 'en' ? Html::tag('span', 'EN') : Html::a('EN', '/en') ?>
                        </li>
                        <li>
                            <?= Yii::$app->language === 'ru' ? Html::tag('span', 'RU') : Html::a('RU', '/ru') ?>
                        </li>
                        <li>
                            <?= Yii::$app->language === 'ua' ? Html::tag('span', 'UA') : Html::a('UA', '/ua') ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </header>

        <?= $content ?>

        <?php if (!isset($this->params['embed'])): ?>
        <footer>
            <div class="container">
                <a href="/" class="logo"></a>
                <ul class="footer-links">
                    <li>
                        <a href="<?= Url::to(['site/policy']) ?>"><?= Yii::t('app', 'Privacy policy') ?></a>
                    </li>
                    <li>
                        <a href="<?= Url::to(['site/contact']) ?>">Contact Us</a>
                    </li>
                </ul>
            </div>
        </footer>
        <?php endif; ?>
    </div>
</div>
<div class="popup" id="contact-form">
    <div class="popup-box">
        <button type="button" class="popup-close"></button>
        <div class="popup-header"><?= Yii::t('app', 'Contact us') ?></div>
        <p><?= Yii::t('app', 'Have a question? We will answer them as soon as possible') ?></p>
        <?= \frontend\widgets\Feedback::widget() ?>
    </div>
</div>
<script src="js/scripts.js"></script>
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-165945256-1"></script>
<script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }

    gtag('js', new Date());
    gtag('config', 'UA-165945256-1');
</script>


<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
