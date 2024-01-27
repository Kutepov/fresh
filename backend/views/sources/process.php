<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;
use backend\models\Country;
use kartik\widgets\Select2;
use \backend\models\Language;
use backend\models\Source;
use yii\web\View;
use kartik\depdrop\DepDrop;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var backend\models\Source $model
 * @var yii\widgets\ActiveForm $form
 */

$this->title = $model->isNewRecord ? 'Добавить источник' : 'Редактировать источник: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Источники', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerJsFile(
    '/js/manage-sources.js',
    ['position' => View::POS_END, 'depends' => 'yii\web\JqueryAsset']
);
?>
<div class="source-create">
    <div class="page-header">
        <h3><?= Html::encode($this->title) ?></h3>
    </div>

    <div class="source-form">

        <?php $form = ActiveForm::begin([
            'enableAjaxValidation' => true
        ]); ?>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
                <?= $form->field($model, 'copy_from_source_id')->widget(Select2::class, [
                    'data' => $model->getAllSourcesForDropdown(),
                    'options' => ['placeholder' => 'Выберите источник', 'id' => 'copy_from_source_id'],
                    'pluginOptions' => [
                        'allowClear' => true,
                    ]
                ]);
                ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'url') ?>
            </div>
            <?php if ($model->isNewRecord): ?>
                <div class="col-md-3">
                    <?= $form->field($model, 'countries_ids')->widget(Select2::class, [
                        'data' => Country::getForDropdown(true),
                        'options' => ['placeholder' => 'Выберите страны, в которых будет доступен источник', 'multiple' => true],
                    ])
                    ?>
                    <?= $form->field($model, 'country')->widget(Select2::class, [
                        'data' => Country::getForDropdown(),
                        'pluginOptions' => [
                            'allowClear' => true,
                        ],
                        'options' => ['placeholder' => 'Выберите страну']
                    ]) ?>
                </div>
            <?php endif; ?>
            <div class="col-md-3">
                <?= $form->field($model, 'group_id')->widget(Select2::class, [
                    'data' => $model->getForDropdown(),
                    'options' => ['placeholder' => 'Выберите источник', 'id' => 'group_id'],
                    'pluginEvents' => [
                        "change" => "function() {
                                if ($(this).val() === '') {
                                    $('.js-wrapper-language').addClass('hidden');
                                } else {
                                $('.js-wrapper-language').removeClass('hidden');
                                }
                            }",
                        "select2:unselecting" => $model->isNewRecord ? '' : "function() { return confirm('Удалить группировку?'); }"
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                    ]
                ])
                ?>

                <div class="js-wrapper-language">
                    <?= $form->field($model, 'language', ['options' => ['class' => 'form-group highlight-addon field-source-language required']])->widget(DepDrop::class, [
                        'type' => DepDrop::TYPE_SELECT2,
                        'data' => $model->getAvailableLanguagesForDropdown(),
                        'options' => ['placeholder' => 'Выберите язык'],
                        'pluginOptions' => [
                            'depends' => ['group_id'],
                            'url' => Url::to(['/sources/get-available-languages', 'id' => $model->id]),
                        ],
                        'select2Options' => [
                            'pluginOptions' => [
                                'allowClear' => true,
                            ],
                        ]
                    ])->label('Язык (только для сгруппированных источников)', ['class' => 'control-label has-star']);
                    ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'type')->dropDownList(Source::TYPES) ?>
                <div class="webview-wrapper-js <?= $model->type === 'webview' ? '' : 'hidden' ?>">
                    <?= $form->field($model, 'webview_js')->checkbox() ?>
                </div>
                <?php if ($model->image && !$model->isNewRecord) {
                    echo Html::img($model->getAbsoluteUrlToImage(), ['width' => 100, 'height' => 100]);
                } ?>
                <?= $form->field($model, 'imageFile')->fileInput() ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'timezone')->widget(
                    Select2::class,
                    [
                        'data' => Country::getTimezonesForDropdown(),
                        'options' => ['placeholder' => 'Часовой пояс'],
                        'pluginOptions' => [
                            'allowClear' => true,
                        ]
                    ]
                ) ?>
            </div>
            <div class="col-md-3">
                <label for="">Доп. опции</label>
                <?= $form->field($model, 'enabled')->checkbox() ?>
                <?= $form->field($model, 'enable_comments')->checkbox() ?>
                <?= $form->field($model, 'default')->checkbox() ?>
                <!--                --><?php //= $form->field($model, 'use_publication_date')->checkbox() ?>
                <?= $form->field($model, 'banned_top')->checkbox() ?>
                <?= $form->field($model, 'ios_enabled')->checkbox() ?>
                <?= $form->field($model, 'android_enabled')->checkbox() ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'note')->textarea(['rows' => 7]) ?>
            </div>
        </div>

        <hr/>
        <div class="row">
            <div class="col-lg-3">
                <?= $form->field($model, 'telegram')->checkbox() ?>
                <?= $form->field($model, 'telegram_channel_id')->textInput() ?>
            </div>
            <div class="col-lg-3">
                <?= $form->field($model, 'push_notifications')->checkbox() ?>
            </div>
        </div>
        <hr/>

        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'injectable_css')->textarea(['rows' => 10]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'injectable_js')->textarea(['rows' => 10]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'adblock_css_selectors')->textarea(['rows' => 10]) ?>
                <?= $form->field($model, 'processed')->checkbox() ?>
            </div>
        </div>

        <?php echo Html::submitButton($model->isNewRecord ? 'Добавить' : 'Обновить',
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']
        );
        ActiveForm::end(); ?>

    </div>


</div>
