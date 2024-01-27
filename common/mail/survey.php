<?php
/**
 * @var $name string
 * @var $country string
 * @var $message string
 */
?>

<?php if ($name): ?>
    <p><strong><?= \t('Имя') ?>:</strong> <?= $name ?></p>
<?php endif; ?>
<p><strong><?= \t('Страна') ?>:</strong> <?= $country ?></p>
<br/>
<p><?= $message ?></p>
