<?php
/**
 * @var array $value
 * @var \common\models\Article $article
 */
?>
<div class="block">
    <div class="carousel">
        <div class="swiper-container">
            <div class="swiper-wrapper">
                <?php foreach ($value as $image): ?>
                    <div class="swiper-slide">
                        <img src="https://stx.myfresh.app/<?= $image ?>" alt="<?= $article->title ?>">
                    </div>

                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="swiper-prev"></button>
        <button type="button" class="swiper-next"></button>
    </div>
    <div class="carousel-nav"></div>
</div>