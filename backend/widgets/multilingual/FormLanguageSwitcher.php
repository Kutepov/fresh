<?php

namespace backend\widgets\multilingual;

use Yii;

/**
 * Widget to display buttons to switch languages in forms
 */
class FormLanguageSwitcher extends \yeesoft\multilingual\widgets\FormLanguageSwitcher
{
    public $emptyLanguages;
    public $progress;

    public function run()
    {
        if ($this->languages) {
            return $this->render($this->view, [
                'language' => Yii::$app->language,
                'languages' => $this->languages,
                'emptyLanguages' => $this->emptyLanguages,
                'progress' => $this->progress,
            ]);
        }
    }

}
