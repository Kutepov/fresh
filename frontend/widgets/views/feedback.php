<?php
/**
 * @var $model \frontend\models\forms\Feedback
 */

use yii\widgets\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

?>

<?php $form = ActiveForm::begin([
    'action' => Url::to(['/index']),
    'options' => ['class' => 'form'],
    'validateOnBlur' => false,
    'validateOnChange' => false,
]);
?>
<fieldset>
    <?= $form->field($model, 'name')->textInput(['class' => 'textfield', 'placeholder' => $model->getAttributeLabel('name')])->label(false) ?>
</fieldset>
<fieldset>
    <?= $form->field($model, 'email')->textInput(['class' => 'textfield', 'placeholder' => $model->getAttributeLabel('email')])->label(false) ?>
</fieldset>
<fieldset>
    <?= $form->field($model, 'message')->textarea(['class' => 'textarea', 'placeholder' => $model->getAttributeLabel('message')])->label(false) ?>
</fieldset>
<span style="display: none;"> <?= $form->field($model, 'captcha')->widget(\manchenkov\yii\recaptcha\ReCaptchaWidget::class, ['action' => 'feedback', 'showBadge' => false, 'preloading' => true]) ?></span>
<div class="form-button">
    <?= Html::submitButton(Yii::t('app', 'Send'), ['class' => 'button button-block']) ?>
</div>
<?php ActiveForm::end() ?>
