<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var \backend\models\forms\SettingsForm $model
 * @var yii\widgets\ActiveForm $form
 */

$this->title = 'Настройки отправки PUSH-уведомлений';
$service = Yii::$container->get(\common\services\MultilingualService::class);
$country = Yii::$app->request->get('country', 'UA');
$this->registerJsFile('/js/settings.js', [
    'depends' => \backend\assets\AppAsset::class
]);
?>
<div class="comment-create">
    <div class="row">
        <div class="col-lg-3">
            <h3><?= Html::encode($this->title) ?> </h3>
        </div>
    </div>

    <div class="comment-form">
        <?= Html::dropDownList('country', $country, $service->getAvailableCountriesForDropDownList(), [
            'data-country-dropdown' => '/settings/push-notifications'
        ]) ?>
        <hr/>
        <?php $form = ActiveForm::begin() ?>

        <div class="row">
            <div class="col-lg-2">
                <?= $form->field($model, 'minClicksCount')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-2">
                <?= $form->field($model, 'minCtr')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-2">
                <?= $form->field($model, 'newArticleTimeLimit')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-2">
                <?= $form->field($model, 'periodBetweenPushes')->textInput(['type' => 'number']) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-1">
                <?= $form->field($model, 'enabled')->checkbox() ?>
            </div>
            <div class="col-lg-1">
                <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
            </div>
        </div>

        <?php ActiveForm::end(); ?>

    </div>


</div>
