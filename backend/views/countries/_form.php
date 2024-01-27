<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;
use kartik\select2\Select2;
use backend\models\Country;
use backend\models\Language;
use unclead\multipleinput\MultipleInput;
use yii\web\View;

/* @var $this yii\web\View */
/* @var $model Country */
/* @var $form kartik\widgets\ActiveForm */

$this->registerJsFile(
    '/js/manage-countries.js',
    ['position' => View::POS_END, 'depends' => 'yii\web\JqueryAsset']
);
?>

<div class="country-form">

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <div class="row">
        <div class="col-md-3">
            <?= $form->field($model, 'code')->textInput(['maxlength' => true]) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'priority')->textInput() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'timezone')->widget(
                Select2::class,
                ['data' => Country::getTimezonesForDropdown(), 'options' => ['placeholder' => 'Часовой пояс']]
            ) ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <?= $form->field($model, 'articles_preview_type')->dropDownList(Country::PREVIEW_TYPES_SELECTBOX) ?>
            <?= $form->field($model, 'articles_preview_type_switcher')->checkbox() ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'quality_survey')->checkbox() ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <?php if ($model->image && !$model->isNewRecord) {
                echo Html::img($model->getAbsoluteUrlToImage(), ['width' => 150, 'height' => 100]);
            } ?>
            <?= $form->field($model, 'imageFile')->fileInput() ?>
        </div>
        <div class="col-md-9">
            <?= $form->field($model, 'languages')->widget(MultipleInput::class, [
                'min' => 0,
                'max' => Language::find()->count(),
                'columns' => [
                    [
                        'name' => 'id',
                        'type' => Select2::class,
                        'title' => 'Язык',
                        'options' => [
                            'data' => Language::getListForCountriesForm(),
                            'pluginOptions' => [
                                'tags' => true,
                                'maximumInputLength' => 64,
                            ],
                            'options' => [
                                'class' => 'js-name-language',
                                'placeholder' => 'Select language',
                            ],

                        ],
                    ],
                    [
                        'name' => 'code',
                        'title' => 'Код',
                        'enableError' => true,
                        'options' => [
                            'class' => 'js-language js-name-language',
                            'maxLength' => 5,
                        ],
                    ],
                    [
                        'name' => 'short_name',
                        'title' => 'Аббревиатура',
                        'options' => [
                            'class' => 'js-language js-name-language',
                            'maxLength' => 16,
                        ],
                        'enableError' => true,
                    ],
                    [
                        'name' => 'default',
                        'title' => 'По умолчанию',
                        'type' => 'radio',
                        'options' => [
                            'class' => 'js-default-language',
                        ],
                        'enableError' => true,
                    ],
                ],
            ]); ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
