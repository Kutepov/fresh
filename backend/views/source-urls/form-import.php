<?php

use yii\widgets\ActiveForm;
use yii\helpers\Html;
use kartik\select2\Select2;
use backend\models\Source;

/**
 * @var \backend\models\forms\ImportSourcesUrls $model
 */

?>

<?php
$form = ActiveForm::begin([
        'id' => 'form-import',
        'enableClientValidation' => true,
    ]
);

echo $form->field($model, 'source_id')->widget(
    Select2::class,
    [
        'data' => Source::getForDropdownSimple(),
        'options' => ['placeholder' => 'Источник'],
    ]);
echo $form->field($model, 'urls')
    ->textarea(['rows' => 10])
    ->label('Список урлов на разделы сайта')
    ->hint('Каждый с новой строки') ?>

    <div class="form-group">
        <?= Html::submitButton('Импортировать', ['class' => 'btn btn-success']) ?>
    </div>

<?php ActiveForm::end(); ?>