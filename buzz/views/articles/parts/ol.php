<?php
/**
 * @var array $value
 * @var \common\models\Article $article
 */
?>
<div class="block">
    <ol>
        <?php foreach ($value as $item): ?>
            <li><?= $item ?></li>
        <?php endforeach; ?>
    </ol>
</div>