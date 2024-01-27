<?php

use backend\models\Country;
use kartik\widgets\ActiveForm;
use kartik\helpers\Html;
use kartik\daterange\DateRangePicker;
use backend\models\Source;

/**
 * @var $model \backend\models\search\statistics\CategoriesSearch
 */
?>
<div class="post-search">
    <?php $form = ActiveForm::begin([
        'action' => ['categories'],
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
            <?= $form->field($model, 'countryCode')->widget(\kartik\select2\Select2::class, [
                'data' => Country::getForDropdown(),
                'pluginEvents' => [
                    "select2:selecting" => "function() { $('.js-dep-fields').removeClass('hidden') }",
                    "select2:unselecting" => "function() { $('.js-dep-fields').addClass('hidden') }"
                ],
                'options' => [
                    'id' => 'countryCode',
                    'placeholder' => 'Укажите страну'
                ],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]); ?>
        </div>


        <div class="js-dep-fields <?= $model->articlesLanguage ? '' : 'hidden' ?>">
            <div class="col-md-2">
                <?= $form->field($model, 'articlesLanguage')->widget(\kartik\depdrop\DepDrop::class, [
                    'type' => \kartik\depdrop\DepDrop::TYPE_SELECT2,
                    'data' => $languages,
                    'options' => ['placeholder' => 'Выберите язык'],
                    'select2Options' => [
                        'pluginOptions' => [
                            'allowClear' => true,
                        ]
                    ],
                    'pluginOptions' => [
                        'depends' => ['countryCode'],
                        'url' => \yii\helpers\Url::to(['/statistics/get-languages-list']),
                    ]
                ]); ?>
            </div>
        </div>

        <div class="col-md-2">
            <?= $form->field($model, 'previewType')->dropDownList(Country::PREVIEW_TYPES_SELECTBOX, ['prompt' => 'Любые']); ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-1" style="margin-top: 25px;">
            <div class="form-group">
                <?= Html::submitButton('Искать', ['class' => 'btn btn-primary']) ?>
            </div>
        </div>
    </div>


    <?php ActiveForm::end(); ?>
</div>
