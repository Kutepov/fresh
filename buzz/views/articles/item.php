<?php
/**
 * @var \common\models\Article $article
 * @var boolean $top
 */

use common\models\Source;
use yii\helpers\Url;

?>

<article class="news">
    <div class="news-main">
        <h3 class="news-header">
            <a href="<?= Url::to($article->route) ?>"><?= $article->title ?></a>
        </h3>
        <ul class="news-info">
            <li><?= $article->publicationDateLabel ?></li>
            <li class="news-info-source<?= $top ? ' news-info-hot' : '' ?>">
                <?= $article->source->getDomain() ?>
            </li>
            <li class="news-info-rating">
                <?= \buzz\widgets\Rating::widget(['entity' => $article]) ?>
            </li>
            <li class="news-info-comments-box">
                <a href="<?= Url::to($article->route) ?>#comments" class="news-info-comments"><?= $article->comments_count ?></a>
            </li>
        </ul>
        <?php /* <a href="#" class="news-app"><?= \t('Открыть в приложении') ?></a> */ ?>
    </div>
    <?php if ($article->preview_image): ?>
        <a href="<?= Url::to($article->route) ?>" class="news-image<?= in_array($article->source->type, [Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW], true) ? ' news-video' : '' ?>">
            <img src="<?= $article->getScaledPreviewImage(110) ?>"
                 srcset="<?= $article->getScaledPreviewImage(110) ?> 1x, <?= $article->getScaledPreviewImage(110, 2) ?> 2x" alt="<?= $article->title ?>">
        </a>
    <?php endif; ?>
</article>
