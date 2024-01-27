<?php

use buzz\widgets\ActiveForm;
use buzz\widgets\Pjax;

/**
 * @var \buzz\models\forms\Feedback $model
 */
?>
<div class="popup" data-popup>
    <?php Pjax::begin([
        'timeout' => false,
        'id' => 'feedback-pjax',
        'formSelector' => 'form',
        'options' => ['class' => 'popup-box']
    ]) ?>
    <button type="button" class="popup-close" data-popup-close></button>
    <div class="popup-header"><?= \t('Связаться с нами') ?></div>
    <p><?= \t('Появились вопросы? Мы ответим на них в самый короткий срок') ?></p>
    <?php $form = ActiveForm::begin([
        'action' => ['site/feedback'],
        'options' => [
            'data-pjax' => 1,
        ],
        'fieldConfig' => [
            'template' => ActiveForm::NO_LABELS,
        ],
    ]) ?>
    <?= $form->field($model, 'name')->textInput(['placeholder' => $model->attributeLabels()['name']]) ?>
    <?= $form->field($model, 'email')->textInput(['placeholder' => $model->attributeLabels()['email']]) ?>
    <?= $form->field($model, 'message')->textarea(['class' => 'textfield', 'placeholder' => $model->attributeLabels()['message']]) ?>
    <div class="form-button">
        <button type="submit" class="button button-block button-green button-big"><?= \t('Отправить') ?></button>
    </div>
    <?php ActiveForm::end() ?>
    <?php Pjax::end() ?>
</div>