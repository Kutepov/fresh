<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var \backend\models\forms\SettingsForm $model
 * @var yii\widgets\ActiveForm $form
 */

$service = Yii::$container->get(\common\services\MultilingualService::class);
$country = Yii::$app->request->get('country', 'UA');
$this->title = 'Настройки топа новостей';
$this->registerJsFile('/js/settings.js', [
    'depends' => \backend\assets\AppAsset::class
]);
?>
<div class="comment-create">
    <div class="page-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="comment-form">
        <?= Html::dropDownList('country', $country, $service->getAvailableCountriesForDropDownList(), [
            'data-country-dropdown' => '/settings/top'
        ]) ?>
        <hr/>
        <?php $form = ActiveForm::begin() ?>

        <div class="row">
            <div class="col-lg-2">
                <?= $form->field($model, 'topCtrUpdateForComment')->textInput(['type' => 'number']) ?>
            </div>
            <div class="col-lg-2">
                <?= $form->field($model, 'topCtrUpdateForRating')->textInput(['type' => 'number']) ?>
            </div>
            <div class="col-lg-2">
                <?= $form->field($model, 'topCtrUpdateForSharing')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'newArticleTopTimeLimit')->textInput(['type' => 'number']) ?>
                <?= $form->field($model, 'ctrPeriod')->textInput(['type' => 'number']) ?>
                <?= $form->field($model, 'topCalculationPeriod')->textInput(['type' => 'number']) ?>
                <?= $form->field($model, 'minClicksThreshold')->textInput(['type' => 'number']) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'ctrDecreaseStartHour')->textInput(['type' => 'number']) ?>
            </div>
            <div class="col-lg-3">
                <?= $form->field($model, 'ctrDecreasePercent')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-4">
                <?= $form->field($model, 'ctrDecreaseYesterdayPercent')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

        <?php ActiveForm::end(); ?>

    </div>


</div>
