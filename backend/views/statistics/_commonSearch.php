<?php

use backend\models\Category;
use kartik\widgets\ActiveForm;
use kartik\helpers\Html;
use backend\models\Country;
use kartik\widgets\Select2;
use kartik\daterange\DateRangePicker;
use backend\models\Source;

/**
 * @var $model \backend\models\search\statistics\CommonSearch
 * @var array $languages
 */
?>
<div class="post-search">
    <?php $form = ActiveForm::begin([
        'action' => ['index'],
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
            <?= $form->field($model, 'country_id')->widget(Select2::class, [
                'data' => Country::getForDropdown(),
                'pluginEvents' => [
                    "select2:selecting" => "function() { $('.js-dep-fields').removeClass('hidden') }",
                    "select2:unselecting" => "function() { $('.js-dep-fields').addClass('hidden') }"
                ],
                'options' => [
                    'id' => 'country_id',
                    'placeholder' => 'Укажите страну'
                ],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]); ?>
        </div>


        <div class="js-dep-fields <?= $model->language ? '' : 'hidden' ?>">
            <div class="col-md-2">
                <?= $form->field($model, 'language')->widget(\kartik\depdrop\DepDrop::class, [
                    'type' => \kartik\depdrop\DepDrop::TYPE_SELECT2,
                    'data' => $languages,
                    'options' => ['placeholder' => 'Выберите язык'],
                    'select2Options' => [
                        'pluginOptions' => [
                            'allowClear' => true,
                        ]
                    ],
                    'pluginOptions' => [
                        'depends' => ['country_id'],
                        'url' => \yii\helpers\Url::to(['/statistics/get-languages-list']),
                    ]
                ]); ?>
            </div>
        </div>

        <div class="col-md-2">
            <?= $form->field($model, 'categoryId')->widget(Select2::class, [
                'data' => Category::getForDropdown($model->country_id),
                'options' => [
                    'placeholder' => '',
                ]
            ]); ?>
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
