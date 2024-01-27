<?php namespace buzz\widgets;

class ActiveForm extends \yii\widgets\ActiveForm
{
    public $fieldClass = 'buzz\widgets\ActiveField';
    public $enableClientValidation = false;
    public $validateOnBlur = false;
    public $validateOnSubmit = false;
    public $validateOnChange = false;
    public $validateOnType = false;
    public $scrollToError = false;
    public $errorCssClass = 'error';

    public $encodeErrorSummary = false;

    const NO_LABELS = '<div class="error-notify">{input}{error}</div>';
    const LABELS = '{label}<div class="error-notify">{input}{error}</div>';

    public function init()
    {
        if (!$this->options['class']) {
            $this->options['class'] = 'form';
        }
        parent::init();
    }
}