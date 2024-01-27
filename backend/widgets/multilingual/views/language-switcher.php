<?php

use backend\assets\FormLanguageSwitcherAsset as LocalFormLanguageSwitcherAsset;
use \yeesoft\multilingual\assets\FormLanguageSwitcherAsset;
use kartik\widgets\Select2;
use yii\web\JsExpression;

/* @var $this yii\web\View */
/* @var $languages array */
/* @var $language string */
/* @var $emptyLanguages array */
/* @var $progress array */
FormLanguageSwitcherAsset::register($this);
LocalFormLanguageSwitcherAsset::register($this);
?>
<div class="row wrapper-language-switcher">
    <div class="form-language-switcher col-md-3 ">
        <label class="text-<?= $progress['total'] === $progress['filled'] ? 'success' : 'danger' ?>">
            Язык (переведено <?= $progress['filled'] ?> из <?= $progress['total'] ?> )
        </label>
        <?php if (count($languages) > 1) {
            echo Select2::widget([
                'name' => 'language-switcher',
                'data' => $languages,
                'pluginOptions' => [
                    'templateResult' => new JsExpression(
                        "function format(state) {
                            if (" . json_encode($emptyLanguages) . ".includes(state.id)) {
                                return $('<span>' +state.text +' </span> <span class=\'glyphicon glyphicon-info-sign text-danger\'></span>');
                            }
                            return state.text;
                        }"
                    ),
                ],
                'options' => [
                    'data-toggle' => 'pill',
                ],
            ]);
        } ?>
    </div>
</div>