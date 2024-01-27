<?php

/**
 * @var $model \backend\models\search\SourceSearch
 */

use kartik\daterange\DateRangePicker;
use kartik\widgets\ActiveForm;
use yii\helpers\Html;

?>

<div class="post-search">
    <?php $form = ActiveForm::begin([
        'method' => 'get',
    ]); ?>
    <div class="row">
        <div class="col-md-3">
            <?= $form->field($model, 'dateInterval')->widget(DateRangePicker::class, [
                'presetDropdown' => true,
                'convertFormat' => true,
                'useWithAddon' => true,
                'pluginOptions' => [
                    'locale' => ['format' => 'Y-m-d']
                ],
            ])->label('Дата') ?>
        </div>
        <div class="col-md-1" style="margin-top: 25px;">
            <div class="form-group">
                <?= Html::submitButton('Искать', ['class' => 'btn btn-primary']) ?>
            </div>
        </div>
    </div>
    <?php ActiveForm::end() ?>
</div>