<?php
/**
 * @var \common\models\Comment $comment
 */
?>
<div class="comment-box">
    <div class="comment-main">
        <div class="comment-info">
            <img src="<?= $comment->user->avatarUrl ?>" class="comment-avatar">
            <div class="comment-author"><?= $comment->user->name ?></div>
            <div class="comment-date"><?= $comment->publicationDateLabel ?></div>
        </div>
        <div class="comment-text">
            <p><?= nl2br($comment->text) ?></p>
        </div>
    </div>
    <div class="comment-rating">
        <?= \buzz\widgets\Rating::widget(['entity' => $comment]) ?>
    </div>
</div>