<?php

use kartik\widgets\ActiveForm;
use kartik\helpers\Html;
use backend\models\Country;
use kartik\widgets\Select2;
use kartik\daterange\DateRangePicker;
use backend\models\Category;
use kartik\depdrop\DepDrop;
use yii\helpers\Url;

/**
 * @var $model \backend\models\search\statistics\ByNewsSearch
 * @var array $sources
 * @var array $languages
 */
?>
<div class="post-search">
    <?php $form = ActiveForm::begin([
        'action' => ['news'],
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
                <?= $form->field($model, 'language')->widget(DepDrop::class, [
                    'type' => DepDrop::TYPE_SELECT2,
                    'data' => $languages,
                    'options' => ['placeholder' => 'Выберите язык'],
                    'select2Options' => [
                        'pluginOptions' => [
                            'allowClear' => true,
                        ]
                    ],
                    'pluginOptions' => [
                        'depends' => ['country_id'],
                        'url' => Url::to(['/statistics/get-languages-list']),
                    ]
                ]); ?>
            </div>
        </div>

        <div class="js-dep-fields <?= $model->country_id ? '' : 'hidden' ?>">
            <div class="col-md-2">
                <?= $form->field($model, 'source_id')->widget(DepDrop::class, [
                    'type' => DepDrop::TYPE_SELECT2,
                    'data' => $sources,
                    'options' => ['placeholder' => 'Укажите источник'],
                    'select2Options' => [
                        'pluginOptions' => [
                            'allowClear' => true,
                        ]
                    ],
                    'pluginOptions' => [
                        'depends' => ['country_id'],
                        'url' => Url::to(['/statistics/get-class-list']),
                    ]
                ]); ?>
            </div>
        </div>


        <div class="js-dep-fields <?= $model->country_id ? '' : 'hidden' ?>">
            <div class="col-md-2">
                <?= $form->field($model, 'category_id')->widget(Select2::class, [
                    'data' => Category::getForDropdown(),
                    'options' => ['placeholder' => 'Укажите категорию'],
                    'pluginOptions' => [
                        'allowClear' => true
                    ],
                ]); ?>
            </div>
        </div>

    </div>
</div>

<div class="row">
    <div class="col-md-1">
        <?= $form->field($model, 'ctrFrom')->textInput(['type' => 'number'])->label('Минимальный CTR фида') ?>
    </div>
    <div class="col-md-2">
        <?= $form->field($model, 'top_position')->dropDownList([0 => 'Нет', 30 => 'Топ-30', 999999 => 'Весь топ'], [
            'prompt' => '',
            'allowClear' => true
        ])->label('ТОП') ?>
    </div>
    <div class="col-md-2">
        <?= $form->field($model, 'platform')->dropDownList(['ios' => 'iOS', 'android' => 'Android'], ['prompt' => '']) ?>
    </div>

    <div class="col-md-1" style="margin-top: 25px;">
        <div class="form-group">
            <?= Html::submitButton('Искать', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>


<?= $form->field($model, 'source_url_id')->hiddenInput()->label(false) ?>
<?php ActiveForm::end(); ?>
