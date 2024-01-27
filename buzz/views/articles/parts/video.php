<?php
/**
 * @var string $value
 * @var \common\models\Article $article
 */

use common\models\Source;

?>
<div class="block expand">
    <div class="video">
        <?php if (preg_match('#(?:https?:\\/\\/)?(?:www\\.)?youtu(?:be)?\\.(?:com|be)(?:\\/watch\\/?\\?v=|\\/embed\\/|\\/shorts\\/|\\/)([\\w\\-]+)#i', $value, $m)): ?>
            <?php \buzz\assets\YoutubeAsset::register($this) ?>
            <?php $this->registerJs('YouTube.enqueueVideo("' . $m[1] . '", ' . (in_array($article->source->type,  [Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW ], true) ? 'true' : 'false') . ');') ?>
            <div id="youtube-player-<?= $m[1] ?>"></div>
        <?php else: ?>
            <iframe width="100%" src="<?= $value ?>"></iframe>
        <?php endif; ?>
    </div>
</div>
