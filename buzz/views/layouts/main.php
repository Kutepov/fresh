<?php
/**
 * @var \Detection\MobileDetect $deviceDetector
 */

use buzz\assets\AppAsset;
use buzz\widgets\HrefLang;
use yii\helpers\Html;
use yii\helpers\Url;

AppAsset::register($this);
$this->registerLinkTag(['rel' => 'canonical', 'href' => rtrim($this->params['canonical'] ?: Url::canonical(), '/')]);
?>

<?php $this->beginPage() ?>
    <!DOCTYPE html>
    <html lang="<?= str_replace('_', '-', Yii::$app->language) ?>">
    <head>
        <meta charset="<?= Yii::$app->charset ?>">
        <?php if ($this->params['needNoindexFollow']): ?>
        <meta name="robots" content="noindex,follow">
        <?php elseif ($this->params['needIndexFollow']): ?>
            <meta name="robots" content="index,follow">
        <?php elseif ($this->params['noIndexNoFollow']): ?>
            <meta name="robots" content="noindex,nofollow">
        <?php endif; ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
        <link rel="apple-touch-icon" sizes="144x144" href="/favicon/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
        <link rel="manifest" href="/favicon/site.webmanifest">
        <link rel="shortcut icon" href="/favicon/favicon.ico">
        <meta name="msapplication-config" content="/favicon/browserconfig.xml">
        <?php $this->registerCsrfMetaTags() ?>
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap">
        <?= HrefLang::widget() ?>
    </head>
    <body>
    <?php $this->beginBody() ?>

    <header>
        <div class="container">
            <div class="header-box">
                <a href="<?= Url::to(['articles/index']) ?>" class="logo"></a>
                <nav class="nav">
                    <?= \buzz\widgets\Menu::widget() ?>
                </nav>
                <ul class="header-app">
                    <li>
                        <a rel="nofollow" target="_blank"
                           href="https://play.google.com/store/apps/details?id=com.freshnews.fresh&referrer=utm_source%3Dfreshbuzz%26utm_medium%3Dtop">
                            <img src="/img/app/app-android-<?= CURRENT_LANGUAGE ?>.svg" alt="">
                        </a>
                    </li>
                    <li>
                        <a rel="nofollow" target="_blank" href="<?= Yii::$app->params['iosUrl'] ?>">
                            <img src="/img/app/app-ios-<?= CURRENT_LANGUAGE ?>.svg" alt="">
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <?= $content ?>
    <footer>
        <div class="container">
            <div class="footer-panel">
                <ul class="footer-links">
                    <li>
                    </li>
                </ul>
                <div class="footer-contact"><a href="javascript:void(0);"
                                               data-popup-url="<?= Url::to(['site/feedback']) ?>"><?= \t('Связаться с нами') ?></a></div>
            </div>
        </div>
    </footer>
    <?php if ($this->deviceDetector->isMobile()): ?>
        <div class="app">
            <div class="app-box">
                <div class="app-logo">
                    <img src="/img/app-logo.svg" alt="">
                </div>
                <div class="app-text"><?= \t('Читайте новости в приложении. Экономьте трафик!') ?></div>
                <?php if ($this->deviceDetector->isIos()): ?>
                    <a target="_blank" data-ios-deeplink rel="nofollow" href="<?= Yii::$app->params['iosUrl'] ?>" class="button button-green"><?= \t('Установить') ?></a>
                <?php else: ?>
                    <a target="_blank" rel="nofollow" data-android-deeplink
                       href="https://play.google.com/store/apps/details?id=com.freshnews.fresh&referrer=utm_source%3Dfreshbuzz%26utm_medium%3Dnews_popup"
                       class="button button-green"><?= \t('Установить') ?></a>
                <?php endif ?>
            </div>
        </div>
    <?php endif ?>
    <?php $this->endBody() ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-3FSLMWT6WX"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());

        gtag('config', 'G-3FSLMWT6WX');
    </script>
    </body>
    </html>
<?php $this->endPage() ?>