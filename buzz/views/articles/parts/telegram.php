<?php
/**
 * @var string $value
 */
?>
<?php if (preg_match('#^https://t\.me/([^\?$]+)#i', $value, $m)): ?>
    <div class="block">
        <script async src="https://telegram.org/js/telegram-widget.js?19" data-telegram-post="<?= $m[1] ?>" data-width="100%"></script>
    </div>
<?php endif ?>