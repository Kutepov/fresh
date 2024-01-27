<?php

use yii\helpers\Html;
use backend\widgets\multilingual\ActiveForm;
use backend\models\User;

/**
 * @var yii\web\View $this
 * @var backend\models\User $model
 * @var yii\widgets\ActiveForm $form
 */

$this->title = $model->isNewRecord ? 'Создать' : 'Редактировать: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Пользователи', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="category-create">
    <div class="category-form">
        <?php $form = ActiveForm::begin() ?>
        <h1><?= Html::encode($this->title) ?></h1>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'email')->textInput(['maxlength' => true]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'status')->dropDownList(User::STATUSES_FOR_DROPDOWN) ?>
                <?= $form->field($model, 'shadow_ban')->checkbox() ?>
                <?php if ($model->apps): ?>
                    <?= $form->field($model, 'appBan')->checkbox() ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'ip')->textInput(['maxlength' => true, 'readonly' => true]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'useragent')->textInput(['maxlength' => true, 'readonly' => true]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'country_code')->textInput(['maxlength' => true, 'readonly' => true]) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'language_code')->textInput(['maxlength' => true, 'readonly' => true]) ?>
            </div>
            <div class="col-md-3">
                <?php if ($model->photo && !$model->isNewRecord) {
                    echo Html::img($model->getAbsoluteUrlToImage(), ['width' => 100, 'height' => 100]);
                } ?>
                <?= $form->field($model, 'imageFile')->fileInput() ?>
            </div>
        </div>

        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

        <?php ActiveForm::end(); ?>

    </div>


</div>
