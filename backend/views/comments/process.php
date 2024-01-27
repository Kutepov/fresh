<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;

/**
* @var yii\web\View $this
* @var backend\models\Comment $model
* @var yii\widgets\ActiveForm $form
*/

$this->title = 'Редактирование комментария';
$this->params['breadcrumbs'][] = ['label' => 'Comments', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="comment-create">
    <div class="page-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="comment-form">
        <?php $form = ActiveForm::begin() ?>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'enabled')->dropDownList([1 => 'Да', 0 => 'Нет']) ?>
            </div>
            <div class="col-md-9">
                <?= $form->field($model, 'text')->textarea(['maxLength' => true, 'rows' => 4]) ?>
            </div>
        </div>
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

        <?php ActiveForm::end(); ?>

    </div>


</div>
