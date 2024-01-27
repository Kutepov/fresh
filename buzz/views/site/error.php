<?php

use buzz\widgets\TopArticles;

?>
<div class="wrapper">
    <div class="container">
        <div class="cols">
            <?= TopArticles::widget() ?>
            <main class="empty">
                <img src="/img/empty.svg" alt="">
                <p><?= \t('Ничего не найдено...') ?></p>
            </main>
        </div>
    </div>
</div>