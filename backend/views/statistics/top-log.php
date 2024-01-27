<?php
/**
 * @var array $stat
 * @var \common\models\Article $article
 */
?>
<?= \yii\helpers\Html::a($article->title, $article->url, ['target' => '_blank', 'rel' => 'nofollow']) ?><br /><br />

<table class="table table-bordered table-striped">
    <thead>
    <tr>
        <?php foreach ($stat[0] as $v): ?>
            <th><?= $v ?></th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($stat as $k => $v): if (!$k) {
        continue;
    } ?>
        <tr>
            <?php foreach ($v as $v1): ?>
                <td><?= $v1 ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
