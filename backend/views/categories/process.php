<?php

use yii\helpers\Html;
use backend\widgets\multilingual\ActiveForm;
use kartik\select2\Select2;
use backend\models\Country;

/**
 * @var yii\web\View $this
 * @var backend\models\Category $model
 * @var yii\widgets\ActiveForm $form
 */

$this->title = $model->isNewRecord ? 'Создать' : 'Редактировать категорию: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Категории', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="category-create">
    <div class="category-form">
        <?php $form = ActiveForm::begin() ?>
        <?= $form->languageSwitcher($model, '@backend/widgets/multilingual/views/language-switcher'); ?>
        <h3><?= Html::encode($this->title) ?></h3>

        <?php if (Yii::$app->session->hasFlash('message')): ?>
            <div class="alert alert-info" role="alert">
                <?= Yii::$app->session->getFlash('message') ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-2">
                <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
            </div>
            <div class="col-md-1">
                <?= $form->field($model, 'priority')->textInput(['maxLength' => true,]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'countriesList')->widget(Select2::class, [
                    'data' => Country::getForDropdown(),
                    'options' => ['placeholder' => 'Все страны', 'multiple' => true],
                    'pluginOptions' => [
                        'tokenSeparators' => [',', ' '],
                        'closeOnSelect' => false,
                    ],
                ]);
                ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-2">
                <?php if ($model->image && !$model->isNewRecord) {
                    echo Html::img($model->getAbsoluteUrlToImage(), ['width' => 100, 'height' => 100]);
                } ?>
                <?= $form->field($model, 'imageFile')->fileInput() ?>
            </div>

            <div class="col-md-2">
                <?php if ($model->icon && !$model->isNewRecord) {
                    echo Html::img($model->getAbsoluteUrlToIcon(), ['width' => 100, 'height' => 100]);
                } ?>
                <?= $form->field($model, 'iconFile')->fileInput() ?>
            </div>
        </div>


        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

        <?php ActiveForm::end(); ?>

    </div>


</div>
