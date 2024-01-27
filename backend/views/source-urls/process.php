<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;
use kartik\depdrop\DepDrop;
use kartik\select2\Select2;
use backend\models\Source;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var backend\models\SourceUrl $model
 * @var yii\widgets\ActiveForm $form
 * @var array $classes
 * @var array $categories
 * @var array $timezones
 */

$this->title = $model->isNewRecord ? 'Создать категорию источника' : 'Редактировать категорию источника: ' . $model->url;
$this->params['breadcrumbs'][] = ['label' => 'Источники', 'url' => ['/sources']];
$this->params['breadcrumbs'][] = ['label' => 'Категории источников', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="source-url-create">
    <div class="page-header">
        <h3><?= Html::encode($this->title) ?></h3>
    </div>

    <div class="source-url-form">

        <?php $form = ActiveForm::begin(); ?>

        <div class="row">
            <div class="col-md-3">
                <?= $form->field($model, 'source_id')->widget(Select2::class, [
                    'data' => Source::getForDropdownSimple(),
                    'id' => 'source_id',
                    'pluginEvents' => $model->isNewRecord ? [] : ["select2:selecting" => "function() { return confirm('Изменить категорию?'); }"],
                    'options' => ['placeholder' => 'Выберите источник', 'id' => 'source_id'],
                ]);
                ?>
                <?= $form->field($model, 'url')->textInput(['maxlength' => true]) ?>
                <?= $form->field($model, 'url_skip_regexp')->textInput(['maxlength' => true]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'countries_ids')->widget(Select2::class, [
                    'data' => \backend\models\Country::getForDropdown(true),
                    'options' => ['placeholder' => 'Выберите страны, в которых будет доступна категория источника', 'multiple' => true],
                ])
                ?>
                <label for="">Доп. опции</label>
                <?= $form->field($model, 'enabled')->checkbox() ?>
                <?= $form->field($model, 'ios_enabled')->checkbox() ?>
                <?= $form->field($model, 'android_enabled')->checkbox() ?>
                <?= $form->field($model, 'last_scraped_article_date_disabled')->checkbox() ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'category_id')->widget(DepDrop::class, [
                    'type' => DepDrop::TYPE_SELECT2,
                    'data' => $categories,
                    'options' => ['placeholder' => 'Укажите категорию'],
                    'select2Options' => [
                        'pluginOptions' => ['allowClear' => true]
                    ],
                    'pluginOptions' => [
                        'depends' => ['source_id'],
                        'url' => Url::to(['/sources/categories/get-categories-list']),
                    ]
                ]); ?>
                <?= $form->field($model, 'class')->widget(DepDrop::class, [
                    'type' => DepDrop::TYPE_SELECT2,
                    'data' => $classes,
                    'options' => [
                        'placeholder' => 'Укажите класс',
                        'value' => count($classes) === 1 && $model->isNewRecord ? array_key_first($classes) : $model->class
                    ],
                    'select2Options' => [
                        'pluginEvents' => $model->isNewRecord ? [] : ["select2:selecting" => "function() { return confirm('Изменить класс?'); }"],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'ajax' => [
                                'method' => 'post',
                                'url' => Url::to(['/sources/categories/get-class-list']),
                                'data' => new \yii\web\JsExpression('function (params) {
                                    return {
                                        "sourceId": $("#source_id").val()
                                    }
                                }')
                            ],
                        ]
                    ],
                    'pluginOptions' => [
                        'depends' => ['source_id'],
                        'url' => Url::to(['/sources/categories/get-class-list']),
                    ]
                ]); ?>

                <?= $form->field($model, 'timezone')->widget(
                    DepDrop::class,
                    [
                        'type' => DepDrop::TYPE_SELECT2,
                        'data' => $timezones,
                        'options' => [
                            'placeholder' => 'Часовой пояс',
                            'value' => count($timezones) === 1 && $model->isNewRecord ? array_key_first($timezones) : $model->timezone
                        ],
                        'pluginOptions' => [
                            'depends' => ['source_id'],
                            'url' => Url::to(['/sources/categories/get-timezone-list']),
                        ]
                    ]
                )
                ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'note')->textarea(['rows' => 9]) ?>
            </div>
        </div>

        <?php echo Html::submitButton($model->isNewRecord ? 'Добавить' : 'Обновить',
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']
        );
        ActiveForm::end(); ?>
    </div>
</div>
