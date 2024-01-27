<?php
/**
 * @var \common\models\Comment $comment
 */
?>
<div class="comment <?= $comment->lastAnswers ? 'is-opened' : '' ?>">
    <?= $this->render('box', ['comment' => $comment]) ?>
    <?php if ($comment->lastAnswers): ?>
        <div class="comment-thread" data-comment-answers="<?= $comment->id ?>">
            <?php foreach ($comment->lastAnswers as $answer): ?>
                <?= $this->render('item', ['comment' => $answer]) ?>
            <?php endforeach; ?>
        </div>
        <?php if (count($comment->lastAnswers) < $comment->answers_count): ?>
            <div class="comment-expand" data-comment-expand="<?= $comment->id ?>" data-article-id="<?= $comment->article_id ?>">
                <button>
                    <span>
                        <?= \t('Показать {count, plural, one{# ответ} few{# ответа} other{# ответов}}', [
                            'count' => $comment->answers_count - count($comment->lastAnswers)
                        ]) ?>
                    </span>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
