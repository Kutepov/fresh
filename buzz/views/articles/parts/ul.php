<?php
/**
 * @var array $value
 * @var \common\models\Article $article
 */
?>
<div class="block">
    <ul>
        <?php foreach ($value as $item): ?>
            <li><?= $item ?></li>
        <?php endforeach; ?>
    </ul>
</div>