<?php
/**
 * @var array $value
 * @var \common\models\Article $article
 */
?>
<div class="block">
    <img src="https://stx.myfresh.app/<?= $value['image'] ?>" alt="<?= $value['caption'] ?>">
    <div class="image-title">
        <p><?= $value['caption'] ?></p>
    </div>
</div>