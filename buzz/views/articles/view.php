<?php
/**
 * @var \common\models\Article $article
 * @var \common\models\Comment[] $comments
 * @var \common\models\Article[] $similarArticles
 */

use buzz\widgets\Rating;
use common\components\helpers\SEO;
use yii\helpers\Url;
use common\models\Source;

\buzz\assets\SharerAsset::register($this);

SEO::metaTagsTpl('articles/view', [
    'title' => $article->metaTitle,
    'description' => $article->metaDescription,
    'category' => $article->category->title
]);

if ($article->previewImageUrl) {
    $this->registerMetaTag(['name' => 'og:image', 'content' => $article->previewImageUrl]);
    $this->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image']);
    $this->registerMetaTag(['name' => 'twitter:image', 'content' => $article->previewImageUrl]);
    $this->registerMetaTag(['name' => 'twitter:title', 'content' => $article->metaTitle]);
    $this->registerMetaTag(['name' => 'twitter:description', 'content' => $article->metaDescription]);
}
?>

<div class="wrapper">
    <div class="container">
        <div class="cols cols-article">
            <?= \buzz\widgets\TopArticles::widget() ?>
            <main>
            <section class="article">
                <h1><?= $article->title ?></h1>
                <ul class="article-info">
                    <li><?= $article->publicationDateLabel ?></li>
                    <li>
                        <a target="_blank" href="<?= $article->url ?>" rel="nofollow" class="external-link"><?= $article->source->getDomain() ?></a>
                    </li>
                </ul>
                <?php foreach ($article->getBuzzBody() as $i => $item): ?>
                    <?php
                    try {
                        echo $this->render('parts/' . $item['elementName'], ['value' => $item['value'], 'article' => $article]);
                    } catch (\yii\base\ViewNotFoundException $e) {
                        continue;
                    }
                    ?>
                <?php endforeach; ?>
                <?php if (!in_array($article->source->type, [Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW], true)): ?>
                    <div class="block center">
                        <?php if ($this->deviceDetector->isMobile()): ?>
                            <?php if ($this->deviceDetector->isAndroidOS()): ?>
                                <a target="_blank" class="article-more" rel="nofollow"
                                   data-android-deeplink="article/<?= $article->id ?>"
                                   href="https://play.google.com/store/apps/details?id=com.freshnews.fresh&referrer=utm_source%3Dfreshbuzz%26utm_medium%3Dnews_read_more"><?= \t('Читать далее') ?></a>
                            <?php else: ?>
                                <a target="_blank" class="article-more" rel="nofollow" data-ios-deeplink="deep/article?id=<?= $article->id ?>&widget=small"
                                   href="<?= Yii::$app->params['iosUrl'] ?>"><?= \t('Читать далее') ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a target="_blank" class="article-more" rel="nofollow" href="<?= $article->url ?>"><?= \t('Читать далее') ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="block">
                    <div id="comments"></div>
                </div>
                <div class="block">
                    <div class="article-bottom">
                        <?= Rating::widget(['entity' => $article]) ?>
                        <div class="article-actions">
                            <button data-clipboard-text="<?= Url::to($article->route, true) ?>" type="button" class="button button-link">
                                <span>OK</span>
                            </button>
                            <div class="article-share">
                                <button type="button" class="button button-share"></button>
                                <ul class="article-share-options">
                                    <li>
                                        <a href="#" data-sharer="twitter" data-url="<?= Url::current([], true) ?>" data-title="<?= hEncode($article->title) ?>" class="tw"><?= \t('Share on {service}', ['service' => 'Twitter']) ?></a>
                                    </li>
                                    <li>
                                        <a href="#" data-sharer="linkedin" data-url="<?= Url::current([], true) ?>" data-title="<?= hEncode($article->title) ?>" class="in"><?= \t('Share on {service}', ['service' => 'LinkedIn']) ?></a>
                                    </li>
                                    <li>
                                        <a href="#" data-sharer="facebook" data-url="<?= Url::current([], true) ?>" data-title="<?= hEncode($article->title) ?>" class="fb"><?= \t('Share on {service}', ['service' => 'Facebook']) ?></a>
                                    </li>
                                    <li>
                                        <a href="#" data-sharer="reddit" data-url="<?= Url::current([], true) ?>" data-title="<?= hEncode($article->title) ?>" class="rd"><?= \t('Share on {service}', ['service' => 'Reddit']) ?></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
            </section>
            <section class="comments">
                <?php if (!count($comments)): ?>
                    <h3><?= \t('Комментариев нет') ?></h3>
                <?php else: ?>
                    <h3>
                        <?= \t('{count, plural, one{# комментарий} few{# комментария} other{# комментариев}}', [
                            'count' => $article->comments_count
                        ]) ?>
                    </h3>
                <?php endif ?>
                <?php foreach ($comments as $comment): ?>
                    <?= $this->render('../comments/item', ['comment' => $comment]) ?>
                <?php endforeach; ?>
            </section>
            <?php if ($similarArticles): ?>
                <section>
                    <h2 class="tablet-hidden"><?= \t('Читайте также') ?></h2>
                    <?php foreach ($similarArticles as $similarArticle): ?>
                        <?= $this->render('item', ['article' => $similarArticle]) ?>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
                </main>
        </div>
    </div>
</div>