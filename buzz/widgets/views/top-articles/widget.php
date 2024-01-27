<?php
/**
 * @var \common\models\Article[] $articles
 */
?>
<aside>
    <h2><?= \t('Горячие новости') ?></h2>
    <?php foreach ($articles as $article): ?>
        <?= $this->render('@app/views/articles/item', [
            'article' => $article,
            'top' => true
        ]) ?>
    <?php endforeach; ?>
</aside>