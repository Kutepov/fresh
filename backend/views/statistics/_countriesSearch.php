<?php

use backend\models\Country;
use kartik\widgets\ActiveForm;
use kartik\helpers\Html;
use kartik\daterange\DateRangePicker;
use backend\models\Source;

/**
 * @var $model \backend\models\search\statistics\CommonSearch
 */
?>
<div class="post-search">
    <?php $form = ActiveForm::begin([
        'action' => ['countries'],
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
            ]); ?>
        </div>
        <div class="col-md-1">
            <?= $form->field($model, 'platform')->dropDownList(['ios' => 'iOS', 'android' => 'Android'], ['prompt' => '']); ?>
        </div>

        <div class="col-md-2">
            <?= $form->field($model, 'type')->dropDownList(Source::TYPES, ['prompt' => '']); ?>
        </div>

        <div class="col-md-2">
            <?= $form->field($model, 'widget')->dropDownList(['my-feed-top' => 'Топ', 'similar-articles' => 'Читать также', 'bookmarks' => 'Закладки', 'same-articles' => 'Одинаковые'], ['prompt' => '']); ?>
        </div>

        <div class="col-md-2">
            <?= $form->field($model, 'previewType')->dropDownList(Country::PREVIEW_TYPES_SELECTBOX, ['prompt' => 'Любые']); ?>
        </div>
        <div class="col-md-1" style="margin-top: 25px;">
            <div class="form-group">
                <?= Html::submitButton('Искать', ['class' => 'btn btn-primary']) ?>
            </div>
        </div>

    </div>


    <?php ActiveForm::end(); ?>
</div>
