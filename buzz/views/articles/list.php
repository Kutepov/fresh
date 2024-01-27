<?php
/**
 * @var \common\models\Article[] $articles
 * @var int $articlesCount
 * @var \common\models\Category|null $category
 * @var string|null $h1
 * @var \common\models\Country $country
 */

use common\components\helpers\SEO;

\buzz\assets\InfiniteScrollAssset::register($this);
if ($category) {
    SEO::metaTagsTpl('category/index', [
        'title' => mb_strtolower($category->title),
        'country' => $country->name
    ]);
} else {
    SEO::metaTagsTpl('main/index', [
        'country' => $country->name
    ]);
}
?>
<div class="wrapper">
    <div class="container">
        <div class="cols">
            <?php if (!Yii::$app->request->isAjax && !($category && $this->deviceDetector->isMobile())): ?>
                <?= \buzz\widgets\TopArticles::widget() ?>
            <?php endif ?>
            <main id="articles">
                <h1 class="h1-main"><?= $h1 ?: \t('Последние новости') ?></h1>
                <?php foreach ($articles as $article): ?>
                    <?= $this->render('item', [
                        'article' => $article
                    ]) ?>
                <?php endforeach; ?>
            </main>
            <?= $this->registerJS("new InfiniteAjaxScroll('#articles', {
                item: '#articles article.news',
                next: '.next a',
                pagination: '.pagination',
                negativeMargin: 1200
            });") ?>
            <div style="display: none;">
                <?= \yii\widgets\LinkPager::widget([
                    'pagination' => new \yii\data\Pagination([
                        'totalCount' => 10000,
                        'pageSize' => 20,
                        'pageSizeParam' => false,
                        'route' => Yii::$app->requestedRoute,
                        'params' => Yii::$app->request->get() + ['createdBefore' => \Carbon\Carbon::now()->toIso8601String()]

                    ])
                ]) ?>
            </div>
        </div>
    </div>
</div>