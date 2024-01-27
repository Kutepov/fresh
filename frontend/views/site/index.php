<section class="intro">
    <div class="container">
        <div class="intro-box">
            <h1><?= Yii::t('app', 'Your Own News Feed') ?></h1>
            <p><?= Yii::t('app', 'fresh —  the most convenient way to read news about what happened in your country and around the world') ?></p>
            <ul class="intro-app">
                <li>
                    <a href="https://apps.apple.com/app/apple-store/id1503209593?pt=119070971&ct=freshbuzz&mt=8">
                        <img src="img/app-ios-<?= Yii::$app->language ?>.svg" alt="">
                    </a>
                </li>
                <li>
                    <a href="https://play.google.com/store/apps/details?id=com.freshnews.fresh">
                        <img src="img/app-android-<?= Yii::$app->language ?>.svg" alt="">
                    </a>
                </li>
            </ul>
        </div>
        <div class="intro-phone">
            <div class="intro-phone-logo"></div>
        </div>
    </div>
</section>
<section class="about">
    <div class="container">
        <h2><?= Yii::t('app', 'All News in One Application')?></h2>
        <p> <?= Yii::t('app', 'Easily customize your personal feed and conveniently navigate the main topics of the latest news!') ?></p>
        <ul class="about-list">
            <li>
                <div class="about-phone">
                    <img src="/pic/about-<?= Yii::$app->language ?>-1.png" srcset="/pic/about-<?= Yii::$app->language ?>-1.png 1x, /pic/about-<?= Yii::$app->language ?>-1@2x.png 2x" alt="">
                </div>
                <div class="about-phone-text">
                    <span><?= Yii::t('app', 'Source selection') ?></span>
                    <p><?= Yii::t('app', 'Create your own news feed from your favorite and trusted news portals') ?></p>
                </div>
            </li>
            <li>
                <div class="about-phone">
                    <img src="/pic/about-<?= Yii::$app->language ?>-2.png" srcset="/pic/about-<?= Yii::$app->language ?>-2.png 1x, /pic/about-<?= Yii::$app->language ?>-2@2x.png 2x" alt="">
                </div>
                <div class="about-phone-text">
                    <span><?= Yii::t('app', 'Bookmarks') ?></span>
                    <p><?= Yii::t('app', 'You can save useful tips and interesting articles for read later')?></p>
                </div>
            </li>
            <li>
                <div class="about-phone">
                    <img src="/pic/about-<?= Yii::$app->language ?>-3.png" srcset="/pic/about-<?= Yii::$app->language ?>-3.png 1x, /pic/about-<?= Yii::$app->language ?>-3@2x.png 2x" alt="">
                </div>
                <div class="about-phone-text">
                    <span><?= Yii::t('app', 'News') ?></span>
                    <p><?= Yii::t('app', 'Categorization will help you quickly find what you need')?></p>
                </div>
            </li>
        </ul>
    </div>
</section>
<section class="advantages">
    <div class="container">
        <div class="advantages-box">
            <h2> <?= Yii::t('app', 'The <span>Latest</span> News Is Always at Hand!')?></h2>
            <ul class="advantages-list">
                <li class="advantages-new">
                        <span>
                            <span> <?= Yii::t('app', 'Only fresh news') ?></span>
                        </span>
                    <p><?= Yii::t('app', 'Constantly updating the news will help you always keep abreast of important events in the world') ?></p>
                </li>
                <li class="advantages-source">
                        <span>
                            <span>120+ <?= Yii::t('app', 'sources') ?></span>
                        </span>
                    <p><?= Yii::t('app', 'Customize the news feed according to your interests. Choose only your favorite sources and topics') ?></p>
                </li>
                <li class="advantages-design">
                        <span>
                            <span><?= Yii::t('app', 'Laconic design') ?></span>
                        </span>
                    <p><?= Yii::t('app', 'fresh — this is a simple free app that helps you organize your news feed') ?></p>
                </li>
            </ul>
        </div>
        <div class="advantages-phone">
            <img src="/pic/advantages-<?= Yii::$app->language ?>.png" srcset="/pic/advantages-<?= Yii::$app->language ?>.png 1x, /pic/advantages-<?= Yii::$app->language ?>@2x.png 2x" alt="">
        </div>
    </div>
</section>
<section class="app">
    <div class="container">
        <div class="app-box">
            <h2><?= Yii::t('app', 'Discover the Best News App!') ?></h2>
            <ul class="app-list">
                <li class="app-active">
                    <a href="https://apps.apple.com/app/apple-store/id1503209593?pt=119070971&ct=freshbuzz&mt=8">
                        <img src="/img/app-ios-<?= Yii::$app->language ?>.svg" alt="">
                    </a>
                </li>
                <li>
                    <a href="https://play.google.com/store/apps/details?id=com.freshnews.fresh">
                        <img src="/img/app-android-<?= Yii::$app->language ?>.svg" alt="">
                    </a>
                </li>
            </ul>
        </div>
    </div>
</section>