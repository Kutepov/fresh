<?php

namespace backend\widgets\multilingual;

class ActiveForm extends \yeesoft\multilingual\widgets\ActiveForm
{

    /**
     * Renders form language switcher.
     *
     * @param \yii\base\Model $model
     * @param string $view
     * @return string
     */
    public function languageSwitcher($model, $view = null)
    {
        $languages = ($model->getBehavior('multilingual')) ? $model->getBehavior('multilingual')->languages : [];
        $emptyLanguages = $model->getEmptyLanguages();
        $progress = $model->getProgressTranslates();

        return FormLanguageSwitcher::widget(compact('view', 'emptyLanguages', 'languages', 'progress'));
    }
}