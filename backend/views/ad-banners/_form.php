<?php

use yii\helpers\Html;
use kartik\widgets\ActiveForm;
use backend\models\AdBanner;
use backend\models\Country;
use kartik\widgets\Select2;
use backend\models\Category;
use yii\web\View;

/**
 * @var yii\web\View $this
 * @var backend\models\AdBanner $model
 * @var yii\widgets\ActiveForm $form
 */

$this->registerJsFile(
    '/js/manage-ad-banners.js',
    ['position' => View::POS_END, 'depends' => 'yii\web\JqueryAsset'])
?>

<div class="ad-banner-form">
    <?php $form = ActiveForm::begin([
        'enableAjaxValidation' => true,
    ]) ?>
    <div class="row">
        <div class="col-md-1">
            <?= $form->field($model, 'enabled')->dropDownList([1 => 'Да', 0 => 'Нет']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'platform')->dropDownList(AdBanner::PLATFORMS) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'country')->widget(Select2::class, [
                'data' => Country::getForDropdown(),
                'options' => ['placeholder' => 'Выберите страну'],
            ]);
            ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'provider')->dropDownList(AdBanner::PROVIDERS, ['readonly' => in_array($model->type, [AdBanner::TYPE_ARTICLE_BODY, AdBanner::TYPE_SIMILAR_ARTICLES]) ? true : false]) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($model, 'type')->dropDownList(AdBanner::TYPES) ?>
        </div>
        <div class="col-md-2">
            <div id="ad-banners-additional-fields" <?= $model->type !== AdBanner::TYPE_ARTICLE ? '' : 'class="hidden"' ?>>
                <?= $form->field($model, 'position', ['options' => ['class' => 'additional-field']])->textInput() ?>
                <?= $form->field($model, 'repeat_factor', ['options' => ['class' => in_array($model->type, [AdBanner::TYPE_ARTICLE_BODY, AdBanner::TYPE_SIMILAR_ARTICLES]) ? ['additional-field hidden'] : ['additional-field']]])->textInput() ?>
                <?= $form->field($model, 'limit', ['options' => ['class' => in_array($model->type, [AdBanner::TYPE_ARTICLE_BODY, AdBanner::TYPE_SIMILAR_ARTICLES]) ? ['additional-field hidden'] : ['additional-field']]])->textInput() ?>
                <?= $form->field($model, 'banner_id', ['options' => ['class' => in_array($model->type, [AdBanner::TYPE_ARTICLE_BODY, AdBanner::TYPE_SIMILAR_ARTICLES]) ? ['additional-field'] : ['additional-field hidden']]])->textInput() ?>
            </div>
            <div id="ad-banners-categories-field" <?= $model->type === AdBanner::TYPE_CATEGORY ? '' : 'class="hidden"' ?>>
                <?= $form->field($model, 'categories')->widget(
                    Select2::class, [
                        'data' => Category::getForDropdown(),
                        'options' => ['placeholder' => 'Укажите категории', 'multiple' => true],
                    ]
                ) ?>
            </div>
        </div>
    </div>


    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

    <?php ActiveForm::end(); ?>

</div>
