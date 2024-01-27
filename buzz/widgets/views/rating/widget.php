<?php
/**
 * @var \common\contracts\RateableEntity $entity
 * @var string $entityType
 */
if (!$entity->getRatingValue()) {
    $class = '';
}
elseif ($entity->getRatingValue() > 0) {
    $class = 'is-positive';
}
else {
    $class = 'is-negative';
}
?>

<div class="count" data-rating-widget="<?= $entityType ?>" data-id="<?= $entity->getId() ?>">
    <button type="button" data-rating-positive class="count-plus<?= $entity->currentUserRating[0] && $entity->currentUserRating[0]->getRatingValue() > 0 ? ' is-active' : '' ?>"></button>
    <span class="<?= $class ?>" data-rating-value><?= $entity->getRatingValue() ?></span>
    <button type="button" data-rating-negative class="count-minus<?= $entity->currentUserRating[0] && $entity->currentUserRating[0]->getRatingValue() < 0 ? ' is-active' : '' ?>"></button>
</div>