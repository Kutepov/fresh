<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var \backend\models\forms\SettingsForm $model
 * @var yii\widgets\ActiveForm $form
 */

$this->title = 'Настройки постинга в телеграм';
?>
<div class="comment-create">
    <div class="page-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="comment-form">
        <?php $form = ActiveForm::begin() ?>

        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'minClicksCount')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'minCtr')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'newArticleTimeLimit')->textInput(['type' => 'number']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'approvePeriod')->textInput(['type' => 'number']) ?>
            </div>
        </div>

        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

        <?php ActiveForm::end(); ?>

    </div>


</div>
